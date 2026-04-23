<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PettyCashMaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * List of Release Paid — Petty Cash (PAGEID 2273 / MENUID 2761).
 *
 * Source: FIMS BL `NAD_API_PC_LISTOFRELEASEPAID`.
 *   - ?dt_applications=1 — application-level PAID rows (one per pms_id / pcm_id)
 *   - ?dt_receipts=1&pms_id=... — receipt/line-level PAID rows for one application
 *
 * Both views are restricted to rows where pms_status = 'PAID', pcd_status =
 * 'PAID' and pcd_paid_date IS NOT NULL. Legacy holder-scoped gating is
 * exposed through an optional `staff_id` query param.
 */
class PettyCashReleasePaidController extends Controller
{
    use ApiResponse;

    public function applications(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 5)));
        $q = trim((string) $request->input('q', ''));
        $staffId = trim((string) $request->input('staff_id', ''));

        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $base = PettyCashMaster::on('mysql_secondary')
            ->from('petty_cash_master as pms')
            ->join('petty_cash_details as pcd', 'pcd.pms_application_no', '=', 'pms.pms_application_no')
            ->where('pms.pms_status', 'PAID')
            ->where('pcd.pcd_status', 'PAID')
            ->whereNotNull('pcd.pcd_paid_date')
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
                        IFNULL(pms.pms_pay_to_id,''),
                        IFNULL(pms.pms_status,''))) LIKE ?",
                    [$like]
                );
            });

        $groupBy = ['pms.pms_id', 'pcd.pcm_id', 'pms.pms_application_no', 'pms.pms_request_by', 'pms.pms_request_date', 'pms.pms_pay_to_id', 'pcd.pcd_paid_date', 'pms.pms_return_amt', 'pms.pms_status'];

        $total = (clone $base)
            ->select(DB::raw('COUNT(DISTINCT pms.pms_id, pcd.pcm_id) as c'))
            ->value('c');

        $rows = (clone $base)
            ->select([
                'pms.pms_id',
                'pms.pms_application_no',
                'pcd.pcm_id',
                'pms.pms_request_by',
                'pms.pms_request_date',
                'pms.pms_pay_to_id',
                'pcd.pcd_paid_date',
                DB::raw('SUM(pcd.pcd_trans_amt) as pms_total_amt'),
                'pms.pms_return_amt',
                'pms.pms_status',
            ])
            ->groupBy($groupBy)
            ->orderBy('pcd.pcd_paid_date', $sortDir)
            ->orderBy('pms.pms_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $pmsId = (int) $r->pms_id;
            $pcmId = (int) $r->pcm_id;
            $qs = http_build_query([
                'mode' => 'view',
                'pms_id' => $pmsId,
                'pcm_id' => $pcmId,
            ]);

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pms_id' => $pmsId,
                'pcm_id' => $pcmId,
                'pms_application_no' => $r->pms_application_no,
                'pms_request_by' => $r->pms_request_by,
                'pms_request_date' => $r->pms_request_date ? Carbon::parse($r->pms_request_date)->format('d/m/Y') : '',
                'pms_pay_to_id' => $r->pms_pay_to_id,
                'pcd_paid_date' => $r->pcd_paid_date ? Carbon::parse($r->pcd_paid_date)->format('d/m/Y') : '',
                'pms_total_amt' => $r->pms_total_amt !== null ? (float) $r->pms_total_amt : null,
                'pms_return_amt' => $r->pms_return_amt !== null ? (float) $r->pms_return_amt : null,
                'pms_status' => $r->pms_status,
                'url_view' => '/admin/kerisi/m/2761?'.$qs,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => (int) ($total ?? 0),
            'totalPages' => (int) ceil(((int) ($total ?? 0)) / max(1, $limit)),
        ]);
    }

    public function receipts(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(200, (int) $request->input('limit', 25)));
        $q = trim((string) $request->input('q', ''));
        $pmsId = (int) $request->input('pms_id', 0);

        if ($pmsId <= 0) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'pms_id is required', [
                'pms_id' => ['pms_id is required'],
            ]);
        }

        $base = PettyCashMaster::on('mysql_secondary')
            ->from('petty_cash_master as pms')
            ->join('petty_cash_details as pcd', 'pcd.pms_application_no', '=', 'pms.pms_application_no')
            ->where('pms.pms_id', $pmsId)
            ->where('pms.pms_status', 'PAID')
            ->where('pcd.pcd_status', 'PAID')
            ->whereNotNull('pcd.pcd_paid_date')
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS('__',
                        IFNULL(pcd.pcd_receipt_no,''),
                        IFNULL(pms.pms_application_no,''),
                        IFNULL(pms.pms_request_by,''),
                        IFNULL(pms.pms_pay_to_id,''))) LIKE ?",
                    [$like]
                );
            });

        $total = (clone $base)->count('pcd.pcd_id');

        $rows = (clone $base)
            ->select([
                'pms.pms_id',
                'pcd.pcm_id',
                'pms.pms_application_no',
                'pcd.pcd_receipt_no',
                'pcd.pcd_paid_date',
                'pms.pms_request_by',
                'pms.pms_request_date',
                'pms.pms_pay_to_id',
                'pcd.pcd_trans_amt',
                'pms.pms_status',
            ])
            ->orderBy('pcd.pcd_paid_date', 'desc')
            ->orderBy('pcd.pcd_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pms_id' => (int) $r->pms_id,
                'pcm_id' => (int) $r->pcm_id,
                'pms_application_no' => $r->pms_application_no,
                'pcd_receipt_no' => $r->pcd_receipt_no,
                'pcd_paid_date' => $r->pcd_paid_date ? Carbon::parse($r->pcd_paid_date)->format('d/m/Y') : '',
                'pms_request_by' => $r->pms_request_by,
                'pms_request_date' => $r->pms_request_date ? Carbon::parse($r->pms_request_date)->format('d/m/Y') : '',
                'pms_pay_to_id' => $r->pms_pay_to_id,
                'pcd_trans_amt' => $r->pcd_trans_amt !== null ? (float) $r->pcd_trans_amt : null,
                'pms_status' => $r->pms_status,
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
