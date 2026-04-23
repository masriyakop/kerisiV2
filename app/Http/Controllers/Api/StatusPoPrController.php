<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PurchaseOrderMaster;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Purchasing > Status PO & PR (PAGEID 1520 / MENUID 1841).
 *
 * Legacy BL: `ZR_PURCHASING_STATUSPOPR_API` (?dt_statusPOPR=1 + ?DownloadCSV=1).
 * Joins purchase_order_master + purchase_order_details + vend_customer_supplier
 * + bills_master + requisition_master, with an optional smart filter for
 * date range / PO no / PR no / Vendor code / PO status.
 *
 * Legacy PTJ gating: when PTJ = 'S10400' or user belongs to groups 22/271 the
 * query runs unscoped; otherwise it is scoped to the logged-in staff's own
 * PTJ via organization_unit.ou_bursar_flag != 'Y'. We approximate the legacy
 * PTJ gating through an optional `ou_code` query param (empty = unscoped).
 * This matches the approach taken for the Petty Cash Confirmation Payment
 * endpoint (see PettyCashConfirmPaymentController).
 */
class StatusPoPrController extends Controller
{
    use ApiResponse;

    private const CONN = 'mysql_secondary';

    private const SORTABLE = [
        'pom_order_no',
        'rqm_requisition_no',
        'pom_description',
        'pom_order_status',
        'itm_item_code',
        'pod_item_spec',
        'vcs_vendor_code',
        'vcs_vendor_name',
        'pom_request_date',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'pom_order_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'pom_order_no';
        }

        $base = $this->baseQuery($request);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(pm.pom_order_no,''),
                    IFNULL(pd.rqm_requisition_no,''),
                    IFNULL(pm.pom_description,''),
                    IFNULL(pd.itm_item_code,''),
                    IFNULL(pd.pod_item_spec,''),
                    IFNULL(pm.pom_order_status,''),
                    IFNULL(pm.vcs_vendor_code,''),
                    IFNULL(vc.vcs_vendor_name,''),
                    IFNULL(bm.bim_bills_no,''),
                    IFNULL(DATE_FORMAT(pm.pom_request_date, '%d/%m/%Y'),'')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->distinct()->count('pm.pom_order_id');

        $rows = (clone $base)
            ->select([
                'pm.pom_order_id',
                'rm.rqm_requisition_id',
                'pm.pom_order_no',
                'pd.rqm_requisition_no',
                'pm.pom_description',
                'pd.itm_item_code',
                'pd.pod_item_spec',
                'pm.pom_order_status',
                'pm.vcs_vendor_code',
                'vc.vcs_vendor_name',
                DB::raw("CASE
                    WHEN bm.pom_order_no IS NOT NULL THEN bm.bim_bills_no
                    WHEN bm.rqm_requisition_no IS NOT NULL THEN bm.bim_bills_no
                    ELSE NULL
                END AS bills"),
                'pm.pom_request_date',
            ])
            ->distinct()
            ->orderBy($sortBy, $sortDir)
            ->orderBy('pm.pom_order_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $requestDate = $r->pom_request_date
                ? Carbon::parse($r->pom_request_date)->format('d/m/Y')
                : '';

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pomOrderId' => $r->pom_order_id !== null ? (int) $r->pom_order_id : null,
                'rqmRequisitionId' => $r->rqm_requisition_id !== null ? (int) $r->rqm_requisition_id : null,
                'poNo' => $r->pom_order_no,
                'prNo' => $r->rqm_requisition_no,
                'description' => $r->pom_description,
                'itemCode' => $r->itm_item_code,
                'itemDesc' => $r->pod_item_spec,
                'poStatus' => $r->pom_order_status,
                'vendorCode' => $r->vcs_vendor_code,
                'vendorName' => $r->vcs_vendor_name,
                'billNo' => $r->bills,
                'requestDate' => $requestDate,
                // Legacy deep-links (menuID 1827 / 1771 are not yet migrated — these
                // resolve to the ComingSoon placeholder page on the Kerisi frontend).
                'urlViewPo' => $r->pom_order_id !== null
                    ? '/admin/kerisi/m/1827?pom_order_id='.(int) $r->pom_order_id
                    : null,
                'urlViewPr' => $r->rqm_requisition_id !== null
                    ? '/admin/kerisi/m/1771?rqm_requisition_id='.(int) $r->rqm_requisition_id
                    : null,
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
        $statuses = DB::connection(self::CONN)
            ->table('purchase_order_master')
            ->select('pom_order_status')
            ->whereNotNull('pom_order_status')
            ->distinct()
            ->orderBy('pom_order_status')
            ->pluck('pom_order_status');

        return $this->sendOk([
            'poStatus' => $statuses->values()->all(),
        ]);
    }

    private function baseQuery(Request $request): QueryBuilder
    {
        $ouCode = trim((string) $request->input('ou_code', ''));
        $poNo = trim((string) $request->input('pom_order_no', ''));
        $prNo = trim((string) $request->input('rqm_requisition_no', ''));
        $vendor = trim((string) $request->input('vcs_vendor_code', ''));
        $poStatus = trim((string) $request->input('pom_order_status', ''));
        $dateStart = trim((string) $request->input('date_start', ''));
        $dateEnd = trim((string) $request->input('date_end', ''));

        $query = PurchaseOrderMaster::query()
            ->from('purchase_order_master as pm')
            ->join('purchase_order_details as pd', 'pm.pom_order_id', '=', 'pd.pom_order_id')
            ->join('vend_customer_supplier as vc', 'pm.vcs_vendor_code', '=', 'vc.vcs_vendor_code')
            ->leftJoin('bills_master as bm', 'bm.pom_order_no', '=', 'pm.pom_order_no')
            ->join('requisition_master as rm', 'rm.rqm_requisition_no', '=', 'pd.rqm_requisition_no')
            ->when($ouCode !== '', function ($q) use ($ouCode) {
                $q->join('organization_unit as ou', 'pd.oun_code', '=', 'ou.oun_code')
                    ->where('ou.oun_code', $ouCode)
                    ->where(function ($b) {
                        $b->whereNull('ou.ou_bursar_flag')
                            ->orWhere('ou.ou_bursar_flag', '!=', 'Y');
                    });
            });

        if ($poNo !== '') {
            $like = $this->likeEscape(mb_strtolower($poNo, 'UTF-8'));
            $query->whereRaw("LOWER(IFNULL(pm.pom_order_no, '')) LIKE ?", [$like]);
        }
        if ($prNo !== '') {
            $like = $this->likeEscape(mb_strtolower($prNo, 'UTF-8'));
            $query->whereRaw("LOWER(IFNULL(pd.rqm_requisition_no, '')) LIKE ?", [$like]);
        }
        if ($vendor !== '') {
            $like = $this->likeEscape(mb_strtolower($vendor, 'UTF-8'));
            $query->whereRaw("LOWER(IFNULL(pm.vcs_vendor_code, '')) LIKE ?", [$like]);
        }
        if ($poStatus !== '') {
            $query->where('pm.pom_order_status', $poStatus);
        }

        [$from, $to] = $this->parseDateRange($dateStart, $dateEnd);
        if ($from !== null && $to !== null) {
            $query->whereBetween('pm.pom_request_date', [$from, $to]);
        } elseif ($from !== null) {
            $query->where('pm.pom_request_date', '>=', $from);
        } elseif ($to !== null) {
            $query->where('pm.pom_request_date', '<=', $to);
        }

        return $query->toBase();
    }

    /**
     * Accept either ISO (yyyy-mm-dd) or legacy dd/mm/yyyy strings.
     */
    private function parseDateRange(string $start, string $end): array
    {
        return [$this->parseDate($start, false), $this->parseDate($end, true)];
    }

    private function parseDate(string $value, bool $endOfDay): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $value)) {
                $d = Carbon::createFromFormat('d/m/Y', $value);
            } else {
                $d = Carbon::parse($value);
            }
        } catch (\Throwable) {
            return null;
        }
        if ($d === false || $d === null) {
            return null;
        }
        return ($endOfDay ? $d->endOfDay() : $d->startOfDay())->format('Y-m-d H:i:s');
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
