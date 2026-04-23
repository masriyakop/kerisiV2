<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\VoucherMaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * List of Voucher Petty Cash (PAGEID 2774 / MENUID 3344).
 *
 * Source: FIMS BL `NAD_API_PC_LISTOFVOUCHERPETTYCASH` (?dt_details=1). Lists
 * vouchers generated from petty-cash bills (voucher_master joined through
 * bills_master to petty_cash_batch). Supports a smart-filter modal on the
 * frontend with free-text plus per-field narrowing.
 */
class PettyCashVoucherListController extends Controller
{
    use ApiResponse;

    // FIMS `voucher_master` does not carry either a voucher_date or a
    // voucher_amt column. The legacy BL sources the date from
    // `voucher_master.createddate` and the "voucher amount" from
    // `SUM(pcd_trans_amt)` on `petty_cash_details` (already pre-aggregated
    // into `petty_cash_batch.pcb_batch_amt`). Public sort keys stay
    // `vma_voucher_date` / `vma_voucher_amt` for API stability — they map
    // to the real underlying columns here.
    private const SORTABLE = [
        'vma_voucher_no' => 'vm.vma_voucher_no',
        'vma_voucher_date' => 'vm.createddate',
        'vma_voucher_amt' => 'pcb.pcb_batch_amt',
        'vma_vch_status' => 'vm.vma_vch_status',
        'bim_bills_no' => 'bm.bim_bills_no',
        'pcb_batch_id' => 'pcb.pcb_batch_id',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 5)));
        $q = trim((string) $request->input('q', ''));

        $sortByKey = (string) $request->input('sort_by', 'vma_voucher_date');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderExpr = self::SORTABLE[$sortByKey] ?? 'vm.createddate';

        $voucherNo = trim((string) $request->input('vma_voucher_no', ''));
        $voucherStatus = trim((string) $request->input('vma_vch_status', ''));
        $billsNo = trim((string) $request->input('bim_bills_no', ''));
        $batchId = trim((string) $request->input('pcb_batch_id', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        $base = VoucherMaster::on('mysql_secondary')
            ->from('voucher_master as vm')
            ->join('bills_master as bm', 'bm.bim_voucher_no', '=', 'vm.vma_voucher_no')
            ->join('petty_cash_batch as pcb', 'pcb.bim_bills_no', '=', 'bm.bim_bills_no')
            ->when($voucherNo !== '', fn ($qry) => $qry->where('vm.vma_voucher_no', 'like', '%'.$voucherNo.'%'))
            ->when($voucherStatus !== '', fn ($qry) => $qry->where('vm.vma_vch_status', $voucherStatus))
            ->when($billsNo !== '', fn ($qry) => $qry->where('bm.bim_bills_no', 'like', '%'.$billsNo.'%'))
            ->when($batchId !== '', fn ($qry) => $qry->where('pcb.pcb_batch_id', 'like', '%'.$batchId.'%'))
            ->when($dateFrom !== '', fn ($qry) => $qry->whereDate('vm.createddate', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($qry) => $qry->whereDate('vm.createddate', '<=', $dateTo))
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS('__',
                        IFNULL(vm.vma_voucher_no,''),
                        IFNULL(pcb.pcb_batch_amt,''),
                        IFNULL(vm.vma_vch_status,''),
                        IFNULL(bm.bim_bills_no,''),
                        IFNULL(pcb.pcb_batch_id,''))) LIKE ?",
                    [$like]
                );
            });

        $total = (clone $base)->distinct()->count('vm.vma_voucher_id');

        $rows = (clone $base)
            ->select([
                'vm.vma_voucher_id',
                'vm.vma_voucher_no',
                'vm.createddate as vma_voucher_date',
                'pcb.pcb_batch_amt as vma_voucher_amt',
                'vm.vma_vch_status',
                'bm.bim_bills_no',
                'pcb.pcb_batch_id',
            ])
            ->distinct()
            ->orderBy($orderExpr, $sortDir)
            ->orderBy('vm.vma_voucher_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $voucherId = (int) $r->vma_voucher_id;
            $qs = http_build_query([
                'mode' => 'view',
                'vma_voucher_id' => $voucherId,
            ]);

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'vma_voucher_id' => $voucherId,
                'vma_voucher_no' => $r->vma_voucher_no,
                'vma_voucher_date' => $r->vma_voucher_date ? Carbon::parse($r->vma_voucher_date)->format('d/m/Y') : '',
                'vma_voucher_amt' => $r->vma_voucher_amt !== null ? (float) $r->vma_voucher_amt : null,
                'vma_vch_status' => $r->vma_vch_status,
                'bim_bills_no' => $r->bim_bills_no,
                'pcb_batch_id' => $r->pcb_batch_id,
                'url_view' => '/admin/kerisi/m/3344?'.$qs,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function options(): JsonResponse
    {
        $statuses = VoucherMaster::on('mysql_secondary')
            ->from('voucher_master as vm')
            ->join('bills_master as bm', 'bm.bim_voucher_no', '=', 'vm.vma_voucher_no')
            ->join('petty_cash_batch as pcb', 'pcb.bim_bills_no', '=', 'bm.bim_bills_no')
            ->whereNotNull('vm.vma_vch_status')
            ->distinct()
            ->orderBy('vm.vma_vch_status')
            ->pluck('vm.vma_vch_status')
            ->filter(fn ($s) => $s !== null && $s !== '')
            ->values()
            ->map(fn ($s) => ['id' => $s, 'label' => $s])
            ->all();

        return $this->sendOk([
            'status' => $statuses,
        ]);
    }

    private function likeEscape(string $needleLower): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needleLower).'%';
    }
}
