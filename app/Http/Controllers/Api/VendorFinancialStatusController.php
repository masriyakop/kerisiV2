<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vendor Portal > Financial Status (PAGEID 1714 / MENUID 2072).
 *
 * Source: legacy FIMS BL `NF_BL_PURCHASING_FINANCIAL_STATUS`. The page
 * surfaces three read-only datatables for the logged-in vendor:
 *
 *   - billings   : bills_master + bills_details, scoped by bim_payto_id
 *   - vouchers   : voucher_master + voucher_details (vde_payto_type='C'
 *                  & acct group LIKE '%BANK%'), scoped by vde_payto_id
 *   - payments   : payment_record + voucher_master + voucher_details,
 *                  scoped by pre_payto_id
 *
 * All three are scoped to the authenticated user's `name` (FIMS
 * username == vendor code, same convention as TenderQuotationController
 * and VendorPoStatusController).
 */
class VendorFinancialStatusController extends Controller
{
    use ApiResponse;

    private const CONN = 'mysql_secondary';

    private const SORTABLE_BILLS = [
        'bill_voucher_no', 'bill_ref_no', 'bill_desc',
        'bill_received_date', 'bill_amount', 'bill_status',
    ];

    private const SORTABLE_VOUCHERS = [
        'vou_voucher_no', 'vou_desc', 'vou_date',
        'vou_status', 'vou_amount', 'vou_ref_no',
    ];

    private const SORTABLE_PAYMENTS = [
        'pay_voucher_no', 'pay_desc', 'pay_ep_cheque',
        'pay_mode_type', 'pay_amount', 'pay_trans_date',
        'pay_collection_mode', 'pay_status_eft', 'pay_ref_no',
    ];

    public function billings(Request $request): JsonResponse
    {
        $vendorCode = $this->vendorCode($request);
        if ($vendorCode === null) {
            return $this->emptyEnvelope(['billAmount' => 0]);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = $this->sortBy($request, self::SORTABLE_BILLS, 'bill_received_date');
        $sortDir = $this->sortDir($request, 'desc');

        $rawSortColumn = $this->billingsSortRaw($sortBy);

        $base = DB::connection(self::CONN)
            ->table('bills_master as a')
            ->join('bills_details as b', 'a.bim_bills_id', '=', 'b.bim_bills_id')
            ->where('b.bid_trans_type', 'DT')
            ->where('a.bim_payto_id', $vendorCode)
            ->whereIn('a.bim_status', ['APPROVE', 'APPROVED']);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(a.bim_voucher_no,''),
                    IFNULL(a.bim_bills_no,''),
                    IFNULL(a.bim_bills_desc,''),
                    IFNULL(DATE_FORMAT(a.bim_received_date,'%d/%m/%Y'),''),
                    IFNULL(a.bim_status,'')
                )) LIKE ?",
                [$like]
            );
        }

        $groupBy = [
            'a.bim_voucher_no', 'a.bim_bills_no', 'a.bim_bills_desc',
            'a.bim_received_date', 'a.bim_status', 'b.bid_payto_id',
        ];

        $totalRows = (clone $base)
            ->select(DB::raw('COUNT(*) AS C'))
            ->fromSub(function ($q1) {
                $q1->fromSub(function ($q2) {
                    // no-op
                }, 'unused');
            }, 'unused');
        // simpler total: count distinct grouping tuples
        $total = (int) DB::connection(self::CONN)
            ->table(DB::raw('('.(clone $base)->groupBy($groupBy)->selectRaw('1')->toSql().') as cnt'))
            ->mergeBindings((clone $base)->getQuery())
            ->count();

        $rows = (clone $base)
            ->groupBy($groupBy)
            ->select([
                DB::raw('a.bim_voucher_no AS bill_voucher_no'),
                DB::raw('a.bim_bills_no AS bill_ref_no'),
                DB::raw('a.bim_bills_desc AS bill_desc'),
                DB::raw("DATE_FORMAT(a.bim_received_date,'%d/%m/%Y') AS bill_received_date"),
                DB::raw('SUM(b.bid_amt) AS bill_amount'),
                DB::raw('a.bim_status AS bill_status'),
                DB::raw('b.bid_payto_id AS bill_pay_to_id'),
                DB::raw('a.bim_received_date AS bill_received_date_raw'),
            ])
            ->orderByRaw("$rawSortColumn $sortDir")
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $totalAmount = (float) (clone $base)->sum(DB::raw('b.bid_amt'));

        $data = $rows->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'voucherNo' => $r->bill_voucher_no,
                'refNo' => $r->bill_ref_no,
                'description' => $r->bill_desc,
                'receivedDate' => $r->bill_received_date,
                'amount' => (float) $r->bill_amount,
                'status' => $r->bill_status,
                'payToId' => $r->bill_pay_to_id,
            ];
        })->all();

        return $this->sendOk($data, [
            'page' => $page, 'limit' => $limit, 'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => ['billAmount' => $totalAmount],
        ]);
    }

    public function vouchers(Request $request): JsonResponse
    {
        $vendorCode = $this->vendorCode($request);
        if ($vendorCode === null) {
            return $this->emptyEnvelope(['vouAmount' => 0]);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = $this->sortBy($request, self::SORTABLE_VOUCHERS, 'vou_date');
        $sortDir = $this->sortDir($request, 'desc');

        $rawSortColumn = $this->vouchersSortRaw($sortBy);

        // Sub-select for "bank" account codes (legacy: acm_acct_group LIKE '%BANK%').
        $bankAcctSub = DB::connection(self::CONN)
            ->table('account_main')
            ->whereRaw('UPPER(acm_acct_group) LIKE ?', ['%BANK%'])
            ->select('acm_acct_code');

        $base = DB::connection(self::CONN)
            ->table('voucher_master as a')
            ->join('voucher_details as b', 'a.vma_voucher_id', '=', 'b.vma_voucher_id')
            ->where('b.vde_payto_type', 'C')
            ->whereIn('b.acm_acct_code', $bankAcctSub)
            ->where('b.vde_payto_id', $vendorCode)
            ->whereIn('a.vma_vch_status', ['APPROVE', 'APPROVED']);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(a.vma_voucher_no,''),
                    IFNULL(a.vma_vch_description,''),
                    IFNULL(b.vde_status,''),
                    IFNULL(b.bim_bills_no,'')
                )) LIKE ?",
                [$like]
            );
        }

        $groupBy = [
            'b.vde_payto_id', 'b.vde_payto_type', 'a.vma_voucher_no',
            'a.vma_vch_description', 'a.createddate', 'b.vde_status',
            'b.bim_bills_no', 'b.vde_payment_no',
        ];

        $total = (int) DB::connection(self::CONN)
            ->table(DB::raw('('.(clone $base)->groupBy($groupBy)->selectRaw('1')->toSql().') as cnt'))
            ->mergeBindings((clone $base)->getQuery())
            ->count();

        $rows = (clone $base)
            ->groupBy($groupBy)
            ->select([
                DB::raw('a.vma_voucher_no AS vou_voucher_no'),
                DB::raw('a.vma_vch_description AS vou_desc'),
                DB::raw("DATE_FORMAT(a.createddate,'%d/%m/%Y') AS vou_date"),
                DB::raw('a.createddate AS vou_date_raw'),
                DB::raw('b.vde_status AS vou_status'),
                DB::raw('SUM(b.vde_amount) AS vou_amount'),
                DB::raw('b.bim_bills_no AS vou_ref_no'),
                DB::raw('b.vde_payment_no AS vou_payment_no'),
                DB::raw('b.vde_payto_id AS vou_pay_to_id'),
                DB::raw('b.vde_payto_type AS vou_pay_to_type'),
            ])
            ->orderByRaw("$rawSortColumn $sortDir")
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $totalAmount = (float) (clone $base)->sum(DB::raw('b.vde_amount'));

        $data = $rows->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'voucherNo' => $r->vou_voucher_no,
                'description' => $r->vou_desc,
                'date' => $r->vou_date,
                'status' => $r->vou_status,
                'amount' => (float) $r->vou_amount,
                'refNo' => $r->vou_ref_no,
                'paymentNo' => $r->vou_payment_no,
                'payToId' => $r->vou_pay_to_id,
                'payToType' => $r->vou_pay_to_type,
            ];
        })->all();

        return $this->sendOk($data, [
            'page' => $page, 'limit' => $limit, 'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => ['vouAmount' => $totalAmount],
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $vendorCode = $this->vendorCode($request);
        if ($vendorCode === null) {
            return $this->emptyEnvelope(['payAmount' => 0]);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = $this->sortBy($request, self::SORTABLE_PAYMENTS, 'pay_ep_cheque');
        $sortDir = $this->sortDir($request, 'desc');

        $rawSortColumn = $this->paymentsSortRaw($sortBy);

        $base = DB::connection(self::CONN)
            ->table('payment_record as a')
            ->leftJoin('voucher_master as b', 'b.vma_voucher_no', '=', 'a.pre_voucher_no')
            ->leftJoin('voucher_details as c', function ($j) {
                $j->on('c.vma_voucher_id', '=', 'b.vma_voucher_id')
                    ->whereRaw('(c.vde_payto_id = a.pre_payto_id OR c.vde_factoring_id = a.pre_payto_id)');
            })
            ->where('a.pre_payto_id', $vendorCode)
            ->whereIn('a.pre_status', ['APPROVE', 'APPROVED']);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(a.pre_voucher_no,''),
                    IFNULL(b.vma_vch_description,''),
                    IFNULL(a.pre_payment_no,''),
                    IFNULL(a.pre_mod_type,''),
                    IFNULL(a.pre_total_amt_rm,''),
                    IFNULL(DATE_FORMAT(IFNULL(a.pre_sign_date, a.pre_bankin_date),'%d/%m/%Y'),''),
                    IFNULL(a.pre_collect_mode,''),
                    IFNULL(a.pre_status,''),
                    IFNULL(c.bim_bills_no,'')
                )) LIKE ?",
                [$like]
            );
        }

        // The legacy SQL uses SELECT DISTINCT and counts distinct vde_payto_id.
        // We replicate by selecting DISTINCT on the projection columns.
        $rowsBase = (clone $base)
            ->selectRaw('DISTINCT
                c.vde_payto_id AS pay_pay_to_id,
                c.vde_payto_type AS pay_pay_to_type,
                a.pre_voucher_no AS pay_voucher_no,
                b.vma_vch_description AS pay_desc,
                a.pre_payment_no AS pay_ep_cheque,
                a.pre_mod_type AS pay_mode_type,
                a.pre_total_amt_rm AS pay_amount,
                DATE_FORMAT(IFNULL(a.pre_sign_date, a.pre_bankin_date), \'%d/%m/%Y\') AS pay_trans_date,
                IFNULL(a.pre_sign_date, a.pre_bankin_date) AS pay_trans_date_raw,
                a.pre_mod_type AS pay_collection_mode,
                a.pre_status AS pay_status_eft,
                c.bim_bills_no AS pay_ref_no');

        $total = (int) DB::connection(self::CONN)
            ->table(DB::raw('('.$rowsBase->toSql().') as t'))
            ->mergeBindings($rowsBase)
            ->count();

        $rows = DB::connection(self::CONN)
            ->table(DB::raw('('.$rowsBase->toSql().') as t'))
            ->mergeBindings($rowsBase)
            ->orderByRaw("$rawSortColumn $sortDir")
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $totalAmount = (float) DB::connection(self::CONN)
            ->table(DB::raw('('.$rowsBase->toSql().') as t'))
            ->mergeBindings($rowsBase)
            ->sum('pay_amount');

        $data = $rows->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'voucherNo' => $r->pay_voucher_no,
                'description' => $r->pay_desc,
                'epChequeNo' => $r->pay_ep_cheque,
                'modeType' => $r->pay_mode_type,
                'amount' => $r->pay_amount !== null ? (float) $r->pay_amount : null,
                'transDate' => $r->pay_trans_date,
                'collectionMode' => $r->pay_collection_mode,
                'statusEft' => $r->pay_status_eft,
                'refNo' => $r->pay_ref_no,
                'payToId' => $r->pay_pay_to_id,
                'payToType' => $r->pay_pay_to_type,
            ];
        })->all();

        return $this->sendOk($data, [
            'page' => $page, 'limit' => $limit, 'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => ['payAmount' => $totalAmount],
        ]);
    }

    private function billingsSortRaw(string $sortBy): string
    {
        return match ($sortBy) {
            'bill_voucher_no' => 'a.bim_voucher_no',
            'bill_ref_no' => 'a.bim_bills_no',
            'bill_desc' => 'a.bim_bills_desc',
            'bill_received_date' => 'a.bim_received_date',
            'bill_amount' => 'SUM(b.bid_amt)',
            'bill_status' => 'a.bim_status',
            default => 'a.bim_received_date',
        };
    }

    private function vouchersSortRaw(string $sortBy): string
    {
        return match ($sortBy) {
            'vou_voucher_no' => 'a.vma_voucher_no',
            'vou_desc' => 'a.vma_vch_description',
            'vou_date' => 'a.createddate',
            'vou_status' => 'b.vde_status',
            'vou_amount' => 'SUM(b.vde_amount)',
            'vou_ref_no' => 'b.bim_bills_no',
            default => 'a.createddate',
        };
    }

    private function paymentsSortRaw(string $sortBy): string
    {
        return match ($sortBy) {
            'pay_voucher_no' => 'pay_voucher_no',
            'pay_desc' => 'pay_desc',
            'pay_ep_cheque' => 'pay_ep_cheque',
            'pay_mode_type' => 'pay_mode_type',
            'pay_amount' => 'pay_amount',
            'pay_trans_date' => 'pay_trans_date_raw',
            'pay_collection_mode' => 'pay_collection_mode',
            'pay_status_eft' => 'pay_status_eft',
            'pay_ref_no' => 'pay_ref_no',
            default => 'pay_ep_cheque',
        };
    }

    private function emptyEnvelope(array $footer): JsonResponse
    {
        return $this->sendOk([], [
            'page' => 1, 'limit' => 0, 'total' => 0, 'totalPages' => 0, 'footer' => $footer,
        ]);
    }

    /**
     * @param  array<int,string>  $allowed
     */
    private function sortBy(Request $request, array $allowed, string $default): string
    {
        $sb = (string) $request->input('sort_by', $default);

        return in_array($sb, $allowed, true) ? $sb : $default;
    }

    private function sortDir(Request $request, string $default): string
    {
        $sd = strtolower((string) $request->input('sort_dir', $default));

        return in_array($sd, ['asc', 'desc'], true) ? $sd : $default;
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
