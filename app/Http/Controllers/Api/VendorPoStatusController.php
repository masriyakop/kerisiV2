<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vendor Portal > Purchase Order Status (PAGEID 1664 / MENUID 2015).
 *
 * Source: legacy FIMS BL `NF_BL_VENDOR_PO_STATUS`. The legacy page is a
 * read-only datatable for the logged-in vendor that joins
 * `purchase_order_master` LEFT JOIN `goods_receive_master` (by
 * pom_order_no) and aggregates GRN no / amount as a per-row badge string.
 *
 * Scoping mirrors `TenderQuotationController::vendorCode()`: the
 * authenticated user's `name` is treated as `vcs_vendor_code` (existing
 * FIMS convention — the SPA Sanctum login binds the legacy username into
 * `users.name`). All queries are parameterised via the query builder; no
 * legacy raw SQL is preserved.
 */
class VendorPoStatusController extends Controller
{
    use ApiResponse;

    private const CONN = 'mysql_secondary';

    private const SORTABLE = [
        'createddate',
        'pom_order_no',
        'pom_description',
        'pom_order_amt',
        'pom_order_status',
        'pom_available_date',
    ];

    public function index(Request $request): JsonResponse
    {
        $vendorCode = $this->vendorCode($request);
        if ($vendorCode === null) {
            return $this->sendOk([], [
                'page' => 1, 'limit' => 0, 'total' => 0, 'totalPages' => 0,
                'footer' => ['pomOrderAmt' => 0, 'grmTotalAmt' => 0],
            ]);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'createddate');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'createddate';
        }

        $base = $this->baseQuery($vendorCode, $request);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(DATE_FORMAT(pom.createddate, '%d/%m/%Y'), ''),
                    IFNULL(pom.pom_order_no, ''),
                    IFNULL(pom.pom_description, ''),
                    IFNULL(pom.pom_order_amt, ''),
                    IFNULL(pom.pom_order_status, ''),
                    IFNULL(DATE_FORMAT(pom.pom_available_date, '%d/%m/%Y'), '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->distinct()->count('pom.pom_order_id');

        $rows = (clone $base)
            ->select([
                'pom.pom_order_id',
                'pom.createddate',
                'pom.pom_order_no',
                'pom.pom_description',
                'pom.pom_order_amt',
                'pom.pom_order_status',
                'pom.pom_available_date',
                DB::raw('SUM(grm.grm_total_amt) AS grm_total_amt_sum'),
                DB::raw("GROUP_CONCAT(
                    CASE WHEN grm.grm_receive_no IS NULL THEN NULL
                    ELSE CONCAT(grm.grm_receive_no, '|', IFNULL(grm.grm_total_amt, 0))
                    END
                    SEPARATOR ';;'
                ) AS grn_concat"),
            ])
            ->groupBy([
                'pom.pom_order_id', 'pom.createddate', 'pom.pom_order_no',
                'pom.pom_description', 'pom.pom_order_amt', 'pom.pom_order_status',
                'pom.pom_available_date',
            ])
            ->orderBy('pom.'.$sortBy, $sortDir)
            ->orderBy('pom.pom_order_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'orderId' => (int) $r->pom_order_id,
                'createdDate' => $r->createddate,
                'orderNo' => $r->pom_order_no,
                'description' => $r->pom_description,
                'orderAmount' => $r->pom_order_amt !== null ? (float) $r->pom_order_amt : null,
                'orderStatus' => $r->pom_order_status,
                'availableDate' => $r->pom_available_date,
                'grnTotalAmount' => $r->grm_total_amt_sum !== null ? (float) $r->grm_total_amt_sum : null,
                'grns' => $this->parseGrnConcat($r->grn_concat),
            ];
        });

        // Footer aggregates over the current filter (not the paginated page).
        $footer = $this->footerAggregate($vendorCode, $request, $q);

        return $this->sendOk($data->values()->all(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => $footer,
        ]);
    }

    private function baseQuery(string $vendorCode, Request $request): QueryBuilder
    {
        $b = DB::connection(self::CONN)
            ->table('purchase_order_master as pom')
            ->leftJoin('goods_receive_master as grm', 'pom.pom_order_no', '=', 'grm.pom_order_no')
            ->where('pom.vcs_vendor_code', $vendorCode);

        $createdFrom = trim((string) $request->input('created_date_from', ''));
        $createdTo = trim((string) $request->input('created_date_to', ''));
        $availFrom = trim((string) $request->input('available_date_from', ''));
        $availTo = trim((string) $request->input('available_date_to', ''));
        $orderNo = trim((string) $request->input('pom_order_no', ''));
        $orderType = trim((string) $request->input('pom_order_type', ''));
        $orderStatus = trim((string) $request->input('pom_order_status', ''));
        $amtFrom = trim((string) $request->input('pom_order_amt_from', ''));
        $amtTo = trim((string) $request->input('pom_order_amt_to', ''));

        if ($createdFrom !== '') {
            $b->whereRaw("pom.createddate >= STR_TO_DATE(?, '%d/%m/%Y')", [$createdFrom]);
        }
        if ($createdTo !== '') {
            $b->whereRaw("pom.createddate <= STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')", [$createdTo.' 23:59:59']);
        }
        if ($availFrom !== '') {
            $b->whereRaw("pom.pom_available_date >= STR_TO_DATE(?, '%d/%m/%Y')", [$availFrom]);
        }
        if ($availTo !== '') {
            $b->whereRaw("pom.pom_available_date <= STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')", [$availTo.' 23:59:59']);
        }
        if ($orderNo !== '') {
            $b->where('pom.pom_order_no', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $orderNo).'%');
        }
        if ($orderType !== '') {
            $b->where('pom.pom_order_type', $orderType);
        }
        if ($orderStatus !== '') {
            $b->where('pom.pom_order_status', $orderStatus);
        }
        if ($amtFrom !== '' && is_numeric(str_replace(',', '', $amtFrom))) {
            $b->where('pom.pom_order_amt', '>=', (float) str_replace(',', '', $amtFrom));
        }
        if ($amtTo !== '' && is_numeric(str_replace(',', '', $amtTo))) {
            $b->where('pom.pom_order_amt', '<=', (float) str_replace(',', '', $amtTo));
        }

        return $b;
    }

    /**
     * Compute filter-scoped totals for the footer (matches legacy SUM
     * footer queries).
     *
     * @return array{pomOrderAmt:float, grmTotalAmt:float}
     */
    private function footerAggregate(string $vendorCode, Request $request, string $q): array
    {
        $base = $this->baseQuery($vendorCode, $request);
        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(DATE_FORMAT(pom.createddate, '%d/%m/%Y'), ''),
                    IFNULL(pom.pom_order_no, ''),
                    IFNULL(pom.pom_description, ''),
                    IFNULL(pom.pom_order_amt, ''),
                    IFNULL(pom.pom_order_status, ''),
                    IFNULL(DATE_FORMAT(pom.pom_available_date, '%d/%m/%Y'), '')
                )) LIKE ?",
                [$like]
            );
        }

        // pom_order_amt: SUM over distinct PO numbers (no GRN cartesian).
        $orderAmt = DB::connection(self::CONN)
            ->table('purchase_order_master')
            ->whereIn('pom_order_no', (clone $base)->distinct()->pluck('pom.pom_order_no'))
            ->sum('pom_order_amt');

        // grm_total_amt: SUM over the joined GRN rows that exist for those POs.
        $grmTotal = (clone $base)
            ->whereNotNull('grm.grm_receive_no')
            ->sum('grm.grm_total_amt');

        return [
            'pomOrderAmt' => (float) ($orderAmt ?? 0),
            'grmTotalAmt' => (float) ($grmTotal ?? 0),
        ];
    }

    /**
     * @return array<int, array{receiveNo:string, totalAmount:float|null}>
     */
    private function parseGrnConcat(?string $concat): array
    {
        if ($concat === null || $concat === '') {
            return [];
        }
        $out = [];
        foreach (explode(';;', $concat) as $piece) {
            $piece = trim((string) $piece);
            if ($piece === '') {
                continue;
            }
            $parts = explode('|', $piece);
            $out[] = [
                'receiveNo' => (string) ($parts[0] ?? ''),
                'totalAmount' => isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : null,
            ];
        }

        return $out;
    }

    private function vendorCode(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        $code = trim((string) ($user->name ?? ''));

        return $code === '' ? null : $code;
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
