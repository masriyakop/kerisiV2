<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\DepositMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Credit Control > List of Deposit" (PAGEID 2568 / MENUID 3066).
 *
 * Source: FIMS BL `SNA_API_CC_LISTOFDEPOSIT`. Very similar to the MENUID
 * 1809 list, but restricted to rows where `deposit_details.acm_acct_code`
 * matches an `account_main` row with `acm_flag_subsidiary='Y' AND
 * acm_flag_deposit='Y'` and exposes a different top filter (Deposit
 * Category, Customer Type, Customer ID, PTJ list). Each row carries a
 * `dpmDepositMasterId` so the SPA can link to MENUID 3397 (Detail of
 * Deposit).
 *
 * Uses the deposit_master / deposit_details / account_main join; the
 * Customer ID dropdown unions vend_customer_supplier + staff +
 * vend_customer_supplier_myflite from the legacy `autoSuggestCustID`.
 */
class ListOfDepositController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dpm_deposit_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = [
            'transactiondate', 'dpm_deposit_no', 'dpm_payto_type', 'vcs_vendor_code',
            'dpm_vendor_name', 'dpm_ref_no', 'ddt_doc_no', 'fty_fund_type',
            'at_activity_code', 'oun_code', 'ccr_costcentre', 'acm_acct_code',
            'acm_acct_desc', 'ddt_currency_code', 'ddt_ent_amt', 'ddt_amt', 'ddt_type',
        ];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'dpm_deposit_no';
        }

        $sortColumn = match ($sortBy) {
            'transactiondate' => 'dm.createddate',
            'acm_acct_desc' => 'am.acm_acct_desc',
            'fty_fund_type', 'at_activity_code', 'oun_code', 'ccr_costcentre',
            'acm_acct_code', 'ddt_doc_no', 'ddt_currency_code', 'ddt_type',
            'ddt_amt', 'ddt_ent_amt' => 'dd.' . $sortBy,
            default => 'dm.' . $sortBy,
        };

        $base = $this->baseQuery($request);

        if ($q !== '') {
            $like = $this->likeEscape($q);
            $base->where(function (Builder $b) use ($like) {
                foreach ([
                    'dm.dpm_deposit_no', 'dm.vcs_vendor_code', 'dm.dpm_contract_no',
                    'dm.dpm_vendor_name', 'dm.dpm_payto_type', 'dm.dpm_ref_no',
                    'dd.ddt_doc_no', 'dd.ccr_costcentre', 'dd.acm_acct_code',
                    'dd.ddt_type', 'dd.oun_code', 'dd.fty_fund_type',
                    'dd.at_activity_code', 'dd.cpa_project_no', 'dm.dpm_status',
                ] as $col) {
                    $b->orWhereRaw('LOWER(IFNULL(' . $col . ', "")) LIKE ?', [$like]);
                }
            });
        }

        // Smart filter inputs
        $smart = [
            'smart_deposit_no' => 'dm.dpm_deposit_no',
            'smart_vendor_code' => 'dm.vcs_vendor_code',
            'smart_vendor_name' => 'dm.dpm_vendor_name',
            'smart_ref_no' => 'dm.dpm_ref_no',
            'smart_acct_code' => 'dd.acm_acct_code',
            'smart_amount' => 'dd.ddt_amt',
            'smart_fund_type' => 'dd.fty_fund_type',
        ];
        foreach ($smart as $param => $col) {
            if ($request->filled($param)) {
                $like = $this->likeEscape((string) $request->input($param));
                $base->whereRaw('LOWER(IFNULL(' . $col . ', "")) LIKE ?', [$like]);
            }
        }

        $totals = (clone $base)
            ->selectRaw("COUNT(*) as c, COALESCE(SUM(CASE WHEN dd.ddt_type='DT' THEN dd.ddt_amt ELSE -dd.ddt_amt END),0) as s")
            ->first();

        $total = (int) ($totals->c ?? 0);
        $signedSum = (float) ($totals->s ?? 0);

        $rows = (clone $base)
            ->selectRaw(<<<'SQL'
                dm.dpm_deposit_master_id,
                dm.createddate as transactiondate,
                dm.dpm_deposit_no,
                dm.vcs_vendor_code,
                dm.dpm_contract_no,
                dm.dpm_vendor_name,
                dm.dpm_payto_type,
                dm.dpm_ref_no,
                IF(dd.ddt_line_no NOT IN (1), IFNULL(dd.ddt_description, dd.ddt_transaction_ref), dm.dpm_ref_no_note) as dpm_ref_no_note,
                dd.ddt_currency_code,
                dd.ddt_ent_amt,
                dd.ddt_doc_no,
                dd.ccr_costcentre,
                dd.acm_acct_code,
                am.acm_acct_desc,
                dd.oun_code,
                dd.ddt_amt,
                dd.ddt_type,
                dd.fty_fund_type,
                dd.at_activity_code,
                dd.cpa_project_no,
                dm.dpm_status
            SQL)
            ->orderBy($sortColumn, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'dpmDepositMasterId' => (int) $r->dpm_deposit_master_id,
            'transactionDate' => $r->transactiondate ? \Illuminate\Support\Carbon::parse($r->transactiondate)->format('d/m/Y') : null,
            'dpmDepositNo' => $r->dpm_deposit_no,
            'dpmPaytoType' => $r->dpm_payto_type,
            'vcsVendorCode' => $r->vcs_vendor_code,
            'dpmVendorName' => $r->dpm_vendor_name,
            'dpmRefNo' => $r->dpm_ref_no,
            'dpmRefNoNote' => $r->dpm_ref_no_note,
            'ddtDocNo' => $r->ddt_doc_no,
            'ftyFundType' => $r->fty_fund_type,
            'atActivityCode' => $r->at_activity_code,
            'ounCode' => $r->oun_code,
            'ccrCostcentre' => $r->ccr_costcentre,
            'acmAcctCode' => $r->acm_acct_code,
            'acmAcctDesc' => $r->acm_acct_desc,
            'ddtCurrencyCode' => $r->ddt_currency_code,
            'ddtEntAmt' => (float) ($r->ddt_ent_amt ?? 0),
            'ddtAmt' => (float) ($r->ddt_amt ?? 0),
            'ddtType' => $r->ddt_type,
            'dpmStatus' => $r->dpm_status,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => [
                'ddtAmt' => $signedSum,
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        $db = 'mysql_secondary';

        $categories = DB::connection($db)
            ->table('deposit_category')
            ->orderBy('dct_category_code')
            ->get(['dct_category_code', 'dct_category_desc'])
            ->map(fn ($r) => [
                'id' => $r->dct_category_code,
                'label' => trim(($r->dct_category_code ?? '') . ' - ' . ($r->dct_category_desc ?? '')),
            ])
            ->values();

        $customerTypes = DB::connection($db)
            ->table('lookup_details')
            ->where('lma_code_name', 'CUSTOMER_TYPE')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => $r->lde_value,
                'label' => trim(($r->lde_value ?? '') . ' - ' . ($r->lde_description ?? '')),
            ])
            ->values();

        $ptjs = DB::connection($db)
            ->table('organization_unit')
            ->whereNotNull('oun_code')
            ->orderBy('oun_code')
            ->get(['oun_code', 'oun_desc'])
            ->map(fn ($r) => [
                'id' => $r->oun_code,
                'label' => trim(($r->oun_code ?? '') . ' - ' . ($r->oun_desc ?? '')),
            ])
            ->values();

        return $this->sendOk([
            'category' => $categories,
            'customerType' => $customerTypes,
            'ptj' => $ptjs,
        ]);
    }

    /**
     * Unified customer ID autosuggest mirroring legacy `autoSuggestCustID`.
     * Unions vend_customer_supplier + staff + vend_customer_supplier_myflite.
     */
    public function searchCustomer(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $limit = min(50, max(1, (int) $request->input('limit', 20)));

        $base = DB::connection('mysql_secondary')->table('vend_customer_supplier')
            ->selectRaw('vcs_vendor_code as id, UPPER(vcs_vendor_name) as name');

        $staff = DB::connection('mysql_secondary')->table('staff')
            ->selectRaw('stf_staff_id as id, stf_staff_name as name');

        $myflite = DB::connection('mysql_secondary')->table('vend_customer_supplier_myflite')
            ->selectRaw('vcs_vendor_code as id, UPPER(vcs_vendor_name) as name');

        $union = $base->unionAll($staff)->unionAll($myflite);

        $raw = DB::connection('mysql_secondary')
            ->query()
            ->fromSub($union, 'tbl')
            ->select(['id', 'name']);

        if ($q !== '') {
            $like = $this->likeEscape($q);
            $raw->where(function ($b) use ($like) {
                $b->orWhereRaw('LOWER(IFNULL(id, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(name, "")) LIKE ?', [$like]);
            });
        }

        $rows = $raw
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'label' => trim(($r->id ?? '') . ' - ' . ($r->name ?? '')),
                'name' => (string) ($r->name ?? ''),
            ])
            ->values();

        return $this->sendOk($rows);
    }

    // --------------------------------------------------------------------------

    private function baseQuery(Request $request): Builder
    {
        $q = DepositMaster::query()
            ->from('deposit_master as dm')
            ->join('deposit_details as dd', 'dd.dpm_deposit_master_id', '=', 'dm.dpm_deposit_master_id')
            ->join('account_main as am', function ($j) {
                $j->on('am.acm_acct_code', '=', 'dd.acm_acct_code')
                    ->where('am.acm_flag_subsidiary', 'Y')
                    ->where('am.acm_flag_deposit', 'Y');
            });

        if ($request->filled('category')) {
            $q->where('dm.dpm_deposit_category', $request->input('category'));
        }
        if ($request->filled('customer_type')) {
            $q->where('dm.dpm_payto_type', $request->input('customer_type'));
        }
        if ($request->filled('customer_id')) {
            $q->where('dm.vcs_vendor_code', $request->input('customer_id'));
        }
        if ($request->filled('ptj')) {
            $values = $this->asList($request->input('ptj'));
            if (! empty($values)) {
                $q->whereIn('dd.oun_code', $values);
            }
        }

        return $q;
    }

    private function asList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), fn ($v) => $v !== ''));
        }
        $s = trim((string) $value);
        if ($s === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $s)), fn ($v) => $v !== ''));
    }

    private function likeEscape(string $needle): string
    {
        $n = mb_strtolower($needle, 'UTF-8');

        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $n) . '%';
    }
}
