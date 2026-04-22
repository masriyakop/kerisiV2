<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\TenderMaster;
use App\Models\VendCustomerSupplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "Vendor Portal > Tender/Quotation List" endpoint
 * (PAGEID 2278 / MENUID 2767).
 *
 * Source: FIMS BL `NF_BL_PURCHASING_VENDOR_PORTAL_TENDER`. Two actions are
 * ported:
 *
 *   1. ?ListOfTender=1 → paginated list of APPROVE tenders whose briefing
 *      close date is still in the future. Legacy adds an `editable` flag
 *      that is true when NOW() falls between tdm_tender_open_start and
 *      tdm_tender_open_close — the same flag is emitted here.
 *
 *   2. ?check=1 → returns the blacklist/inactive status of the logged-in
 *      vendor (used by the UI to block the Buy Document flow). The legacy
 *      BL queries vend_customer_supplier where vcs_vendor_status IN
 *      ('0', 'BLACKLIST') and returns `bankInfo`. That semantics is
 *      preserved under `checkVendorStatus` here.
 *
 * Scoping: the vendor identity comes from the Laravel auth user's `name`
 * (treated as vcs_vendor_code — same mapping used by legacy FIMS). The
 * tender listing itself is not scoped to a single vendor; the scoping
 * only applies to the check endpoint.
 *
 * Not implemented: the legacy `tender_briefing` EXISTS clauses that would
 * hide tenders already signed-up-for by someone else. They are commented
 * out in the original BL (`/* maz komen *\/`) so the authoritative
 * behaviour matches what ships here.
 */
class TenderQuotationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'tdm_briefing_close_peti');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSort = [
            'tdm_tender_no', 'tdm_tender_type', 'tdm_title',
            'tdm_start_date', 'tdm_end_date',
            'tdm_estimated_amount', 'tdm_amount_doc',
            'tdm_status', 'tdm_briefing_ref_no',
            'tdm_briefing_close_peti', 'tdm_tender_open_start',
            'tdm_tender_open_close',
        ];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'tdm_briefing_close_peti';
        }

        $query = TenderMaster::query()
            ->from('tender_master as tdm')
            ->where('tdm.tdm_status', 'APPROVE')
            ->whereRaw('DATE(NOW()) <= tdm.tdm_briefing_close_peti')
            ->select([
                'tdm.tdm_tender_id',
                'tdm.tdm_tender_no',
                'tdm.tdm_briefing_ref_no',
                'tdm.tdm_tender_type',
                'tdm.tdm_start_date',
                'tdm.tdm_end_date',
                'tdm.tdm_title',
                'tdm.tdm_estimated_amount',
                'tdm.tdm_amount_doc',
                'tdm.tdm_status',
                'tdm.tdm_briefing_close_peti',
                'tdm.tdm_tender_open_start',
                'tdm.tdm_tender_open_close',
            ])
            ->distinct();

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function ($b) use ($like) {
                foreach ([
                    'tdm.tdm_tender_no',
                    'tdm.tdm_tender_type',
                    'tdm.tdm_title',
                    'tdm.tdm_status',
                    'tdm.tdm_briefing_ref_no',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL($col, '')) LIKE ?", [$like]);
                }
                foreach ([
                    'tdm.tdm_estimated_amount',
                    'tdm.tdm_amount_doc',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL(CAST($col AS CHAR), '')) LIKE ?", [$like]);
                }
                $b->orWhereRaw("DATE_FORMAT(tdm.tdm_start_date, '%d/%m/%Y') LIKE ?", [$like]);
                $b->orWhereRaw("DATE_FORMAT(tdm.tdm_end_date, '%d/%m/%Y') LIKE ?", [$like]);
                $b->orWhereRaw("DATE_FORMAT(tdm.tdm_briefing_close_peti, '%d/%m/%Y') LIKE ?", [$like]);
                $b->orWhereRaw("DATE_FORMAT(tdm.tdm_tender_open_start, '%d/%m/%Y') LIKE ?", [$like]);
                $b->orWhereRaw("DATE_FORMAT(tdm.tdm_tender_open_close, '%d/%m/%Y') LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count('tdm.tdm_tender_id');

        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $now = Carbon::now();
        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit, $now) {
            $open = $r->tdm_tender_open_start ? Carbon::parse($r->tdm_tender_open_start) : null;
            $close = $r->tdm_tender_open_close ? Carbon::parse($r->tdm_tender_open_close) : null;
            $editable = $open !== null
                && $close !== null
                && $now->greaterThanOrEqualTo($open)
                && $now->lessThanOrEqualTo($close);
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'tenderId' => $r->tdm_tender_id,
                'tenderNo' => $r->tdm_tender_no,
                'briefingRefNo' => $r->tdm_briefing_ref_no,
                'tenderType' => $r->tdm_tender_type,
                'startDate' => $r->tdm_start_date,
                'endDate' => $r->tdm_end_date,
                'title' => $r->tdm_title,
                'estimatedAmount' => $r->tdm_estimated_amount !== null ? (float) $r->tdm_estimated_amount : null,
                'amountDoc' => $r->tdm_amount_doc !== null ? (float) $r->tdm_amount_doc : null,
                'status' => $r->tdm_status,
                'briefingCloseDate' => $r->tdm_briefing_close_peti,
                'tenderOpenStart' => $r->tdm_tender_open_start,
                'tenderOpenClose' => $r->tdm_tender_open_close,
                'editable' => $editable,
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
     * Report whether the logged-in vendor is inactive ('0') or blacklisted.
     * Mirrors the legacy ?check=1 branch.
     */
    public function checkVendorStatus(Request $request): JsonResponse
    {
        $vendorCode = $this->vendorCode($request);
        $status = null;

        if ($vendorCode !== null) {
            $row = VendCustomerSupplier::query()
                ->where('vcs_vendor_code', $vendorCode)
                ->whereIn('vcs_vendor_status', ['0', 'BLACKLIST'])
                ->value('vcs_vendor_status');
            $status = $row ?: null;
        }

        return $this->sendOk([
            'vendorCode' => $vendorCode,
            'restrictedStatus' => $status,
            'canBuyDocument' => $status === null,
        ]);
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
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
    }
}
