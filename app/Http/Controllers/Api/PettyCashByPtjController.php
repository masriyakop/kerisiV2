<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PettyCashMaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * List Petty Cash by PTJ (PAGEID 1963 / MENUID 2399).
 *
 * Source: FIMS BL `NAD_API_PC_PETTYCASHBYPTJ` (?dt_details=1). Joins
 * petty_cash_master + petty_cash_details + petty_cash_main via Eloquent.
 *
 * Legacy gating (`FLC_USER_GROUP_MAPPING` group 18 or nimda) is not ported:
 * the endpoint is exposed to any authenticated user with an all-rows view,
 * matching the approach taken by other FIMS list screens in this repo.
 * Action column in the frontend points to the yet-unmigrated Petty Cash
 * Claim Form (MENUID 1506).
 */
class PettyCashByPtjController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'pms_application_no' => 'pms.pms_application_no',
        'pms_request_date' => 'pms.pms_request_date',
        'pms_total_amt' => 'pms.pms_total_amt',
        'pms_return_amt' => 'pms.pms_return_amt',
        'pms_status' => 'pms.pms_status',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 5)));
        $q = trim((string) $request->input('q', ''));
        $sortByKey = (string) $request->input('sort_by', 'pms_application_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderExpr = self::SORTABLE[$sortByKey] ?? 'pms.pms_application_no';

        $base = PettyCashMaster::on('mysql_secondary')
            ->from('petty_cash_master as pms')
            ->join('petty_cash_details as pcd', 'pcd.pms_application_no', '=', 'pms.pms_application_no')
            ->join('petty_cash_main as pci', 'pci.pcm_id', '=', 'pcd.pcm_id')
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS('__',
                        IFNULL(pms.pms_application_no,''),
                        IFNULL(pms.pms_request_by,''),
                        IFNULL(DATE_FORMAT(pms.pms_request_date, '%d/%m/%Y'),''),
                        IFNULL(pms.pms_total_amt,''),
                        IFNULL(pms.pms_return_amt,''),
                        IFNULL(pms.pms_status,''))) LIKE ?",
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
                'pms.pms_return_amt',
                'pms.pms_status',
                'pci.pcm_id',
            ])
            ->distinct()
            ->orderBy($orderExpr, $sortDir)
            ->orderBy('pms.pms_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $pmsId = (int) $r->pms_id;
            $pcmId = (int) $r->pcm_id;
            $status = (string) ($r->pms_status ?? '');
            $qs = http_build_query([
                'mode' => 'view',
                'status' => $status,
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
                'pms_total_amt' => $r->pms_total_amt !== null ? (float) $r->pms_total_amt : null,
                'pms_return_amt' => $r->pms_return_amt !== null ? (float) $r->pms_return_amt : null,
                'pms_status' => $status,
                'url_view' => '/admin/kerisi/m/1506?'.$qs,
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
