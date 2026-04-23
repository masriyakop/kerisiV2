<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PettyCashBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bill Petty Cash list (PAGEID 1964 / MENUID 2400).
 *
 * Source: FIMS BL `NAD_API_PC_PETTYCASHBILL` (?dt_details=1). Lists petty
 * cash batches that are ENDORSE-status and have no linked bill number yet,
 * so finance can attach them to a bill. Joins via Eloquent on
 * petty_cash_batch + petty_cash_details.
 *
 * Legacy gating (FLC_USER_GROUP_MAPPING group 15 or pcm_holder_id = user
 * staff id) is not ported yet. Optional `staff_id` query param narrows the
 * list to batches linked to a petty_cash_main row for that holder.
 */
class PettyCashBillController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'pcb_batch_id' => 'pcb.pcb_batch_id',
        'pcb_batch_amt' => 'pcb.pcb_batch_amt',
        'pcb_status' => 'pcb.pcb_status',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 5)));
        $q = trim((string) $request->input('q', ''));
        $staffId = trim((string) $request->input('staff_id', ''));

        $sortByKey = (string) $request->input('sort_by', 'pcb_batch_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderExpr = self::SORTABLE[$sortByKey] ?? 'pcb.pcb_batch_id';

        $base = PettyCashBatch::on('mysql_secondary')
            ->from('petty_cash_batch as pcb')
            ->join('petty_cash_details as pcd', 'pcd.pcb_batch_id', '=', 'pcb.pcb_batch_id')
            ->where('pcb.pcb_status', 'ENDORSE')
            ->whereNull('pcb.bim_bills_no')
            ->when($staffId !== '', function ($qry) use ($staffId) {
                $qry->whereExists(function ($sub) use ($staffId) {
                    $sub->select(DB::raw('1'))
                        ->from('petty_cash_main as pcm')
                        ->whereColumn('pcm.pcm_id', 'pcd.pcm_id')
                        ->where('pcm.pcm_holder_id', $staffId);
                });
            })
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS('__',
                        IFNULL(pcb.pcb_batch_id,''),
                        IFNULL(pcb.pcb_batch_amt,''),
                        IFNULL(pcb.pcb_status,''))) LIKE ?",
                    [$like]
                );
            });

        $total = (clone $base)->distinct()->count('pcb.pcb_id');

        $rows = (clone $base)
            ->select([
                'pcb.pcb_id',
                'pcb.pcb_batch_id',
                'pcb.pcb_batch_amt',
                'pcb.pcb_status',
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
            $qs = http_build_query([
                'mode' => 'view',
                'status' => $status,
                'pcb_id' => $pcbId,
            ]);

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pcb_id' => $pcbId,
                'pcb_batch_id' => $r->pcb_batch_id,
                'pcb_batch_amt' => $r->pcb_batch_amt !== null ? (float) $r->pcb_batch_amt : null,
                'pcb_status' => $status,
                'url_view' => '/admin/kerisi/m/1504?'.$qs,
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
