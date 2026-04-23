<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PettyCashBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Petty Cash Recoup list (PAGEID 1255 / MENUID 1532).
 *
 * Source: FIMS BL `API_PETTYCASH_PETTYCASHRECOUP` (?PettyCashRecoupList_dt=1).
 * Joins petty_cash_batch + petty_cash_details + petty_cash_main + voucher_master.
 *
 * Legacy FLC_USER_GROUP_MAPPING / holder-only branches are not wired to Laravel
 * users yet. Use optional query params:
 * - `staff_id` — restrict rows to batches linked to a petty_cash_main row whose
 *   pcm_holder_id matches this value (approximates non–finance-unit users).
 * - Omit `staff_id` — list all batches (approximates finance / unit bayaran users).
 */
class PettyCashRecoupController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'pcb_batch_id' => 'pcb.pcb_batch_id',
        'pcb_trans_no' => 'pcb.pcb_trans_no',
        'pcb_batch_amt' => 'pcb.pcb_batch_amt',
        'pcm_balance' => 'pcm.pcm_balance',
        'pcb_balance_before' => 'pcb.pcb_balance_before',
        'pcb_receiveamt' => 'pcb.pcb_receiveamt',
        'pcb_balance_inhand' => 'pcb.pcb_balance_inhand',
        'pcb_status' => 'pcb.pcb_status',
        'vma_voucher_no' => 'vm.vma_voucher_no',
        'vma_vch_status' => 'vm.vma_vch_status',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $staffId = trim((string) $request->input('staff_id', ''));

        $sortByKey = (string) $request->input('sort_by', 'pcb_batch_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderExpr = self::SORTABLE[$sortByKey] ?? 'pcb.pcb_batch_id';

        $needle = $q !== '' ? $this->likeEscape(mb_strtolower($q, 'UTF-8')) : null;

        $base = PettyCashBatch::on('mysql_secondary')
            ->from('petty_cash_batch as pcb')
            ->leftJoin('petty_cash_details as pcd', 'pcb.pcb_batch_id', '=', 'pcd.pcb_batch_id')
            ->leftJoin('petty_cash_main as pcm', 'pcm.pcm_id', '=', 'pcd.pcm_id')
            ->leftJoin('voucher_master as vm', 'pcb.vma_voucher_id', '=', 'vm.vma_voucher_id')
            ->when($staffId !== '', function ($qry) use ($staffId) {
                $qry->whereExists(function ($sub) use ($staffId) {
                    $sub->select(DB::raw('1'))
                        ->from('petty_cash_details as pcd2')
                        ->join('petty_cash_main as pcm2', 'pcm2.pcm_id', '=', 'pcd2.pcm_id')
                        ->whereColumn('pcd2.pcb_batch_id', 'pcb.pcb_batch_id')
                        ->where('pcm2.pcm_holder_id', $staffId);
                });
            })
            ->when($needle !== null, function ($qry) use ($needle) {
                $qry->whereRaw(
                    "LOWER(CONCAT_WS('__',
                        IFNULL(pcb.pcb_batch_id,''),
                        IFNULL(pcb.pcb_trans_no,''),
                        IFNULL(pcb.pcb_batch_amt,''),
                        IFNULL(pcb.pcb_status,''),
                        IFNULL(vm.vma_voucher_no,''),
                        IFNULL(vm.vma_vch_status,''),
                        IFNULL(pcb.pcb_balance_before,''),
                        IFNULL(pcm.pcm_balance,''),
                        IFNULL(pcb.pcb_receiveamt,''),
                        IFNULL(pcb.pcb_balance_inhand,''))) LIKE ?",
                    [$needle]
                );
            });

        $total = (clone $base)->select('pcb.pcb_id')->distinct()->count('pcb.pcb_id');

        $rows = (clone $base)
            ->select([
                'pcb.pcb_id',
                'pcb.pcb_batch_id',
                'pcb.pcb_trans_no',
                'pcb.pcb_batch_amt',
                'pcb.pcb_status',
                'pcb.pcb_balance_before',
                'pcb.pcb_receiveamt',
                'pcb.pcb_balance_inhand',
                'pcm.pcm_balance',
                'vm.vma_voucher_no',
                'vm.vma_vch_status',
            ])
            ->distinct()
            ->orderBy($orderExpr, $sortDir)
            ->orderBy('pcb.pcb_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $pcbId = (int) $r->pcb_id;
            $status = (string) ($r->pcb_status ?? '');
            $qsView = http_build_query([
                'pcb_id' => $pcbId,
                'mode' => 'view',
                'currStatus' => $status,
            ]);
            $qsEdit = http_build_query([
                'pcb_id' => $pcbId,
                'mode' => 'edit',
                'currStatus' => $status,
            ]);

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pcb_id' => $pcbId,
                'pcb_batch_id' => $r->pcb_batch_id,
                'pcb_trans_no' => $r->pcb_trans_no,
                'pcb_batch_amt' => $r->pcb_batch_amt !== null ? (float) $r->pcb_batch_amt : null,
                'pcm_balance' => $r->pcm_balance !== null ? (float) $r->pcm_balance : null,
                'pcb_balance_before' => $r->pcb_balance_before !== null ? (float) $r->pcb_balance_before : null,
                'pcb_receiveamt' => $r->pcb_receiveamt !== null ? (float) $r->pcb_receiveamt : null,
                'pcb_balance_inhand' => $r->pcb_balance_inhand !== null ? (float) $r->pcb_balance_inhand : null,
                'pcb_status' => $status,
                'vma_voucher_no' => $r->vma_voucher_no,
                'vma_vch_status' => $r->vma_vch_status,
                'url_view' => '/admin/kerisi/m/1534?'.$qsView,
                'url_edit' => '/admin/kerisi/m/1534?'.$qsEdit,
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
     * Return Petty Cash Recoup batch + selected line items (PAGEID 1256 / MENUID 1534).
     *
     * Source: FIMS BL `API_PETTYCASH_PETTYCASHRECOUPFORM` — `PettyCashBatchMaster` +
     * `PettyCashRecoupDetailSelected_dt` branches. Read-only for now; workflow
     * actions (submit/endorse/approve/reject) are deferred until the FIMS
     * workflow engine is ported.
     */
    public function show(int $pcbId): JsonResponse
    {
        $batch = DB::connection('mysql_secondary')
            ->table('petty_cash_batch')
            ->select([
                'pcb_id',
                'pcb_batch_id',
                'pcb_trans_no',
                'pcb_batch_amt',
                'pcb_status',
                'pcb_balance_before',
                'pcb_receiveamt',
                'pcb_balance_inhand',
                'vma_voucher_id',
                'oun_code',
            ])
            ->where('pcb_id', $pcbId)
            ->first();

        if (! $batch) {
            return $this->sendError(404, 'NOT_FOUND', 'Petty cash recoup batch not found');
        }

        $lines = DB::connection('mysql_secondary')
            ->table('petty_cash_details as pcd')
            ->leftJoin('petty_cash_master as pcm', 'pcm.pms_application_no', '=', 'pcd.pms_application_no')
            ->where('pcd.pcb_batch_id', $batch->pcb_batch_id)
            ->orderBy('pcd.pcd_id')
            ->select([
                'pcd.pcd_id',
                'pcd.pms_application_no',
                'pcm.pms_request_date',
                'pcd.pcd_receipt_no',
                'pcd.fty_fund_type',
                'pcd.at_activity_code',
                'pcd.oun_code',
                'pcd.ccr_costcentre',
                'pcd.acm_acct_code',
                'pcd.so_code',
                'pcd.pcd_trans_amt',
                'pcd.pcd_batch_status',
                'pcd.cpa_project_no',
            ])
            ->get()
            ->values()
            ->map(fn ($l, int $i) => [
                'index' => $i + 1,
                'pcd_id' => (int) $l->pcd_id,
                'pms_application_no' => (string) ($l->pms_application_no ?? ''),
                'pms_request_date' => $l->pms_request_date ? (string) $l->pms_request_date : null,
                'pcd_receipt_no' => (string) ($l->pcd_receipt_no ?? ''),
                'fty_fund_type' => (string) ($l->fty_fund_type ?? ''),
                'at_activity_code' => (string) ($l->at_activity_code ?? ''),
                'oun_code' => (string) ($l->oun_code ?? ''),
                'ccr_costcentre' => (string) ($l->ccr_costcentre ?? ''),
                'acm_acct_code' => (string) ($l->acm_acct_code ?? ''),
                'so_code' => (string) ($l->so_code ?? ''),
                'cpa_project_no' => (string) ($l->cpa_project_no ?? ''),
                'pcd_trans_amt' => $l->pcd_trans_amt !== null ? (float) $l->pcd_trans_amt : null,
                'pcd_batch_status' => (string) ($l->pcd_batch_status ?? ''),
            ]);

        $voucher = null;
        if (! empty($batch->vma_voucher_id)) {
            $voucher = DB::connection('mysql_secondary')
                ->table('voucher_master')
                ->where('vma_voucher_id', $batch->vma_voucher_id)
                ->select(['vma_voucher_id', 'vma_voucher_no', 'vma_vch_status'])
                ->first();
        }

        return $this->sendOk([
            'pcb_id' => (int) $batch->pcb_id,
            'pcb_batch_id' => (string) ($batch->pcb_batch_id ?? ''),
            'pcb_trans_no' => $batch->pcb_trans_no !== null ? (int) $batch->pcb_trans_no : null,
            'pcb_batch_amt' => $batch->pcb_batch_amt !== null ? (float) $batch->pcb_batch_amt : null,
            'pcb_status' => (string) ($batch->pcb_status ?? ''),
            'pcb_balance_before' => $batch->pcb_balance_before !== null ? (float) $batch->pcb_balance_before : null,
            'pcb_receiveamt' => $batch->pcb_receiveamt !== null ? (float) $batch->pcb_receiveamt : null,
            'pcb_balance_inhand' => $batch->pcb_balance_inhand !== null ? (float) $batch->pcb_balance_inhand : null,
            'oun_code' => (string) ($batch->oun_code ?? ''),
            'vma_voucher_no' => $voucher->vma_voucher_no ?? null,
            'vma_vch_status' => $voucher->vma_vch_status ?? null,
            'lines' => $lines,
        ]);
    }

    private function likeEscape(string $needleLower): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needleLower).'%';
    }
}
