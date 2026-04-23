<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\ManualJournalMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger > Manual Journal Listing (PAGEID 1729 / MENUID 2089).
 *
 * Legacy BL: `V2_GL_JOURNAL_API` endpoints:
 *   - ?listing=1         -> GET  /api/general-ledger/manual-journal   (index)
 *   - ?listing_delete=1  -> DELETE /api/general-ledger/manual-journal/{id}
 *   - options()          -> GET  /api/general-ledger/manual-journal/options
 *
 * Legacy PDF reporters under `custom/report/Manual Journal/`:
 *   - downloadListPDF.php  -> GET /api/general-ledger/manual-journal/listing-pdf
 *     (returns all rows for the given filters, no pagination). The client
 *     renders the PDF via jsPDF to match the legacy landscape layout.
 *   - downloadPDFmj.php    -> GET /api/general-ledger/manual-journal/{id}
 *     (returns header + GL lines + workflow signers for a single journal,
 *     again with client-side jsPDF rendering).
 *
 * Fixed filters (mirrored from legacy):
 *   - mjm_system_id IN ('MNL', 'MNL_UNIDENTIFIED', 'MNL_INVEST')
 *   - mjm_typeofjournal = ?  (REQUIRED — legacy returns [] if missing)
 *
 * Smart-filter fields:
 *   - mjm_enterdate_from / mjm_enterdate_to (dd/mm/yyyy strings, inclusive)
 *   - mjm_status        (exact match)
 *   - mjm_total_amt_from / mjm_total_amt_to (numeric, thousands stripped)
 *
 * Global search (q): mjm_journal_no, mjm_journal_desc, mjm_status,
 * createdby — the exact CONCAT_WS('__', ...) set from the legacy BL.
 *
 * Delete: legacy BL only deletes when mjm_status = 'DRAFT'; enforced here.
 *
 * Edit / View / Duplicate row actions are still deferred because MENUID 2090
 * (Manual Journal Form) has not been migrated yet.
 *
 * The Type-of-Journal Top-Filter source is hard-coded in the legacy
 * `Form_Item_default` HTML (not a SQL lookup): General / InterOU /
 * Intercompany. options() returns those fixed values plus distinct statuses
 * pulled via Eloquent from manual_journal_master.
 */
class ManualJournalListingController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'mjm_journal_no',
        'mjm_journal_desc',
        'mjm_total_amt',
        'mjm_status',
        'mjm_enterdate',
        'createdby',
        'createddate',
    ];

    /**
     * Fixed Type-of-Journal values shipped on the legacy page as plain
     * `<option>` HTML inside Form_Item_default of COMPONENTID 5104.
     * Keys are the underlying `mjm_typeofjournal` codes; values are the
     * display labels the legacy dropdown renders.
     */
    private const JOURNAL_TYPES = [
        'General' => 'General',
        'InterOU' => 'Interou',
        'Intercompany' => 'Intercompany',
    ];

    private const SYSTEM_IDS = ['MNL', 'MNL_UNIDENTIFIED', 'MNL_INVEST'];

    /**
     * Safety cap on the "download all" PDF endpoint to keep a single request
     * bounded even if a user widens the filter too aggressively. Matches the
     * biggest listings the legacy page was seen to render without timing out.
     */
    private const PDF_ROW_LIMIT = 5000;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));

        $sortBy = (string) $request->input('sort_by', 'createddate');
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'createddate';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $typeOfJournal = trim((string) $request->input('mjm_typeofjournal', ''));

        // Legacy BL: `if(!$_GET['mjm_typeofjournal']) return [];` — returning an
        // empty page (with valid meta) preserves that contract without forcing
        // the caller to handle a 4xx.
        if ($typeOfJournal === '' || ! array_key_exists($typeOfJournal, self::JOURNAL_TYPES)) {
            return $this->sendOk([], [
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'totalPages' => 0,
            ]);
        }

        $query = $this->buildFilteredQuery($request, $typeOfJournal);

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('mjm_journal_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (ManualJournalMaster $r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'mjmJournalId' => (int) $r->mjm_journal_id,
                'journalNo' => $r->mjm_journal_no,
                'description' => $r->mjm_journal_desc,
                'typeOfJournal' => $r->mjm_typeofjournal,
                'amount' => $r->mjm_total_amt !== null ? (float) $r->mjm_total_amt : null,
                'status' => $r->mjm_status,
                'systemId' => $r->mjm_system_id,
                'dateJournal' => $r->mjm_enterdate
                    ? Carbon::parse($r->mjm_enterdate)->format('d/m/Y')
                    : null,
                'createdBy' => $r->createdby,
                'createdDate' => $r->createddate
                    ? Carbon::parse($r->createddate)->format('d/m/Y H:i')
                    : null,
                'wasNotes' => null,
            ];
        });

        // Fetch workflow rejection notes for REJECT rows only — mirrors the
        // legacy per-row SELECT against wf_application_status.
        $rejectIds = $rows
            ->where('mjm_status', 'REJECT')
            ->pluck('mjm_journal_id')
            ->filter()
            ->values()
            ->all();
        $rejectNos = $rows
            ->where('mjm_status', 'REJECT')
            ->pluck('mjm_journal_no')
            ->filter()
            ->values()
            ->all();

        if (! empty($rejectIds) || ! empty($rejectNos)) {
            $notes = DB::connection('mysql_secondary')
                ->table('wf_application_status')
                ->where('was_status', 'REJECT')
                ->where(function ($b) use ($rejectIds, $rejectNos) {
                    if (! empty($rejectIds)) {
                        $b->orWhereIn('was_application_id', $rejectIds);
                    }
                    if (! empty($rejectNos)) {
                        $b->orWhereIn('was_application_id', $rejectNos);
                    }
                })
                ->pluck('was_notes', 'was_application_id');

            $data = $data->map(function ($row) use ($notes) {
                if (($row['status'] ?? null) !== 'REJECT') {
                    return $row;
                }
                $note = $notes[$row['mjmJournalId']] ?? $notes[$row['journalNo']] ?? null;
                $row['wasNotes'] = $note;

                return $row;
            });
        }

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $master = ManualJournalMaster::query()->find($id);
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Manual journal not found');
        }

        // Mirror legacy gate: delete only if mjm_status = 'DRAFT'.
        if (strtoupper((string) ($master->mjm_status ?? '')) !== 'DRAFT') {
            return $this->sendError(
                409,
                'JOURNAL_NOT_DELETABLE',
                'Only DRAFT manual journals can be deleted.',
            );
        }

        $master->delete();

        return $this->sendOk(['success' => true]);
    }

    public function options(): JsonResponse
    {
        $types = array_map(
            fn (string $code, string $label) => ['code' => $code, 'label' => $label],
            array_keys(self::JOURNAL_TYPES),
            array_values(self::JOURNAL_TYPES),
        );

        $statuses = ManualJournalMaster::query()
            ->whereIn('mjm_system_id', self::SYSTEM_IDS)
            ->whereNotNull('mjm_status')
            ->where('mjm_status', '!=', '')
            ->distinct()
            ->orderBy('mjm_status')
            ->pluck('mjm_status')
            ->values()
            ->all();

        return $this->sendOk([
            'types' => $types,
            'statuses' => array_values(array_map('strval', $statuses)),
        ]);
    }

    /**
     * Return ALL rows matching the listing filters for the PDF toolbar
     * button. Mirrors `custom/report/Manual Journal/downloadListPDF.php`: no
     * pagination, same filter contract as index(). Capped at PDF_ROW_LIMIT
     * for safety — that legacy PHP reporter would simply time out on very
     * wide filters; refusing beyond the cap is safer than silently
     * truncating.
     */
    public function listingForPdf(Request $request): JsonResponse
    {
        $typeOfJournal = trim((string) $request->input('mjm_typeofjournal', ''));
        if ($typeOfJournal === '' || ! array_key_exists($typeOfJournal, self::JOURNAL_TYPES)) {
            return $this->sendOk([
                'rows' => [],
                'filters' => $this->echoFilters($request, $typeOfJournal),
                'generatedAt' => Carbon::now()->format('d/m/Y H:i:s'),
                'typeLabel' => '',
            ]);
        }

        $query = $this->buildFilteredQuery($request, $typeOfJournal);

        $sortBy = (string) $request->input('sort_by', 'createddate');
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'createddate';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->orderBy('mjm_journal_id', 'desc')
            ->limit(self::PDF_ROW_LIMIT)
            ->get();

        $truncated = $rows->count() >= self::PDF_ROW_LIMIT;

        $data = $rows->values()->map(function (ManualJournalMaster $r, int $i) {
            return [
                'index' => $i + 1,
                'journalNo' => (string) ($r->mjm_journal_no ?? ''),
                'description' => (string) ($r->mjm_journal_desc ?? ''),
                'amount' => $r->mjm_total_amt !== null ? (float) $r->mjm_total_amt : 0.0,
                'status' => (string) ($r->mjm_status ?? ''),
                'dateJournal' => $r->mjm_enterdate
                    ? Carbon::parse($r->mjm_enterdate)->format('d/m/Y')
                    : '',
                'createdBy' => (string) ($r->createdby ?? ''),
            ];
        })->all();

        return $this->sendOk([
            'rows' => $data,
            'filters' => $this->echoFilters($request, $typeOfJournal),
            'generatedAt' => Carbon::now()->format('d/m/Y H:i:s'),
            'typeLabel' => self::JOURNAL_TYPES[$typeOfJournal],
            'truncated' => $truncated,
            'limit' => self::PDF_ROW_LIMIT,
        ]);
    }

    /**
     * Per-row detail endpoint backing the legacy
     * `custom/report/Manual Journal/downloadPDFmj.php` report. Returns the
     * journal header, GL lines (with account description and derived
     * `glStructure`), totals, and workflow signers (Input / Verifier /
     * Approver) derived from `wf_application_status` + `wf_process` +
     * `staff_service`, falling back to the "system" generator when the
     * journal was posted automatically (mirrors the `$createby ? ... : ...`
     * branches in the legacy PHP).
     */
    public function show(int $id): JsonResponse
    {
        $master = ManualJournalMaster::query()->find($id);
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Manual journal not found');
        }

        $conn = DB::connection('mysql_secondary');
        $journalNo = (string) ($master->mjm_journal_no ?? '');
        $journalId = (int) $master->mjm_journal_id;

        // Lines: join manual_journal_details -> account_main for acct_desc.
        // `manual_journal_details.mjm_journal_no` (int) stores the parent's
        // `mjm_journal_id` — see ManualJournalDetl model docblock. Legacy
        // ORDER BY preserved.
        $lineRows = $conn
            ->table('manual_journal_details as mjd')
            ->leftJoin('account_main as am', 'mjd.acm_acct_code', '=', 'am.acm_acct_code')
            ->where('mjd.mjm_journal_no', $journalId)
            ->orderBy('mjd.mjd_document_no')
            ->orderBy('mjd.mjd_payto_id')
            ->orderByRaw(
                "CONCAT_WS('-',"
                ."IFNULL(mjd.fty_fund_type,''),"
                ."IFNULL(mjd.at_activity_code,''),"
                ."IFNULL(mjd.oun_code,''),"
                ."IFNULL(mjd.ccr_costcentre,''),"
                ."IFNULL(mjd.code_so,''))"
            )
            ->orderBy('mjd.mjd_trans_amt')
            ->orderBy('mjd.mjd_trans_type', 'desc')
            ->get([
                'mjd.fty_fund_type',
                'mjd.at_activity_code',
                'mjd.oun_code',
                'mjd.ccr_costcentre',
                'mjd.code_so',
                'mjd.acm_acct_code',
                'am.acm_acct_desc',
                'mjd.mjd_payto_type',
                'mjd.mjd_payto_id',
                'mjd.mjd_document_no',
                'mjd.mjd_reference',
                'mjd.mjd_reference_2',
                'mjd.mjd_trans_amt',
                'mjd.mjd_trans_type',
            ]);

        $lines = [];
        $totalDt = 0.0;
        $totalCr = 0.0;
        foreach ($lineRows as $row) {
            $amount = (float) ($row->mjd_trans_amt ?? 0);
            $isDebit = strtoupper((string) ($row->mjd_trans_type ?? '')) === 'DT';
            if ($isDebit) {
                $totalDt += $amount;
            } else {
                $totalCr += $amount;
            }
            $glStructure = implode('-', array_map(
                static fn ($v) => (string) ($v ?? ''),
                [
                    $row->fty_fund_type,
                    $row->at_activity_code,
                    $row->oun_code,
                    $row->ccr_costcentre,
                    $row->code_so,
                ],
            ));
            // Legacy rule: mjd_reference IS NULL -> fall back to mjd_reference_2.
            $ref = $row->mjd_reference !== null && $row->mjd_reference !== ''
                ? (string) $row->mjd_reference
                : (string) ($row->mjd_reference_2 ?? '');

            $accountCode = (string) ($row->acm_acct_code ?? '');
            $accountLabel = $accountCode;
            if (filled($row->acm_acct_desc)) {
                $accountLabel .= ' - '.(string) $row->acm_acct_desc;
            }

            $lines[] = [
                'glStructure' => $glStructure,
                'accountCode' => $accountLabel,
                'payToType' => (string) ($row->mjd_payto_type ?? ''),
                'payToId' => (string) ($row->mjd_payto_id ?? ''),
                'documentNo' => (string) ($row->mjd_document_no ?? ''),
                'reference' => $ref,
                'debit' => $isDebit ? $amount : 0.0,
                'credit' => $isDebit ? 0.0 : $amount,
            ];
        }

        // Creator staff name (legacy: LEFT JOIN staff ST ON stf_ad_username = createdby).
        $creatorStaffName = null;
        if (filled($master->createdby)) {
            $creatorStaffName = (string) ($conn->table('staff')
                ->where('stf_ad_username', $master->createdby)
                ->value('stf_staff_name') ?? '');
        }

        // Workflow code lookup (legacy excludes ACK variants).
        $workflowCode = (string) ($conn->table('wf_application_status')
            ->whereIn('was_application_id', array_filter([$journalNo, (string) $journalId], 'strlen'))
            ->where('was_workflow_code', 'not like', '%ACK%')
            ->distinct()
            ->value('was_workflow_code') ?? '');

        // Guard matching legacy `mjm_approveby != 'system_ID' OR IS NULL`
        // — determines whether to render signatories at all.
        $hasHumanApprover = (bool) $conn->table('manual_journal_master')
            ->where('mjm_journal_no', $journalNo)
            ->where(function ($b) {
                $b->where('mjm_approveby', '!=', 'system_ID')->orWhereNull('mjm_approveby');
            })
            ->exists();

        $processFlow = [];
        if ($hasHumanApprover && $workflowCode !== '') {
            // Legacy uses `was_application_id` = the string journal_no for the
            // current workflow path. Sub-selects on sts_job_flag=1 mirror the
            // primary service row. `staff_service.sts_oun_code` concatenated
            // with sts_extended_field->'$.sts_oun_desc' for the unit label.
            $rows = $conn
                ->table('wf_process as wfp')
                ->leftJoin('wf_application_status as was', function ($j) use ($journalNo) {
                    $j->on('wfp.wfp_process_id', '=', 'was.was_process_id')
                        ->where('was.was_application_id', '=', $journalNo);
                })
                ->leftJoin('staff as stf', 'was.createdby', '=', 'stf.stf_ad_username')
                ->leftJoin('staff_service as ss', function ($j) {
                    $j->on('ss.stf_staff_id', '=', 'stf.stf_staff_id')
                        ->where('ss.sts_job_flag', '=', 1);
                })
                ->where('wfp.wfp_workflow_code', $workflowCode)
                ->orderBy('wfp.wfp_sequence')
                ->orderBy('was.createddate')
                ->get([
                    'wfp.wfp_process_name',
                    'was.was_extended_field',
                    'was.was_notes',
                    'was.createddate as was_createddate',
                    'ss.sts_oun_code',
                    'ss.sts_extended_field',
                    'stf.stf_email_addr',
                    'stf.stf_telno_work',
                ]);

            foreach ($rows as $r) {
                $wasExt = $this->decodeJson($r->was_extended_field);
                $stsExt = $this->decodeJson($r->sts_extended_field);

                $ounCode = (string) ($r->sts_oun_code ?? '');
                $ounDesc = (string) ($stsExt['sts_oun_desc'] ?? '');
                $ounLabel = trim($ounCode.($ounDesc !== '' ? '-'.$ounDesc : ''), '-');

                $createdBy = (string) ($wasExt['createdby_name'] ?? '');
                $statusDesc = strtoupper((string) ($wasExt['was_status_desc'] ?? ''));

                $createdAt = $r->was_createddate
                    ? Carbon::parse($r->was_createddate)
                    : null;

                $processFlow[] = [
                    'processName' => (string) ($r->wfp_process_name ?? ''),
                    'createdByName' => $createdBy,
                    'ounDesc' => $ounLabel,
                    'emailAddr' => (string) ($r->stf_email_addr ?? ''),
                    'telNoWork' => (string) ($r->stf_telno_work ?? ''),
                    'statusDesc' => $statusDesc,
                    'remark' => (string) ($r->was_notes ?? ''),
                    'createdDate' => $createdAt?->format('d/m/Y') ?? '',
                    'createdTime' => $createdAt?->format('h:i A') ?? '',
                ];
            }
        }

        // Organisation header (legacy: `SELECT org_desc FROM organization WHERE org_status = 1`).
        $organization = (string) ($conn->table('organization')
            ->where('org_status', 1)
            ->value('org_desc') ?? '');

        $header = [
            'journalId' => $journalId,
            'journalNo' => $journalNo,
            'journalDesc' => (string) ($master->mjm_journal_desc ?? ''),
            'typeOfJournal' => (string) ($master->mjm_typeofjournal ?? ''),
            'status' => (string) ($master->mjm_status ?? ''),
            'systemId' => (string) ($master->mjm_system_id ?? ''),
            'enterDate' => $master->mjm_enterdate
                ? Carbon::parse($master->mjm_enterdate)->format('d/m/Y')
                : '',
            'enterMonth' => $master->mjm_enterdate
                ? Carbon::parse($master->mjm_enterdate)->format('m/Y')
                : '',
            'createdBy' => (string) ($master->createdby ?? ''),
            'createdByName' => $creatorStaffName,
            'createdDate' => $master->createddate
                ? Carbon::parse($master->createddate)->format('d/m/Y')
                : '',
            'createdTime' => $master->createddate
                ? Carbon::parse($master->createddate)->format('h:i A')
                : '',
            'organization' => $organization,
            'hasHumanApprover' => $hasHumanApprover,
        ];

        return $this->sendOk([
            'header' => $header,
            'lines' => $lines,
            'totals' => [
                'debit' => round($totalDt, 2),
                'credit' => round($totalCr, 2),
            ],
            'processFlow' => $processFlow,
        ]);
    }

    /**
     * Build a filtered query against manual_journal_master for the given
     * Type-of-Journal. Shared by index() and listingForPdf() so the two
     * endpoints never drift apart.
     *
     * @return \Illuminate\Database\Eloquent\Builder<ManualJournalMaster>
     */
    private function buildFilteredQuery(Request $request, string $typeOfJournal)
    {
        $q = trim((string) $request->input('q', ''));
        $dateFrom = trim((string) $request->input('mjm_enterdate_from', ''));
        $dateTo = trim((string) $request->input('mjm_enterdate_to', ''));
        $status = trim((string) $request->input('mjm_status', ''));
        $amtFrom = trim((string) $request->input('mjm_total_amt_from', ''));
        $amtTo = trim((string) $request->input('mjm_total_amt_to', ''));

        $query = ManualJournalMaster::query()
            ->whereIn('mjm_system_id', self::SYSTEM_IDS)
            ->where('mjm_typeofjournal', $typeOfJournal);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(mjm_journal_no, ''),
                    IFNULL(mjm_journal_desc, ''),
                    IFNULL(mjm_status, ''),
                    IFNULL(createdby, '')
                )) LIKE ?",
                [$like]
            );
        }

        if ($dateFrom !== '' && $this->parseDmyDate($dateFrom) !== null) {
            $query->where('mjm_enterdate', '>=', $this->parseDmyDate($dateFrom).' 00:00:00');
        }
        if ($dateTo !== '' && $this->parseDmyDate($dateTo) !== null) {
            $query->where('mjm_enterdate', '<=', $this->parseDmyDate($dateTo).' 23:59:59');
        }
        if ($status !== '') {
            $query->where('mjm_status', $status);
        }

        $amtFromFloat = $this->parseAmount($amtFrom);
        $amtToFloat = $this->parseAmount($amtTo);
        if ($amtFromFloat !== null) {
            $query->where('mjm_total_amt', '>=', $amtFromFloat);
        }
        if ($amtToFloat !== null) {
            $query->where('mjm_total_amt', '<=', $amtToFloat);
        }

        return $query;
    }

    /**
     * Echo the filter criteria back in the PDF payload so the legacy
     * "FILTERED CRITERIA" section can be rendered client-side.
     *
     * @return array<string, string>
     */
    private function echoFilters(Request $request, string $typeOfJournal): array
    {
        return [
            'typeOfJournal' => self::JOURNAL_TYPES[$typeOfJournal] ?? '',
            'globalSearch' => trim((string) $request->input('q', '')),
            'dateFrom' => trim((string) $request->input('mjm_enterdate_from', '')),
            'dateTo' => trim((string) $request->input('mjm_enterdate_to', '')),
            'status' => trim((string) $request->input('mjm_status', '')),
            'amountFrom' => trim((string) $request->input('mjm_total_amt_from', '')),
            'amountTo' => trim((string) $request->input('mjm_total_amt_to', '')),
        ];
    }

    /**
     * Decode a mysql JSON column (string in legacy, array after cast in
     * Eloquent) without throwing — returns [] on anything non-array.
     *
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function parseDmyDate(string $value): ?string
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmount(string $value): ?float
    {
        if ($value === '') {
            return null;
        }
        $clean = str_replace([','], '', $value);
        if (! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
