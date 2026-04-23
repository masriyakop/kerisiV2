<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PettyCashMaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Request Petty Cash list (PAGEID 2010 / MENUID 2456).
 *
 * Source: FIMS BL `NAD_API_PC_REQUESTPETTYCASH` (?dt_details=1). Aggregates
 * petty_cash_master with its details, related batch, bills_master and
 * voucher_master through Eloquent + query-builder joins. Each application
 * appears as a single row summarising status across the downstream tables.
 *
 * Legacy PTJ gating via the staff's pcm_id is not ported. Pass an optional
 * `staff_id` query param to narrow to applications whose main row has
 * pcm_holder_id equal to that staff id.
 */
class PettyCashRequestListController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'pms_application_no' => 'pms.pms_application_no',
        'pms_request_date' => 'pms.pms_request_date',
        'pms_request_by' => 'pms.pms_request_by',
        'pcb_batch_id' => 'pcb.pcb_batch_id',
        'pcb_status' => 'pcb.pcb_status',
        'bim_bills_no' => 'bm.bim_bills_no',
        'bim_status' => 'bm.bim_status',
        'vma_voucher_no' => 'vm.vma_voucher_no',
        'vma_vch_status' => 'vm.vma_vch_status',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 5)));
        $q = trim((string) $request->input('q', ''));
        $staffId = trim((string) $request->input('staff_id', ''));

        $sortByKey = (string) $request->input('sort_by', 'pms_application_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderExpr = self::SORTABLE[$sortByKey] ?? 'pms.pms_application_no';

        $base = PettyCashMaster::on('mysql_secondary')
            ->from('petty_cash_master as pms')
            ->join('petty_cash_details as pcd', 'pcd.pms_application_no', '=', 'pms.pms_application_no')
            ->leftJoin('petty_cash_batch as pcb', 'pcb.pcb_batch_id', '=', 'pcd.pcb_batch_id')
            ->leftJoin('bills_master as bm', 'bm.bim_bills_no', '=', 'pcb.bim_bills_no')
            ->leftJoin('voucher_master as vm', 'vm.vma_voucher_no', '=', 'bm.bim_voucher_no')
            ->leftJoin('staff as s', function ($join) {
                $join->on('s.stf_ad_username', '=', 'pms.pms_request_by')
                    ->orOn('s.stf_staff_id', '=', 'pms.pms_request_by');
            })
            ->when($staffId !== '', function ($qry) use ($staffId) {
                $qry->whereExists(function ($sub) use ($staffId) {
                    $sub->selectRaw('1')
                        ->from('petty_cash_main as pcm')
                        ->whereColumn('pcm.pcm_id', 'pcd.pcm_id')
                        ->where('pcm.pcm_holder_id', $staffId);
                });
            })
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS('__',
                        IFNULL(pms.pms_application_no,''),
                        IFNULL(pms.pms_request_by,''),
                        IFNULL(s.stf_staff_name,''),
                        IFNULL(pcb.pcb_batch_id,''),
                        IFNULL(pcb.pcb_status,''),
                        IFNULL(bm.bim_bills_no,''),
                        IFNULL(bm.bim_status,''),
                        IFNULL(vm.vma_voucher_no,''),
                        IFNULL(vm.vma_vch_status,''))) LIKE ?",
                    [$like]
                );
            });

        $total = (clone $base)->distinct()->count('pms.pms_id');

        $rows = (clone $base)
            ->select([
                'pms.pms_id',
                'pms.pms_application_no',
                'pms.pms_request_by',
                'pms.pms_request_date',
                'pms.pms_total_amt',
                's.stf_staff_name',
                'pcb.pcb_batch_id',
                'pcb.pcb_status',
                'pcb.createddate as pcb_created',
                'bm.bim_bills_no',
                'bm.bim_status',
                'bm.createddate as bim_created',
                'vm.vma_voucher_no',
                'vm.vma_vch_status',
                'vm.createddate as vma_created',
            ])
            ->distinct()
            ->orderBy($orderExpr, $sortDir)
            ->orderBy('pms.pms_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $pmsId = (int) $r->pms_id;
            $qs = http_build_query([
                'mode' => 'view',
                'pms_id' => $pmsId,
            ]);

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pms_id' => $pmsId,
                'pms_application_no' => $r->pms_application_no,
                'pms_request_by' => $r->pms_request_by,
                'stf_staff_name' => $r->stf_staff_name,
                'pms_request_date' => $r->pms_request_date ? Carbon::parse($r->pms_request_date)->format('d/m/Y') : '',
                'pms_total_amt' => $r->pms_total_amt !== null ? (float) $r->pms_total_amt : null,
                'pcb_batch_id' => $r->pcb_batch_id,
                'pcb_status' => $r->pcb_status,
                'recoup_created_date' => $r->pcb_created ? Carbon::parse($r->pcb_created)->format('d/m/Y') : '',
                'bim_bills_no' => $r->bim_bills_no,
                'bim_status' => $r->bim_status,
                'bill_created_date' => $r->bim_created ? Carbon::parse($r->bim_created)->format('d/m/Y') : '',
                'vma_voucher_no' => $r->vma_voucher_no,
                'vma_vch_status' => $r->vma_vch_status,
                'voucher_created_date' => $r->vma_created ? Carbon::parse($r->vma_created)->format('d/m/Y') : '',
                'url_view' => '/admin/kerisi/m/1490?'.$qs,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    private function likeEscape(string $needleLower): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needleLower).'%';
    }
}
