<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\OfflineReceiptMaster;
use App\Models\PreprintedReceiptStockMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Account Receivable > Cashbook PTJ" endpoint (PAGEID 2048 / MENUID 1049).
 *
 * Source: FIMS BL `MZ_BL_AR_CASHBOOK_LISTING` (?dtCashbook=1 for the list,
 * ?download=1 for the CSV export — ported to frontend-side export here). The
 * legacy listing is a UNION over two receipt sources, grouped by staff +
 * counter:
 *
 *   1. Offline receipts:
 *        offline_receipt_master orm
 *        + offline_receipt_authorize ora  (ore_counter_no = orm_counter_no)
 *        + staff stf                      (stf_ad_username = orm.createdby)
 *        WHERE orm.orm_status NOT IN ('REJECT','CANCEL')
 *        GROUP BY stf_staff_id, stf_staff_name, oun_code_ptj,
 *                 orm_counter_no, event_desc/remark
 *
 *   2. Preprinted receipts:
 *        preprinted_receipt_stock_master prsm
 *        + preprinted_receipt_stock_details prsd
 *        + offline_receipt_authorize ora (ora.ore_counter_no = prsd_counter_no,
 *                                         ora.ore_status='APPROVE')
 *        + offline_receipt_staff ors     (ore_offline_receipt_id = ora.*)
 *        + offline_receipt_rate orr      (orr_id = prsd.orr_id,
 *                                         ore_offline_receipt_id = ora.*)
 *        + staff stf                     (stf_staff_id = ors.ors_staff_id)
 *        GROUP BY stf_staff_id, stf_staff_name, oun_code_ptj,
 *                 ore_counter_no, event_desc/remark
 *
 * Both branches return the same 7-column shape (staff id/name, PTJ,
 * application_no, purpose, collection_amt, receipt_type).
 *
 * # Assumption on user scoping
 * The legacy BL scopes results to the authenticated staff (`$_USER['STAFF_ID']`)
 * OR members of the `UUM_UNIT_TERIMAAN` FIMS user group. This project does not
 * yet model FIMS user-groups, so scoping is exposed as the optional `staff_id`
 * query-param (admins call it with no filter to see all). Adding group-based
 * restriction is a follow-up when the FIMS user-group import lands.
 */
class CashbookPtjController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $staffId = trim((string) $request->input('staff_id', ''));

        $offline = $this->offlineQuery($staffId);
        $preprinted = $this->preprintedQuery($staffId);

        // `LIKE` / pagination are applied on the UNION as a sub-query so both
        // branches contribute to the final dataset consistently.
        $sub = DB::connection('mysql_secondary')
            ->query()
            ->fromSub($offline->unionAll($preprinted), 'aggregated');

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $sub->where(function ($b) use ($like) {
                foreach ([
                    'staff_id', 'staff_name', 'staff_ptj',
                    'application_no', 'purpose', 'receipt_type',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL($col, '')) LIKE ?", [$like]);
                }
            });
        }

        $total = (clone $sub)->count();

        $rows = $sub
            ->orderBy('staff_id')
            ->orderBy('application_no')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'staffId' => $r->staff_id,
            'staffName' => $r->staff_name,
            'staffPtj' => $r->staff_ptj,
            'applicationNo' => $r->application_no,
            'purpose' => $r->purpose,
            'collectionAmount' => (float) $r->collection_amt,
            'receiptType' => $r->receipt_type,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    /**
     * Offline receipt branch of the union (legacy $common).
     */
    private function offlineQuery(string $staffId): Builder
    {
        $query = OfflineReceiptMaster::query()
            ->from('offline_receipt_master as orm')
            ->join('offline_receipt_authorize as ora', 'ora.ore_counter_no', '=', 'orm.orm_counter_no')
            ->join('staff as stf', 'stf.stf_ad_username', '=', 'orm.createdby')
            ->whereNotIn('orm.orm_status', ['REJECT', 'CANCEL']);

        if ($staffId !== '') {
            $query->where('stf.stf_staff_id', $staffId);
        }

        return $query
            ->groupBy('stf.stf_staff_id', 'stf.stf_staff_name', 'ora.oun_code_ptj', 'orm.orm_counter_no')
            ->groupByRaw("IFNULL(ora.ore_extended_field->>'$.ore_event_desc', ora.ore_remark)")
            ->select([
                DB::raw('stf.stf_staff_id as staff_id'),
                DB::raw('stf.stf_staff_name as staff_name'),
                DB::raw('ora.oun_code_ptj as staff_ptj'),
                DB::raw('orm.orm_counter_no as application_no'),
                DB::raw("IFNULL(ora.ore_extended_field->>'\$.ore_event_desc', ora.ore_remark) as purpose"),
                DB::raw('SUM(orm.orm_total_amt) as collection_amt'),
                DB::raw("'Offline' as receipt_type"),
            ]);
    }

    /**
     * Preprinted receipt branch of the union (legacy $common2).
     */
    private function preprintedQuery(string $staffId): Builder
    {
        $query = PreprintedReceiptStockMaster::query()
            ->from('preprinted_receipt_stock_master as prsm')
            ->join('preprinted_receipt_stock_details as prsd', 'prsm.prsm_id', '=', 'prsd.prsm_id')
            ->join('offline_receipt_authorize as ora', 'ora.ore_counter_no', '=', 'prsd.prsd_counter_no')
            ->join('offline_receipt_staff as ors', 'ora.ore_offline_receipt_id', '=', 'ors.ore_offline_receipt_id')
            ->join('offline_receipt_rate as orr', function ($j) {
                $j->on('ora.ore_offline_receipt_id', '=', 'orr.ore_offline_receipt_id')
                    ->on('orr.orr_id', '=', 'prsd.orr_id');
            })
            ->join('staff as stf', 'stf.stf_staff_id', '=', 'ors.ors_staff_id')
            ->where('ora.ore_status', 'APPROVE');

        if ($staffId !== '') {
            $query->where('stf.stf_staff_id', $staffId);
        }

        return $query
            ->groupBy('stf.stf_staff_id', 'stf.stf_staff_name', 'ora.oun_code_ptj', 'ora.ore_counter_no')
            ->groupByRaw("IFNULL(ora.ore_extended_field->>'$.ore_event_desc', ora.ore_remark)")
            ->select([
                DB::raw('stf.stf_staff_id as staff_id'),
                DB::raw('stf.stf_staff_name as staff_name'),
                DB::raw('ora.oun_code_ptj as staff_ptj'),
                DB::raw('ora.ore_counter_no as application_no'),
                DB::raw("IFNULL(ora.ore_extended_field->>'\$.ore_event_desc', ora.ore_remark) as purpose"),
                DB::raw('SUM(prsd.prsd_value) as collection_amt'),
                DB::raw("'Preprinted' as receipt_type"),
            ]);
    }

    private function likeEscape(string $needle): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
    }
}
