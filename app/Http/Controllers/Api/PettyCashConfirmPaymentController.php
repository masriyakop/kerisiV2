<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PettyCashBatch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Confirmation Payment — Petty Cash (PAGEID 1982 / MENUID 2424).
 *
 * Source: FIMS BL `NAD_API_PC_CONFIRMATIONPAYMENT`
 *   - ?dt_awaiting=1 — awaiting-confirmation batches (pcb_receiveamt IS NULL)
 *   - ?dt_confirmed=1 — already-confirmed batches (pcb_receiveamt IS NOT NULL)
 *
 * Joins voucher_master, voucher_details, payment_record and petty_cash_*
 * via Eloquent base + query-builder joins. Legacy PTJ gating is approximated
 * via optional `staff_id` query param (narrows to batches tied to a
 * petty_cash_main with matching pcm_holder_id).
 */
class PettyCashConfirmPaymentController extends Controller
{
    use ApiResponse;

    public function awaiting(Request $request): JsonResponse
    {
        return $this->listBatches($request, false);
    }

    public function confirmed(Request $request): JsonResponse
    {
        return $this->listBatches($request, true);
    }

    private function listBatches(Request $request, bool $confirmed): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 5)));
        $q = trim((string) $request->input('q', ''));
        $staffId = trim((string) $request->input('staff_id', ''));

        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $base = PettyCashBatch::on('mysql_secondary')
            ->from('petty_cash_batch as pcb')
            ->join('voucher_master as vm', 'vm.vma_voucher_id', '=', 'pcb.vma_voucher_id')
            ->join('voucher_details as vd', 'vd.vma_voucher_id', '=', 'vm.vma_voucher_id')
            ->join('payment_record as pr', 'pr.pre_payment_no', '=', 'vd.vde_payment_no')
            ->when($confirmed, fn ($qry) => $qry->whereNotNull('pcb.pcb_receiveamt'))
            ->when(! $confirmed, fn ($qry) => $qry->whereNull('pcb.pcb_receiveamt'))
            ->when($staffId !== '', function ($qry) use ($staffId) {
                $qry->whereExists(function ($sub) use ($staffId) {
                    $sub->selectRaw('1')
                        ->from('petty_cash_details as pcd')
                        ->join('petty_cash_main as pcm', 'pcm.pcm_id', '=', 'pcd.pcm_id')
                        ->whereColumn('pcd.pcb_batch_id', 'pcb.pcb_batch_id')
                        ->where('pcm.pcm_holder_id', $staffId);
                });
            });

        if ($confirmed) {
            $base->leftJoin('staff as s', 's.stf_staff_id', '=', 'pcb.pcb_receiveby');
        }

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(pcb.pcb_batch_id,''),
                    IFNULL(pcb.pcb_batch_amt,''),
                    IFNULL(pcb.pcb_status,''),
                    IFNULL(pcb.pcb_approve_amt,''),
                    IFNULL(vm.vma_voucher_no,''),
                    IFNULL(vd.vde_payment_no,''),
                    IFNULL(pr.pre_total_amt,''))) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->distinct()->count('pcb.pcb_id');

        $select = [
            'pcb.pcb_id',
            'pcb.pcb_batch_id',
            'pcb.pcb_batch_amt',
            'pcb.pcb_status',
            'pcb.pcb_approve_amt',
            'vm.vma_voucher_no',
            'vd.vde_payment_no',
            'pr.pre_total_amt',
        ];

        if ($confirmed) {
            $select[] = 's.stf_staff_name';
            $select[] = 'pcb.pcb_receivedate';
        }

        $orderExpr = $confirmed ? 'pcb.pcb_receivedate' : 'pcb.pcb_batch_id';

        $rows = (clone $base)
            ->select($select)
            ->distinct()
            ->orderBy($orderExpr, $sortDir)
            ->orderBy('pcb.pcb_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit, $confirmed) {
            $pcbId = (int) $r->pcb_id;
            $status = (string) ($r->pcb_status ?? '');
            $qs = http_build_query([
                'mode' => 'view',
                'status' => $status,
                'pcb_id' => $pcbId,
            ]);

            $row = [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pcb_id' => $pcbId,
                'pcb_batch_id' => $r->pcb_batch_id,
                'pcb_batch_amt' => $r->pcb_batch_amt !== null ? (float) $r->pcb_batch_amt : null,
                'pcb_status' => $status,
                'pcb_approve_amt' => $r->pcb_approve_amt !== null ? (float) $r->pcb_approve_amt : null,
                'vma_voucher_no' => $r->vma_voucher_no,
                'vde_payment_no' => $r->vde_payment_no,
                'pre_total_amt' => $r->pre_total_amt !== null ? (float) $r->pre_total_amt : null,
                'url_view' => '/admin/kerisi/m/2424?'.$qs,
            ];

            if ($confirmed) {
                $row['stf_staff_name'] = $r->stf_staff_name;
                $row['pcb_receivedate'] = $r->pcb_receivedate
                    ? Carbon::parse($r->pcb_receivedate)->format('d/m/Y')
                    : '';
            }

            return $row;
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
