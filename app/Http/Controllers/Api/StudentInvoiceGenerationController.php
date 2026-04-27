<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportStudentInvoiceCsvRequest;
use App\Http\Requests\GenerateStudentInvoiceRequest;
use App\Http\Requests\SearchStudentInvoiceGenerationRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\LookupDetail;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Student Finance > Invoice Generation (PAGEID 970 / MENUID 1231).
 *
 * Source: FIMS BL `CALL_PROC_STUDENT_INVOICE` (legacy GET branches
 * `find=1`, `csv=1`, `match=1`, `generate=1`). The legacy page is a
 * Search Parameter form (semester / program level / student type /
 * structure type / matric / intake case) followed by a datatable
 * "List of Students : Invoice Generation" with 8 columns and a
 * Generate button.
 *
 * Both the listing and the generate flow are driven by MySQL stored
 * procedures on `mysql_secondary`:
 *   - `invoiceCheckingByBatch(semester, programLevel, studentType,
 *       intakeCase, username, uniqueKey, feeType, matricNo, OUT mesg,
 *       OUT wout)` — populates the per-call `temp_stud_listing_match`
 *       roster keyed by the generated `c_unique_key`. The same
 *       `uniqueKey` is then used to scope the listing SELECT, the CSV
 *       exports, and the subsequent `generate` call.
 *   - `invoiceCreationByBatch(semester, programLevel, studentType,
 *       intakeCase, username, uniqueKey, feeType, matricNo, OUT mesg,
 *       OUT wout)` — creates `cust_invoice_master`/`cust_invoice_details`
 *       rows for the roster and seeds workflow tasks.
 *
 * Migration parity vs deviations:
 *   - We mirror the legacy `c.c_unique_key` scoping exactly, so the
 *     three listing/export/generate endpoints stay coupled to a single
 *     search batch.
 *   - The legacy `generate=1` branch rewrites every newly-created
 *     `wf_task.wtk_task_url` to point at legacy `index.php?...&menuID=
 *     1492&...`. We preserve that string verbatim so workflow approvers
 *     who still use the legacy app keep working — MENUID 1492 is NOT
 *     migrated yet, so opening the URL inside this CMS would 404.
 *   - The legacy `?match=1` CSV downloads the post-generate match data
 *     by the same `uniqueKey` — we expose it as `/export/match-csv`.
 *   - Stored-procedure CALL with OUT params + `unprepared`/session-var
 *     read follows the precedent in `InvestmentAccrualController` /
 *     `InvestmentGenerateScheduleController`.
 *   - Joined tables straddle utf8mb3 (legacy `student`,
 *     `temp_stud_listing_match`) vs utf8mb4 collations, so every join
 *     and search predicate goes through `CollationSafeSql::cs()` to
 *     avoid SQLSTATE[HY000] 1267 / 1253 (same hazard as
 *     `OfferedStudentController`).
 */
class StudentInvoiceGenerationController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    private const SORTABLE = [
        'matric',
        'name',
        'status',
        'program',
        'intake_case',
        'citizenship',
        'semester_no',
        'fee_code',
    ];

    public function __construct(
        protected AuditService $auditService,
    ) {}

    /**
     * GET `/student-finance/invoice-generation/options`.
     *
     * Returns the five legacy dropdowns (semester / programLevel /
     * studentType / feeType / intakeCase). Semester is sourced from
     * `academic_calendar` (legacy form had no lookup query attached;
     * other migrated student-finance pages also drive semester from
     * `academic_calendar`).
     */
    public function options(): JsonResponse
    {
        $semester = DB::connection('mysql_secondary')
            ->table('academic_calendar')
            ->select(['acl_semester_code', 'acl_semester_name'])
            ->whereNotNull('acl_semester_code')
            ->where('acl_semester_code', '!=', '')
            ->orderBy('acl_semester_code', 'desc')
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->acl_semester_code,
                'label' => $r->acl_semester_name
                    ? $r->acl_semester_code.' - '.$r->acl_semester_name
                    : (string) $r->acl_semester_code,
            ])
            ->values();

        $programLevel = $this->lookupOptions('PROGRAM_LEVEL', withDescription: true);
        $studentType = $this->lookupOptions('STUDENT_TYPE');
        $feeType = $this->lookupOptions('FEE_TYPE', activeOnly: true, sortBy: 'lde_sorting');
        $intakeCase = $this->lookupOptions('INTAKE_CASE', withDescription: true);

        return $this->sendOk([
            'semester' => $semester,
            'programLevel' => $programLevel,
            'studentType' => $studentType,
            'feeType' => $feeType,
            'intakeCase' => $intakeCase,
        ]);
    }

    /**
     * POST `/student-finance/invoice-generation/search`.
     *
     * Calls `invoiceCheckingByBatch(...)` then paginates the resulting
     * `temp_stud_listing_match` roster (same shape as the legacy
     * datatable). Returns the `uniqueKey` and `message` returned by
     * the SP so the frontend can pass them to the CSV / generate
     * endpoints.
     */
    public function search(SearchStudentInvoiceGenerationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $page = max(1, (int) ($validated['page'] ?? 1));
        $limit = max(1, min(100, (int) ($validated['limit'] ?? 10)));
        $q = trim((string) ($validated['q'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'matric');
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'matric';
        }
        $sortDir = strtolower((string) ($validated['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $semester = (string) $validated['semester'];
        $programLevel = (string) $validated['program_level'];
        $feeType = (string) $validated['fee_type'];
        $studentType = (string) ($validated['student_type'] ?? '');
        $intakeCase = (string) ($validated['intake_case'] ?? '');
        $matricNo = (string) ($validated['matric_no'] ?? '');

        $username = $this->resolveUsername($request);
        $uniqueKey = (string) Str::uuid();

        try {
            $message = $this->callInvoiceCheckingByBatch(
                $semester,
                $programLevel,
                $studentType,
                $intakeCase,
                $username,
                $uniqueKey,
                $feeType,
                $matricNo,
            );
        } catch (Throwable $e) {
            return $this->sendError(
                500,
                'INTERNAL_ERROR',
                'Failed to invoke invoiceCheckingByBatch.',
                ['reason' => strtok($e->getMessage(), "\n") ?: 'Stored procedure failed.']
            );
        }

        $base = $this->rosterQuery($uniqueKey);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            // Mirrors the legacy CONCAT_WS('__', ...) global-search surface.
            $base->whereRaw(
                'LOWER(CONCAT_WS(\'__\','
                    .$this->cs("IFNULL(std.std_student_id, '')").','
                    .$this->cs("IFNULL(std.std_intake_case, '')").','
                    .$this->cs("IFNULL(std.std_student_name, '')").','
                    .$this->cs("IFNULL(std.std_extended_field->>'$.std_status_desc', '')").','
                    .$this->cs("IFNULL(std.std_program, '')").','
                    .$this->cs("IFNULL(std.std_extended_field->>'$.std_intake_case_desc', '')").','
                    .$this->cs("IFNULL(std.std_extended_field->>'$.std_citizenship_status_desc', '')").','
                    .$this->cs("IFNULL(std.std_sem_level, '')").','
                    .$this->cs("IFNULL(c.c_code, '')").
                ')) LIKE ?',
                [$like]
            );
        }

        $total = (clone $base)->count();

        $orderColumn = match ($sortBy) {
            'matric' => 'std.std_student_id',
            'name' => 'std.std_student_name',
            'status' => DB::raw("std.std_extended_field->>'$.std_status_desc'"),
            'program' => 'std.std_program',
            'intake_case' => DB::raw("std.std_extended_field->>'$.std_intake_case_desc'"),
            'citizenship' => DB::raw("std.std_extended_field->>'$.std_citizenship_status_desc'"),
            'semester_no' => 'std.std_sem_level',
            'fee_code' => 'c.c_code',
            default => 'std.std_student_id',
        };

        $rows = (clone $base)
            ->select([
                'std.std_student_id',
                'std.std_student_name',
                'std.std_program',
                'std.std_intake_case',
                'std.std_sem_level',
                DB::raw("std.std_extended_field->>'$.std_status_desc' AS std_status_desc"),
                DB::raw("std.std_extended_field->>'$.std_intake_case_desc' AS std_intake_case_desc"),
                DB::raw("std.std_extended_field->>'$.std_citizenship_status_desc' AS std_citizenship_status_desc"),
                'c.c_code',
            ])
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('std.std_student_id', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'matric' => (string) ($r->std_student_id ?? ''),
            'name' => $r->std_student_name,
            'status' => $r->std_status_desc,
            'program' => $r->std_program,
            'intakeCase' => $r->std_intake_case_desc !== null && $r->std_intake_case_desc !== ''
                ? $r->std_intake_case_desc
                : $r->std_intake_case,
            'citizenship' => $r->std_citizenship_status_desc,
            'semesterNo' => $r->std_sem_level,
            'feeCode' => $r->c_code,
        ])->all();

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $total === 0 ? 1 : (int) ceil($total / $limit),
            'uniqueKey' => $uniqueKey,
            'message' => $message,
        ]);
    }

    /**
     * POST `/student-finance/invoice-generation/generate`.
     *
     * Calls `invoiceCreationByBatch(...)` then mirrors the legacy
     * `wf_task.wtk_task_url` rewrite so workflow approvers landing in
     * the legacy app keep working. Returns the SP's `mesg` and the
     * decoded `wout` JSON.
     */
    public function generate(GenerateStudentInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $uniqueKey = (string) $validated['unique_key'];
        $semester = (string) $validated['semester'];
        $programLevel = (string) $validated['program_level'];
        $feeType = (string) $validated['fee_type'];
        $studentType = (string) ($validated['student_type'] ?? '');
        $intakeCase = (string) ($validated['intake_case'] ?? '');
        $matricNo = (string) ($validated['matric_no'] ?? '');

        $username = $this->resolveUsername($request);
        $conn = DB::connection('mysql_secondary');

        try {
            $conn->statement(
                'CALL invoiceCreationByBatch(?, ?, ?, ?, ?, ?, ?, ?, @sf_mesg, @sf_wout)',
                [
                    $semester,
                    $programLevel,
                    $studentType,
                    $intakeCase,
                    $username,
                    $uniqueKey,
                    $feeType,
                    $matricNo,
                ]
            );
            $row = $conn->selectOne('SELECT @sf_mesg AS mesg, @sf_wout AS wout');
        } catch (Throwable $e) {
            return $this->sendError(
                500,
                'INTERNAL_ERROR',
                'Failed to invoke invoiceCreationByBatch.',
                ['reason' => strtok($e->getMessage(), "\n") ?: 'Stored procedure failed.']
            );
        }

        $message = is_object($row) ? (string) ($row->mesg ?? '') : '';
        $woutRaw = is_object($row) ? (string) ($row->wout ?? '') : '';
        $wout = $woutRaw !== '' ? json_decode($woutRaw, true) : null;

        $taskIds = [];
        if (is_array($wout)
            && (($wout['mesgCode'] ?? null) === 'Y')
            && isset($wout['taskListing'][0])
            && is_array($wout['taskListing'][0])
        ) {
            foreach ($wout['taskListing'][0] as $task) {
                if (! is_array($task) || empty($task['taskId'])) {
                    continue;
                }
                $taskId = (string) $task['taskId'];
                $taskIds[] = $taskId;
                try {
                    $batchRow = $conn->selectOne(
                        'SELECT wtk_application_id FROM wf_task WHERE wtk_task_id = ?',
                        [$taskId]
                    );
                    $batch = is_object($batchRow) ? (string) ($batchRow->wtk_application_id ?? '') : '';
                    // Legacy URL shape preserved verbatim — points at the
                    // legacy `index.php` page wrapper for menuID 1492. The
                    // CMS does not render this URL itself; it is only
                    // consumed by the legacy worklist UI.
                    $url = 'index.php?a='.$this->flcUrlEncode(
                        'page=page_wrapper&menuID=1492&taskId='.$taskId.'&batch='.$batch
                    );
                    DB::connection('mysql_secondary')
                        ->table('wf_task')
                        ->where('wtk_task_id', $taskId)
                        ->update(['wtk_task_url' => $url]);
                } catch (Throwable $e) {
                    // Best-effort URL rewrite — the SP has already
                    // committed the invoice rows. Log the failure but
                    // keep the success envelope so the user can
                    // continue.
                    $this->auditService->log(
                        'student.invoice-generation.task_url.failed',
                        $request->user(),
                        'WfTask',
                        null,
                        null,
                        ['taskId' => $taskId, 'reason' => strtok($e->getMessage(), "\n")]
                    );
                }
            }
        }

        $this->auditService->log(
            'student.invoice-generation.generated',
            $request->user(),
            'CustInvoiceMaster',
            null,
            null,
            [
                'uniqueKey' => $uniqueKey,
                'semester' => $semester,
                'programLevel' => $programLevel,
                'feeType' => $feeType,
                'taskIds' => $taskIds,
            ]
        );

        return $this->sendOk([
            'success' => is_array($wout) && (($wout['mesgCode'] ?? null) === 'Y'),
            'message' => $message,
            'workflow' => $wout,
            'taskIds' => $taskIds,
        ]);
    }

    /**
     * POST `/student-finance/invoice-generation/export/csv`.
     *
     * Mirrors the legacy `?csv=1&type=1` branch — wide column set
     * driven by `temp_stud_listing_match` joined with `student` and
     * (LEFT JOIN) `country` for the citizenship region.
     */
    public function exportCsv(ExportStudentInvoiceCsvRequest $request): StreamedResponse
    {
        $validated = $request->validated();
        $uniqueKey = (string) $validated['unique_key'];

        $rows = DB::connection('mysql_secondary')
            ->table('student as std')
            ->join('temp_stud_listing_match as tsl', function ($join) {
                $join->on(DB::raw($this->cs('std.std_student_id')), '=', DB::raw($this->cs('tsl.c_student_id')));
            })
            ->leftJoin('country as cny', function ($join) {
                $join->on(DB::raw($this->cs('cny.cny_country_code')), '=', DB::raw($this->cs('std.std_citizenship_country')));
            })
            ->where('tsl.c_unique_key', $uniqueKey)
            ->orderBy('tsl.c_code', 'desc')
            ->get([
                'std.std_student_id',
                'std.std_student_name',
                'std.std_status',
                'std.std_intake_semester',
                'std.std_intake_case',
                'std.std_mode_study',
                'std.std_citizenship_country',
                'std.std_citizenship_status',
                'std.std_income_group',
                'std.std_intake_session',
                'std.std_study_center',
                'std.std_program',
                'std.std_gs_code',
                'std.std_extended_field',
                'tsl.c_ptj',
                'tsl.c_semslevel',
                'tsl.c_code',
                'tsl.c_notes',
                DB::raw("cny.cny_extended_field->>'$.cny_region_desc' AS region_desc"),
            ]);

        $header = [
            'Semester' => (string) ($validated['semester_desc'] ?? ''),
            'Program' => (string) ($validated['program_level_desc'] ?? ''),
            'Student Type' => (string) ($validated['student_type_desc'] ?? ''),
            'Fee Type' => (string) ($validated['fee_type_desc'] ?? ''),
            'Intake Case' => (string) ($validated['intake_case_desc'] ?? ''),
        ];

        $columns = [
            'No',
            'Matric',
            'Nama',
            'Status',
            'Program Level',
            'Intake Semester',
            'Intake Case',
            'Mode Study',
            'Region',
            'Citizenship Status',
            'Citizenship Country',
            'Income Group',
            'Intake Session',
            'Study Center',
            'Program',
            'Faculty',
            'GS',
            'Semester No',
            'Fee Structure',
        ];

        $body = $rows->values()->map(function ($r, int $i) {
            $ext = $r->std_extended_field ?? null;
            $extDecoded = is_string($ext) ? json_decode($ext, true) : null;
            $extGet = function (string $key) use ($extDecoded) {
                if (! is_array($extDecoded)) {
                    return '';
                }

                return (string) ($extDecoded[$key] ?? '');
            };
            $combine = function (?string $code, string $desc): string {
                $code = (string) ($code ?? '');
                if ($code === '' && $desc === '') {
                    return '';
                }
                if ($code === '') {
                    return $desc;
                }
                if ($desc === '') {
                    return $code;
                }

                return $code.' - '.$desc;
            };

            return [
                (string) ($i + 1),
                (string) ($r->std_student_id ?? ''),
                (string) ($r->std_student_name ?? ''),
                $combine($r->std_status, $extGet('std_status_desc')),
                $combine(null, $extGet('std_program_level_desc')),
                (string) ($r->std_intake_semester ?? ''),
                $combine($r->std_intake_case, $extGet('std_intake_case_desc')),
                $combine($r->std_mode_study, $extGet('std_mode_study_desc')),
                (string) ($r->region_desc ?? ''),
                $combine($r->std_citizenship_status, $extGet('std_citizenship_status_desc')),
                $combine($r->std_citizenship_country, $extGet('std_citizenship_country_desc')),
                $combine($r->std_income_group, $extGet('std_income_group_desc')),
                $combine($r->std_intake_session, $extGet('std_intake_condition_desc')),
                $combine($r->std_study_center, $extGet('std_study_center_desc')),
                $combine($r->std_program, $extGet('std_program_desc')),
                (string) ($r->c_ptj ?? ''),
                (string) ($r->std_gs_code ?? ''),
                (string) ($r->c_semslevel ?? ''),
                (string) ($r->c_code !== null && $r->c_code !== ''
                    ? $r->c_code
                    : ($r->c_notes ?? '')),
            ];
        })->all();

        return $this->streamCsv('Generate Student Invoice', $columns, $body, $header);
    }

    /**
     * POST `/student-finance/invoice-generation/export/match-csv`.
     *
     * Mirrors the legacy `?match=1&type=1` branch — post-generate
     * match data taken from `cust_invoice_master` joined to
     * `student`, scoped by the same `uniqueKey` that
     * `invoiceCreationByBatch` stamped onto the new invoice rows
     * (`cim_unique_key`).
     */
    public function exportMatchCsv(ExportStudentInvoiceCsvRequest $request): StreamedResponse
    {
        $validated = $request->validated();
        $uniqueKey = (string) $validated['unique_key'];

        $batchRow = DB::connection('mysql_secondary')
            ->table('cust_invoice_master')
            ->where('cim_unique_key', $uniqueKey)
            ->orderBy('cim_cust_invoice_id', 'asc')
            ->first(['cim_batch_no']);
        $batchNo = $batchRow && isset($batchRow->cim_batch_no)
            ? (string) $batchRow->cim_batch_no
            : '';

        $rows = DB::connection('mysql_secondary')
            ->table('cust_invoice_master as cim')
            ->join('student as std', function ($join) {
                $join->on(DB::raw($this->cs('cim.cim_cust_id')), '=', DB::raw($this->cs('std.std_student_id')));
            })
            ->where('cim.cim_unique_key', $uniqueKey)
            ->distinct()
            ->orderBy('cim.cim_cust_invoice_id', 'asc')
            ->get([
                'cim.cim_cust_invoice_id',
                'cim.cim_invoice_no',
                'cim.cim_invoice_date',
                'cim.cim_cust_id',
                'cim.cim_cust_name',
                'cim.cim_semester_id',
                'cim.cim_extended_field',
                'cim.cim_nett_amt',
                'std.std_faculty_code',
                'std.std_gs_code',
            ]);

        $header = [
            'Batch No' => $batchNo,
            'Semester' => (string) ($validated['semester_desc'] ?? ''),
            'Program' => (string) ($validated['program_level_desc'] ?? ''),
            'Student Type' => (string) ($validated['student_type_desc'] ?? ''),
            'Intake Case' => (string) ($validated['intake_case_desc'] ?? ''),
        ];

        $columns = [
            'ID',
            'InvoiceNo',
            'InvoiceDate',
            'CustomerId',
            'CustomerName',
            'semester',
            'feeStructure',
            'PP',
            'GS',
            'Amt',
        ];

        $body = $rows->values()->map(function ($r) {
            $ext = $r->cim_extended_field ?? null;
            $extDecoded = is_string($ext) ? json_decode($ext, true) : null;
            $feeCode = is_array($extDecoded) ? (string) ($extDecoded['fee_code'] ?? '') : '';
            $invoiceDate = $r->cim_invoice_date
                ? date('d/m/Y', strtotime((string) $r->cim_invoice_date))
                : '';
            $netAmt = is_numeric($r->cim_nett_amt)
                ? number_format((float) $r->cim_nett_amt, 2, '.', ',')
                : '';

            return [
                (string) ($r->cim_cust_invoice_id ?? ''),
                (string) ($r->cim_invoice_no ?? ''),
                $invoiceDate,
                (string) ($r->cim_cust_id ?? ''),
                (string) ($r->cim_cust_name ?? ''),
                (string) ($r->cim_semester_id ?? ''),
                $feeCode,
                (string) ($r->std_faculty_code ?? ''),
                (string) ($r->std_gs_code ?? ''),
                $netAmt,
            ];
        })->all();

        return $this->streamCsv('Generate Match Data', $columns, $body, $header);
    }

    /**
     * Build the joined query that powers the listing. Mirrors the
     * legacy `temp_stud_listing_match` JOIN pattern — only rows with
     * a non-null `c_code` and matching `c_unique_key` are returned,
     * exactly like the legacy BL.
     */
    private function rosterQuery(string $uniqueKey)
    {
        return DB::connection('mysql_secondary')
            ->table('student as std')
            ->join('temp_stud_listing_match as c', function ($join) {
                $join->on(DB::raw($this->cs('std.std_student_id')), '=', DB::raw($this->cs('c.c_student_id')));
            })
            ->whereNotNull('c.c_code')
            ->where('c.c_unique_key', $uniqueKey);
    }

    /**
     * Run `invoiceCheckingByBatch(...)` and return its `wout` message.
     */
    private function callInvoiceCheckingByBatch(
        string $semester,
        string $programLevel,
        string $studentType,
        string $intakeCase,
        string $username,
        string $uniqueKey,
        string $feeType,
        string $matricNo,
    ): string {
        $conn = DB::connection('mysql_secondary');
        $conn->statement(
            'CALL invoiceCheckingByBatch(?, ?, ?, ?, ?, ?, ?, ?, @sf_mesg, @sf_wout)',
            [
                $semester,
                $programLevel,
                $studentType,
                $intakeCase,
                $username,
                $uniqueKey,
                $feeType,
                $matricNo,
            ]
        );
        $row = $conn->selectOne('SELECT @sf_wout AS message');

        return is_object($row) ? (string) ($row->message ?? '') : '';
    }

    private function lookupOptions(
        string $codeName,
        bool $withDescription = false,
        bool $activeOnly = false,
        string $sortBy = 'lde_value',
    ) {
        $query = LookupDetail::query()->where('lma_code_name', $codeName);
        if ($activeOnly) {
            $query->where('lde_status', '1');
        }
        $query->orderBy($sortBy);

        return $query->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => (string) $r->lde_value,
                'label' => $withDescription && $r->lde_description
                    ? $r->lde_value.' - '.$r->lde_description
                    : (string) ($r->lde_description !== null && $r->lde_description !== ''
                        ? $r->lde_description
                        : $r->lde_value),
            ])
            ->values()
            ->all();
    }

    private function resolveUsername(Request $request): string
    {
        $user = $request->user();
        if (! $user) {
            return 'system';
        }

        // Prefer explicit username column when present, fall back to
        // name and finally email — same precedence as
        // ProjectMonitoringController::saveBalance.
        return (string) ($user->username ?? $user->name ?? $user->email ?? 'system');
    }

    /**
     * Mirror legacy `flc_url_encode($s)` — base64-encode, then run
     * URL-safe character replacements. The legacy routine adds
     * single-character substitutions on top of base64 to keep the
     * resulting URL safe inside FIMS' query-string router.
     */
    private function flcUrlEncode(string $value): string
    {
        $encoded = base64_encode($value);

        return strtr($encoded, ['+' => '-', '/' => '_', '=' => ',']);
    }

    /**
     * Escape `%`, `_`, `\` for safe LIKE pattern matching, then wrap
     * with `%...%` for substring search.
     */
    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }

    /**
     * Stream a CSV download with the legacy `header => value` ribbon
     * row(s) above the column headers.
     *
     * @param  array<string, string>  $headerMeta
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, string>>  $rows
     */
    private function streamCsv(
        string $filename,
        array $columns,
        array $rows,
        array $headerMeta = [],
    ): StreamedResponse {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $filename) ?: 'export';
        $filenameWithExt = $safeName.'.csv';

        return response()->stream(
            function () use ($columns, $rows, $headerMeta) {
                $out = fopen('php://output', 'w');
                // UTF-8 BOM so Excel renders accented characters correctly.
                fwrite($out, "\xEF\xBB\xBF");
                foreach ($headerMeta as $key => $value) {
                    fputcsv($out, [(string) $key, (string) $value]);
                }
                if (! empty($headerMeta)) {
                    fputcsv($out, []);
                }
                fputcsv($out, $columns);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            },
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filenameWithExt.'"',
            ],
        );
    }
}
