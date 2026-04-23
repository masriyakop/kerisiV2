<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\BankMaster;
use App\Models\StudAccountApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Student Finance > Bank Account Update (PAGEID 977 / MENUID 1081).
 *
 * Source: FIMS BL `DT_BANK_ACC_UPDATE`. Read-only datatable joining
 * `student`, `stud_account_application`, `bank_master` and
 * `academic_calendar` (all DB_SECOND_DATABASE). Global search matches
 * the same `CONCAT_WS('__', ...)` surface as the legacy SQL, and the
 * smart filter preserves the legacy keys (`filter_sem`, `filter_bank`,
 * `filter_status`).
 *
 * Status display rule from the legacy SQL is kept client-side-friendly
 * here: `IF(saa_status='APPROVE', 'APPROVED', saa_status)`.
 *
 * The application editor (the "Update" flow that inserts into
 * `student` + `stud_account`) is NOT migrated — this listing is
 * read-only and action buttons remain disabled until the editor is
 * migrated.
 */
class BankAccountUpdateController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'saa_application_no',
        'dt_id',
        'dt_name',
        'dt_ic_no',
        'dt_sem',
        'dt_acc_no',
        'dt_bank',
        'dt_app_date',
        'dt_approve_date',
        'dt_status',
    ];

    public function options(): JsonResponse
    {
        $banks = BankMaster::query()
            ->select(['bnm_bank_code', 'bnm_bank_desc'])
            ->whereNotNull('bnm_bank_code')
            ->where('bnm_bank_code', '!=', '')
            ->orderBy('bnm_bank_desc')
            ->get()
            ->map(fn ($b) => [
                'id' => (string) $b->bnm_bank_code,
                'label' => $b->bnm_bank_desc
                    ? $b->bnm_bank_code.' - '.$b->bnm_bank_desc
                    : (string) $b->bnm_bank_code,
            ])
            ->values();

        // Distinct saa_status values actually present in the table.
        $status = StudAccountApplication::query()
            ->select('saa_status')
            ->whereNotNull('saa_status')
            ->where('saa_status', '!=', '')
            ->distinct()
            ->orderBy('saa_status')
            ->pluck('saa_status')
            ->map(fn ($s) => [
                'id' => (string) $s,
                // Match the legacy IF(saa_status='APPROVE','APPROVED',...) label.
                'label' => $s === 'APPROVE' ? 'APPROVED' : (string) $s,
            ])
            ->values();

        return $this->sendOk([
            'bank' => $banks,
            'status' => $status,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dt_app_date');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'dt_app_date';
        }

        $filterSem = trim((string) $request->input('filter_sem', ''));
        $filterBank = trim((string) $request->input('filter_bank', ''));
        $filterStatus = trim((string) $request->input('filter_status', ''));

        // Mirror the legacy SQL layout (DT_BANK_ACC_UPDATE) with explicit
        // table aliases so we can use them in the search/select clauses
        // below. The join predicates are wrapped with
        // `COLLATE utf8mb4_unicode_ci` because the joined tables come
        // from different schema generations (legacy FIMS tables use
        // utf8mb4_unicode_ci; some newer tables on the same DB use the
        // MySQL 8 default utf8mb4_0900_ai_ci) and the mismatch raises
        // SQLSTATE[HY000] 1267 "Illegal mix of collations" on the
        // `=` comparison. The legacy BL runs on utf8_general_ci where
        // this coercion is implicit.
        $coll = 'COLLATE utf8mb4_unicode_ci';
        $base = StudAccountApplication::query()
            ->from('stud_account_application as saa')
            ->join('student as std', function ($join) use ($coll) {
                $join->on(
                    DB::raw("std.std_student_id $coll"),
                    '=',
                    DB::raw("saa.std_student_id $coll"),
                );
            })
            ->join('bank_master as bnm', function ($join) use ($coll) {
                $join->on(
                    DB::raw("bnm.bnm_bank_code $coll"),
                    '=',
                    DB::raw("saa.bnm_bank_code $coll"),
                );
            })
            ->join('academic_calendar as acc', function ($join) use ($coll) {
                $join->on(
                    DB::raw("acc.acl_semester_code $coll"),
                    '=',
                    DB::raw("std.std_current_sem $coll"),
                );
            });

        // Force a consistent collation on every text expression that
        // touches more than one joined table, same reason as the join
        // predicates above (1267 "Illegal mix of collations").
        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(std.std_student_id, '') $coll,
                    IFNULL(std.std_student_name, '') $coll,
                    IFNULL(std.std_ic_no, '') $coll,
                    IFNULL(std.std_passport, '') $coll,
                    IFNULL(std.std_sem_level, '') $coll,
                    IFNULL(saa.saa_bank_acc_no, '') $coll,
                    IFNULL(bnm.bnm_bank_desc, '') $coll,
                    IFNULL(saa.saa_application_no, '') $coll,
                    IFNULL(DATE_FORMAT(saa.saa_apply_date, '%d/%m/%Y'), '') $coll,
                    IFNULL(saa.saa_status, '') $coll
                )) LIKE ?",
                [$like]
            );
        }

        if ($filterSem !== '') {
            $like = $this->likeEscape(mb_strtolower($filterSem, 'UTF-8'));
            $base->whereRaw("LOWER(IFNULL(std.std_sem_level, '') $coll) LIKE ?", [$like]);
        }

        if ($filterBank !== '') {
            // Legacy BL uses LIKE for partial bank-code matches; keep the
            // substring semantics even though the UI exposes exact-match
            // dropdown values (avoids behaviour drift).
            $like = $this->likeEscape(mb_strtolower($filterBank, 'UTF-8'));
            $base->whereRaw("LOWER(IFNULL(bnm.bnm_bank_code, '') $coll) LIKE ?", [$like]);
        }

        if ($filterStatus !== '') {
            $like = $this->likeEscape(mb_strtolower($filterStatus, 'UTF-8'));
            $base->whereRaw("LOWER(IFNULL(saa.saa_status, '') $coll) LIKE ?", [$like]);
        }

        $total = (clone $base)->count();

        $orderColumn = match ($sortBy) {
            'saa_application_no' => 'saa.saa_application_no',
            'dt_id' => 'std.std_student_id',
            'dt_name' => 'std.std_student_name',
            'dt_ic_no' => DB::raw('IFNULL(std.std_ic_no, std.std_passport)'),
            'dt_sem' => 'acc.acl_semester_code',
            'dt_acc_no' => 'saa.saa_bank_acc_no',
            'dt_bank' => 'bnm.bnm_bank_desc',
            'dt_app_date' => 'saa.saa_apply_date',
            'dt_approve_date' => 'saa.saa_approved_date',
            'dt_status' => 'saa.saa_status',
            default => 'saa.saa_apply_date',
        };

        $rows = (clone $base)
            ->select([
                'saa.saa_application_id',
                'saa.saa_application_no',
                'std.std_student_id as dt_id',
                'std.std_student_name as dt_name',
                DB::raw('IFNULL(std.std_ic_no, std.std_passport) as dt_ic_no'),
                DB::raw("CONCAT('(', acc.acl_semester_code, ') ', acc.acl_semester_name) as dt_sem"),
                'saa.saa_bank_acc_no as dt_acc_no',
                'bnm.bnm_bank_desc as dt_bank',
                'bnm.bnm_bank_code',
                DB::raw("DATE_FORMAT(saa.saa_apply_date, '%d/%m/%Y') as dt_app_date"),
                DB::raw("DATE_FORMAT(saa.saa_approved_date, '%d/%m/%Y') as dt_approve_date"),
                DB::raw("IF(saa.saa_status='APPROVE','APPROVED',saa.saa_status) as dt_status"),
                'saa.saa_status as status_raw',
            ])
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('saa.saa_application_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'applicationId' => (int) $r->saa_application_id,
            'applicationNo' => $r->saa_application_no,
            'matric' => $r->dt_id,
            'name' => $r->dt_name,
            'icPassport' => $r->dt_ic_no,
            'currentSemester' => $r->dt_sem,
            'accountNo' => $r->dt_acc_no,
            'bankName' => $r->dt_bank,
            'bankCode' => $r->bnm_bank_code,
            'applicationDate' => $r->dt_app_date,
            'approvedDate' => $r->dt_approve_date,
            'status' => $r->dt_status,
            'statusRaw' => $r->status_raw,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
