<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Student Finance > Student Profile or Ledger (PAGEID 1232 / MENUID 1509).
 *
 * Source: FIMS BL `V2_SFSP_LEDGER_API` (datatable listing via ?listing=1;
 * CSV export via ?download=1). Reads from the single `student` table in
 * DB_SECOND_DATABASE. Program Level / Status descriptions are pulled from
 * the `std_extended_field` JSON column (same keys the legacy SQL uses
 * via `std_extended_field->>'$.std_program_level_desc'` and
 * `std_extended_field->>'$.std_status_desc'`).
 *
 * Smart filters (legacy `smartFilter` keys are kept intact):
 *   - std_student_id (Matric)  — exact match
 *   - std_student_name (Name)  — LIKE %...%
 *   - ic_passport (NRIC/Passport) — exact match against
 *     IFNULL(IF(std_ic_no='', NULL, std_ic_no), std_passport)
 *   - std_program_level — exact match
 *   - std_sem_level — exact match
 *   - std_status_desc[] — IN (...), compared as UPPER against
 *     std_extended_field->>'$.std_status_desc'
 *
 * The "View Profile" / "View Ledger" legacy deep-links point at
 * menuID=1512 and api/V2_SFSP_LEDGER_VIEWPROFILE_API which are NOT in the
 * migrated menu yet; the frontend renders those buttons disabled until
 * those editors are migrated.
 */
class LedgerController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'std_student_id',
        'std_student_name',
        'ic_passport',
        'std_sem_level',
        'std_program_level',
        'std_status_desc',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'std_student_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'std_student_id';
        }

        $studentId = trim((string) $request->input('std_student_id', ''));
        $studentName = trim((string) $request->input('std_student_name', ''));
        $icPassport = trim((string) $request->input('ic_passport', ''));
        $programLevel = trim((string) $request->input('std_program_level', ''));
        $semLevel = trim((string) $request->input('std_sem_level', ''));
        // std_status_desc may arrive as array (?std_status_desc[]=ACTIVE) or CSV.
        $statusRaw = $request->input('std_status_desc', []);
        if (is_string($statusRaw)) {
            $statusRaw = array_filter(array_map('trim', explode(',', $statusRaw)));
        }
        $statusList = is_array($statusRaw)
            ? array_values(array_filter(array_map(
                fn ($v) => mb_strtoupper(trim((string) $v), 'UTF-8'),
                $statusRaw
            )))
            : [];

        $query = Student::query();

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(std_student_id, ''),
                    IFNULL(std_student_name, ''),
                    IFNULL(IF(std_ic_no='', NULL, std_ic_no), IFNULL(std_passport, '')),
                    IFNULL(std_sem_level, ''),
                    IFNULL(std_program_level, ''),
                    IFNULL(std_extended_field->>'$.std_program_level_desc', ''),
                    IFNULL(std_extended_field->>'$.std_status_desc', '')
                )) LIKE ?",
                [$like]
            );
        }

        if ($studentId !== '') {
            $query->where('std_student_id', $studentId);
        }

        if ($studentName !== '') {
            $query->where('std_student_name', 'like',
                '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $studentName).'%');
        }

        if ($icPassport !== '') {
            $query->whereRaw(
                "IFNULL(IF(std_ic_no='', NULL, std_ic_no), std_passport) = ?",
                [$icPassport]
            );
        }

        if ($programLevel !== '') {
            $query->where('std_program_level', $programLevel);
        }

        if ($semLevel !== '') {
            $query->where('std_sem_level', $semLevel);
        }

        if (! empty($statusList)) {
            $placeholders = implode(',', array_fill(0, count($statusList), '?'));
            $query->whereRaw(
                "UPPER(std_extended_field->>'$.std_status_desc') IN ($placeholders)",
                $statusList,
            );
        }

        $total = (clone $query)->count();

        $orderColumn = match ($sortBy) {
            'ic_passport' => DB::raw("IFNULL(IF(std_ic_no='', NULL, std_ic_no), std_passport)"),
            'std_status_desc' => DB::raw("std_extended_field->>'$.std_status_desc'"),
            default => $sortBy,
        };

        $rows = $query
            ->select([
                'std_student_id',
                'std_student_name',
                'std_ic_no',
                'std_passport',
                'std_sem_level',
                'std_program_level',
                'std_outstanding_amt',
                DB::raw("std_extended_field->>'\$.std_program_level_desc' as program_level_desc"),
                DB::raw("std_extended_field->>'\$.std_status_desc' as status_desc"),
            ])
            ->orderBy($orderColumn, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $icOrPassport = $r->std_ic_no !== null && $r->std_ic_no !== ''
                ? $r->std_ic_no
                : ($r->std_passport ?? null);

            $programLevelLabel = $r->std_program_level
                ? ($r->program_level_desc
                    ? $r->std_program_level.'-'.$r->program_level_desc
                    : $r->std_program_level)
                : ($r->program_level_desc ?: null);

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'studentId' => (string) $r->std_student_id,
                'studentName' => $r->std_student_name,
                'icPassport' => $icOrPassport,
                'semLevel' => $r->std_sem_level,
                'programLevel' => $r->std_program_level,
                'programLevelLabel' => $programLevelLabel,
                'statusDesc' => $r->status_desc ?? '',
                'outstandingAmt' => $r->std_outstanding_amt !== null
                    ? (float) $r->std_outstanding_amt
                    : 0.0,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    /**
     * Smart filter dropdown values.
     *
     * - programLevel is sourced from distinct `std_program_level` values on
     *   the `student` table (DB2 scope), mirroring the legacy lookup
     *   `SELECT lde_value, lde_description FROM lookup_details WHERE
     *   lma_code_name = 'PROGRAM_LEVEL'` — we use the live student distinct
     *   values so the filter stays in sync with data that actually exists.
     * - status is distinct UPPER(std_extended_field->>'$.std_status_desc'),
     *   matching the legacy dropdown exactly.
     */
    public function options(): JsonResponse
    {
        $programLevel = Student::query()
            ->select([
                'std_program_level as id',
                DB::raw("MAX(std_extended_field->>'\$.std_program_level_desc') as label_desc"),
            ])
            ->whereNotNull('std_program_level')
            ->where('std_program_level', '!=', '')
            ->groupBy('std_program_level')
            ->orderBy('std_program_level')
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'label' => $r->label_desc ? $r->id.' - '.$r->label_desc : (string) $r->id,
            ])
            ->values();

        $status = Student::query()
            ->selectRaw("DISTINCT UPPER(std_extended_field->>'\$.std_status_desc') as status_desc")
            ->whereRaw("std_extended_field->>'\$.std_status_desc' IS NOT NULL")
            ->whereRaw("std_extended_field->>'\$.std_status_desc' != ''")
            ->orderBy('status_desc')
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->status_desc,
                'label' => (string) $r->status_desc,
            ])
            ->filter(fn ($r) => $r['id'] !== '' && $r['id'] !== null)
            ->values();

        return $this->sendOk([
            'programLevel' => $programLevel,
            'status' => $status,
        ]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
