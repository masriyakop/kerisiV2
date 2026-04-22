<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\OnlinePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Vendor Portal > Online Registration Fee History" endpoint
 * (PAGEID 1654 / MENUID 2003).
 *
 * # Source
 * The authoritative BL `NF_BL_VENDOR_ONLINE_PAYMENT` is NOT present in the
 * available PAGE_SECOND_LEVEL_MENU.json feed. This implementation is
 * reconstructed from two surviving artefacts:
 *
 *   - the frontend datatable column spec (keys opa_checkout_time,
 *     opa_status_desc, vcs_vendor_code, vcs_vendor_name, vcs_vendor_status),
 *   - the commented-out SQL block embedded inside BL
 *     NF_BL_PURCHASING_VENDOR_PORTAL_TENDER (PAGEID 2278, lines 463-532),
 *     which shows the legacy joins onto online_payment + receipt_master
 *     and the numeric-to-label mapping for opa_status.
 *
 * Anything that depends on encrypted tokens (downloadReceipt,
 * confirmNotPending) or external calls to the payment gateway (re-query
 * pending payments) is deliberately OUT OF SCOPE here — those surfaces
 * mutate data and have no reproducible source. The listing is read-only.
 *
 * # Scoping
 * Legacy page accepts URL query params `creditorId` + `creditorName` and
 * defaults to the authenticated user. Both behaviours are preserved: an
 * optional `creditor_id` param wins; otherwise `auth()->user()->name` is
 * used as the payee ID (matching FIMS' "username === vendor code"
 * convention).
 */
class VendorRegistrationFeeHistoryController extends Controller
{
    use ApiResponse;

    /**
     * Numeric opa_status → human label mapping (legacy CASE expression).
     */
    private const STATUS_LABELS = [
        '1' => 'Successful',
        '2' => 'Pending',
        '3' => 'Unsuccessful',
        '4' => 'Pending Authorization',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'opa_checkout_time');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $creditorId = $this->creditorId($request);

        if ($creditorId === null) {
            return $this->sendOk([], [
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'totalPages' => 0,
            ]);
        }

        $allowedSort = [
            'opa_checkout_time', 'opa_payee_id', 'opa_payee_name',
            'opa_status', 'opa_transaction_amount', 'opa_reference_no',
        ];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'opa_checkout_time';
        }

        $query = OnlinePayment::query()
            ->from('online_payment as opa')
            ->leftJoin('receipt_master as rma', 'opa.opa_receipt_master_id', '=', 'rma.rma_receipt_master_id')
            ->where('opa.opa_payee_id', $creditorId)
            ->select([
                'opa.opa_online_payment_id',
                'opa.opa_reference_no',
                'opa.opa_checkout_time',
                'opa.opa_transaction_time',
                'opa.opa_transaction_amount',
                'opa.opa_payee_id',
                'opa.opa_payee_name',
                DB::raw("IFNULL(opa.opa_status, '2') as opa_status"),
                'opa.opa_desc',
                'rma.rma_receipt_no',
            ]);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function ($b) use ($like) {
                foreach ([
                    'opa.opa_reference_no',
                    'opa.opa_payee_id',
                    'opa.opa_payee_name',
                    'opa.opa_desc',
                    'rma.rma_receipt_no',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL($col, '')) LIKE ?", [$like]);
                }
                $b->orWhereRaw("DATE_FORMAT(opa.opa_checkout_time, '%d/%m/%Y') LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count('opa.opa_online_payment_id');

        $rows = $query
            ->orderBy('opa.' . $sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $statusCode = (string) $r->opa_status;
            $statusLabel = self::STATUS_LABELS[$statusCode] ?? 'Pending';
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'checkoutDate' => $r->opa_checkout_time,
                'transactionDate' => $r->opa_transaction_time,
                'referenceNo' => $r->opa_reference_no,
                'receiptNo' => $r->rma_receipt_no,
                'creditorId' => $r->opa_payee_id,
                'vendorName' => $r->opa_payee_name,
                'description' => $r->opa_desc,
                'transactionAmount' => $r->opa_transaction_amount !== null
                    ? (float) $r->opa_transaction_amount
                    : null,
                'statusCode' => $statusCode,
                'statusDesc' => $statusLabel,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'creditorId' => $creditorId,
        ]);
    }

    /**
     * Resolve creditor ID from the explicit ?creditor_id= param,
     * falling back to the authenticated user's login name.
     */
    private function creditorId(Request $request): ?string
    {
        $override = trim((string) $request->input('creditor_id', ''));
        if ($override !== '') {
            return $override;
        }
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
