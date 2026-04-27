<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\LookupDetail;
use App\Models\OfferedStudent;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Student Finance > List of Offered (PAGEID 2181 / MENUID 2636).
 *
 * Source: FIMS BL `MZ_BL_SF_OFFEREDLIST`. Read-only datatable joining
 * `offered_student` (DB_SECOND_DATABASE) against three optional payment
 * surfaces — `receipt_master` (auto-receipt), `receipt_batch_detl` +
 * `receipt_batch_master` (knockoff batches) and `manual_journal_master`
 * (manual unidentified journals) — so a single row reveals whichever of
 * the three channels actually settled the offer fee.
 *
 * Smart filter keys preserve the legacy contract:
 *   - ost_program_level — exact match
 *   - ost_offered_semester — exact match (legacy autosuggest, exact value
 *     submitted)
 *
 * Global search mirrors the legacy `CONCAT_WS('__', ...)` surface so
 * cross-column substring queries work the same as the FIMS UI.
 *
 * The page does not declare a `printout` field on `COMPONENT_JS`, so per
 * project policy the frontend exposes PDF, CSV and Excel exports backed
 * by the same query (full filtered set, no extra endpoint).
 *
 * Joined tables straddle utf8mb3 / utf8mb4 collations on the legacy DB
 * (same hazard as `BankAccountUpdateController` / `LedgerController`),
 * so every join predicate and global-search expression is wrapped via
 * `CollationSafeSql::cs()` to avoid SQLSTATE[HY000] 1267 / 1253.
 */
class OfferedStudentController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    private const SORTABLE = [
        'matric',
        'name',
        'ic_passport',
        'program_level',
        'offered_semester',
        'payment',
        'approve_date',
        'receipt_no',
    ];

    public function options(): JsonResponse
    {
        $programLevel = LookupDetail::query()
            ->where('lma_code_name', 'PROGRAM_LEVEL')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => (string) $r->lde_value,
                'label' => $r->lde_description
                    ? $r->lde_value.' - '.$r->lde_description
                    : (string) $r->lde_value,
            ])
            ->values();

        // Legacy surfaces ost_offered_semester via the `V2_GLOBAL_AUTOSUGGEST`
        // semester endpoint (not migrated). Use distinct values that actually
        // exist in the table so the dropdown stays accurate without pulling
        // in another BL.
        $offeredSemester = OfferedStudent::query()
            ->select('ost_offered_semester')
            ->whereNotNull('ost_offered_semester')
            ->where('ost_offered_semester', '!=', '')
            ->distinct()
            ->orderBy('ost_offered_semester', 'desc')
            ->pluck('ost_offered_semester')
            ->map(fn ($s) => [
                'id' => (string) $s,
                'label' => (string) $s,
            ])
            ->values();

        return $this->sendOk([
            'programLevel' => $programLevel,
            'offeredSemester' => $offeredSemester,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'matric');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'matric';
        }

        $programLevel = trim((string) $request->input('ost_program_level', ''));
        $offeredSemester = trim((string) $request->input('ost_offered_semester', ''));

        $base = $this->baseQuery($programLevel);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            // Legacy concat surface is duplicated as-is so behaviour matches
            // FIMS dollar-for-dollar. Wrapping each fragment with cs() avoids
            // 1267 collation mixes between offered_student (utf8mb3) and the
            // newer receipt_*/manual_journal_* tables (utf8mb4).
            $base->whereRaw(
                'LOWER(CONCAT_WS(\'__\','
                    .$this->cs("IFNULL(os.ost_student_id, '')").','
                    .$this->cs("IFNULL(os.ost_student_name, '')").','
                    .$this->cs("IFNULL(IF(os.ost_ic_no='', NULL, os.ost_ic_no), IFNULL(os.ost_passport, ''))").','
                    .$this->cs("IFNULL(os.ost_offered_semester, '')").','
                    .$this->cs("IFNULL((SELECT lde_description FROM lookup_details WHERE lma_code_name = 'PROGRAM_LEVEL' AND lde_value = os.ost_program_level LIMIT 1), '')").','
                    .$this->cs('IFNULL(IFNULL(rm.rma_total_amt, rbd.rbd_transaction_amt), mjm.mjm_total_amt)').','
                    .$this->cs('IFNULL(IFNULL(rm.rma_receipt_no, mjm.mjm_journal_no), rbm.rbm_reference_no)').
                ')) LIKE ?',
                [$like]
            );
        }

        if ($offeredSemester !== '') {
            $base->where('os.ost_offered_semester', $offeredSemester);
        }

        $total = (clone $base)->count();

        $orderColumn = match ($sortBy) {
            'matric' => 'os.ost_student_id',
            'name' => 'os.ost_student_name',
            'ic_passport' => DB::raw('IFNULL(os.ost_ic_no, os.ost_passport)'),
            'program_level' => 'os.ost_program_level',
            'offered_semester' => 'os.ost_offered_semester',
            'payment' => DB::raw('IFNULL(IFNULL(rm.rma_total_amt, mjm.mjm_total_amt), rbd.rbd_transaction_amt)'),
            'approve_date' => DB::raw('IFNULL(IFNULL(rm.rma_approve_date, mjm.mjm_approvedate), rbd.rbd_transaction_date)'),
            'receipt_no' => DB::raw('IFNULL(IFNULL(rm.rma_receipt_no, mjm.mjm_journal_no), rbm.rbm_reference_no)'),
            default => 'os.ost_student_id',
        };

        $rows = (clone $base)
            ->select([
                'os.ost_student_id',
                'os.ost_student_name',
                'os.ost_ic_no',
                'os.ost_passport',
                'os.ost_program_level',
                'os.ost_offered_semester',
                DB::raw("(SELECT lde_description FROM lookup_details WHERE lma_code_name = 'PROGRAM_LEVEL' AND lde_value = os.ost_program_level LIMIT 1) AS program_level_desc"),
                DB::raw('IFNULL(IFNULL(rm.rma_total_amt, mjm.mjm_total_amt), rbd.rbd_transaction_amt) AS payment_amt'),
                DB::raw('IFNULL(IFNULL(rm.rma_approve_date, mjm.mjm_approvedate), rbd.rbd_transaction_date) AS payment_date'),
                DB::raw('IFNULL(IFNULL(rm.rma_receipt_no, mjm.mjm_journal_no), rbm.rbm_reference_no) AS receipt_no'),
                'rm.rma_receipt_master_id',
            ])
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('os.ost_student_id', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'matric' => (string) $r->ost_student_id,
            'name' => $r->ost_student_name,
            'icPassport' => $r->ost_ic_no !== null && $r->ost_ic_no !== ''
                ? $r->ost_ic_no
                : $r->ost_passport,
            'programLevel' => $r->ost_program_level,
            'programLevelLabel' => $r->program_level_desc,
            'offeredSemester' => $r->ost_offered_semester,
            'paymentAmt' => $r->payment_amt !== null ? (float) $r->payment_amt : null,
            'paymentDate' => $r->payment_date,
            'receiptNo' => $r->receipt_no,
            'receiptMasterId' => $r->rma_receipt_master_id !== null
                ? (int) $r->rma_receipt_master_id
                : null,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    /**
     * Replicates the legacy `$common` join from `MZ_BL_SF_OFFEREDLIST`,
     * including the unusual ON-clause filter where program_level is
     * AND-ed into the receipt_master join itself (so program-level
     * filtering doesn't accidentally drop offered_student rows that
     * have no matching receipt). Bindings are passed positionally.
     */
    private function baseQuery(string $programLevelFilter): Builder
    {
        $coll = 'COLLATE utf8mb4_unicode_ci';

        $rmaJoin = "rm.rma_status='APPROVE'";
        $rmaBindings = [];
        if ($programLevelFilter !== '') {
            // Legacy semantics: program_level filter is fused into the
            // receipt-master ON clause, NOT the WHERE clause. Reproduce
            // that exact behaviour here (using the offered_student column,
            // since `ost_program_level` is what the legacy SQL filtered).
            $rmaJoin .= ' AND '.$this->cs('os.ost_program_level').' = '.$this->cs('?');
            $rmaBindings[] = $programLevelFilter;
        }

        return DB::connection('mysql_secondary')
            ->table('offered_student as os')
            ->leftJoin('receipt_master as rm', function ($join) use ($coll, $rmaJoin, $rmaBindings) {
                $join->on(
                    DB::raw("os.ost_student_id $coll"),
                    '=',
                    DB::raw("rm.rma_cust_id $coll"),
                )->whereRaw($rmaJoin, $rmaBindings);
            })
            ->leftJoin('receipt_batch_detl as rbd', function ($join) use ($coll) {
                $join->on(
                    DB::raw("os.ost_student_id $coll"),
                    '=',
                    DB::raw("rbd.std_student_id $coll"),
                );
            })
            ->leftJoin('receipt_batch_master as rbm', function ($join) use ($coll) {
                $join->on(
                    DB::raw("rbd.rbm_receipt_batch_master_id $coll"),
                    '=',
                    DB::raw("rbm.rbm_receipt_batch_master_id $coll"),
                )->whereRaw('rbm.rbm_status_cd = ?', ['KNOCKOFF']);
            })
            ->leftJoin('manual_journal_master as mjm', function ($join) use ($coll) {
                $join->on(
                    DB::raw("rm.rma_cust_id $coll"),
                    '=',
                    DB::raw("mjm.mjm_cust_id $coll"),
                );
            })
            // The mjd join in the legacy BL only narrows the manual-journal
            // surface (mjm_system_id='MNL_UNIDENTIFIED', mjd_payto_id='1',
            // mjd_trans_type='DT'). It is not referenced in the SELECT, but
            // dropping it would inflate row counts via duplicated mjm rows
            // when a journal has multiple details. Keep it.
            ->leftJoin('manual_journal_details as mjd', function ($join) use ($coll) {
                $join->on(
                    DB::raw("mjm.mjm_journal_id $coll"),
                    '=',
                    DB::raw("mjd.mjm_journal_no $coll"),
                )
                    ->whereRaw("mjm.mjm_system_id = 'MNL_UNIDENTIFIED'")
                    ->whereRaw("mjd.mjd_payto_id = '1'")
                    ->whereRaw("mjd.mjd_trans_type = 'DT'");
            });
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
