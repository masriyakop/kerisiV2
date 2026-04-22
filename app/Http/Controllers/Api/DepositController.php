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
 * "Credit Control > Deposit" listing (PAGEID 1473 / MENUID 1809).
 *
 * Source: FIMS BL `ZR_CREDITCONTROL_DEPOSIT_API` (the `$_GET['dt_deposit']`
 * branch). It joins `deposit_master` × `deposit_details` × `deposit_category`
 * and renders one row per detail line. Filtering happens both via a global
 * `q` search (mirrors CONCAT_WS LIKE) and via smart-filter keys (deposit no,
 * vendor code, ref no, acct code, amount, fund type). Top filters (deposit
 * category, pay-to-type, transaction type, currency, PTJ list, date range)
 * follow the same shape.
 *
 * The read-only footer returns the signed sum of `ddt_amt` (CR rows negated),
 * matching the legacy `SELECT SUM(CASE WHEN ddt_type='DT' THEN ddt_amt ELSE
 * -ddt_amt END) …` block.
 *
 * Lookup helpers (`options()`, `autosuggestDepositNo()`, …) back the
 * filter dropdowns and Smart-Filter comboboxes used on the page.
 */
class DepositController extends Controller
{
    use ApiResponse;

    /**
     * List deposit transaction rows (1809 default datatable).
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'date_transaction');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'date_transaction', 'dpm_payto_type', 'vcs_vendor_code', 'dpm_vendor_name',
            'dpm_ref_no', 'ddt_doc_no', 'fty_fund_type', 'at_activity_code',
            'oun_code', 'ccr_costcentre', 'acm_acct_code', 'ddt_currency_code',
            'ddt_conversion_rate', 'ddt_type', 'ddt_amt', 'ddt_ent_amt',
        ];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'date_transaction';
        }

        // Column alias resolver for ORDER BY (date_transaction lives under dd.createddate)
        $sortColumn = match ($sortBy) {
            'date_transaction' => 'dd.createddate',
            'fty_fund_type', 'at_activity_code', 'oun_code', 'ccr_costcentre',
            'acm_acct_code', 'ddt_doc_no', 'ddt_currency_code', 'ddt_conversion_rate',
            'ddt_type', 'ddt_amt', 'ddt_ent_amt' => 'dd.' . $sortBy,
            default => $sortBy,
        };

        $base = $this->baseQuery($request);

        if ($q !== '') {
            $like = $this->likeEscape($q);
            $base->where(function (Builder $b) use ($like) {
                foreach ([
                    'dm.vcs_vendor_code', 'dm.dpm_deposit_no', 'dm.dpm_contract_no',
                    'dm.dpm_vendor_name', 'dm.dpm_payto_type', 'dm.dpm_ref_no',
                    'dd.ddt_doc_no', 'dd.ccr_costcentre', 'dd.acm_acct_code',
                    'dd.ddt_type', 'dd.oun_code', 'dd.fty_fund_type',
                    'dd.at_activity_code', 'dd.cpa_project_no',
                ] as $col) {
                    $b->orWhereRaw('LOWER(IFNULL(' . $col . ', "")) LIKE ?', [$like]);
                }
            });
        }

        // ---- Smart filter (legacy `smartFilter.*`) --------------------------------
        $this->applySmartFilter($base, $request);

        // ---- Footer: signed sum of ddt_amt (CR negated) --------------------------
        $totals = (clone $base)
            ->selectRaw("COUNT(*) as c, COALESCE(SUM(CASE WHEN dd.ddt_type='DT' THEN dd.ddt_amt ELSE -dd.ddt_amt END),0) as s")
            ->first();

        $total = (int) ($totals->c ?? 0);
        $signedSum = (float) ($totals->s ?? 0);

        $rows = (clone $base)
            ->selectRaw(<<<'SQL'
                dm.dpm_deposit_master_id,
                dm.dpm_deposit_no,
                dm.vcs_vendor_code,
                dm.dpm_contract_no,
                dm.dpm_vendor_name,
                dm.dpm_payto_type,
                dm.dpm_ref_no,
                IF(dd.ddt_line_no NOT IN (1), IFNULL(dd.ddt_description, dd.ddt_transaction_ref), dm.dpm_ref_no_note) as dpm_ref_no_note,
                dd.ddt_doc_no,
                dd.ccr_costcentre,
                dd.acm_acct_code,
                DATE_FORMAT(dd.createddate, '%d-%m-%Y') as date_transaction,
                dd.oun_code,
                CASE WHEN dd.ddt_type='DT' THEN dd.ddt_amt ELSE -dd.ddt_amt END as ddt_amt,
                dd.ddt_type,
                dd.fty_fund_type,
                dd.at_activity_code,
                dd.cpa_project_no,
                dd.ddt_currency_code,
                dd.ddt_conversion_rate,
                CASE WHEN dd.ddt_type='DT' THEN dd.ddt_ent_amt ELSE -dd.ddt_ent_amt END as ddt_ent_amt
            SQL)
            ->orderBy($sortColumn, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'dpmDepositMasterId' => (int) $r->dpm_deposit_master_id,
            'dpmDepositNo' => $r->dpm_deposit_no,
            'dateTransaction' => $r->date_transaction,
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
            'ddtCurrencyCode' => $r->ddt_currency_code,
            'ddtConversionRate' => $r->ddt_conversion_rate !== null ? (float) $r->ddt_conversion_rate : null,
            'ddtType' => $r->ddt_type,
            'ddtAmt' => (float) ($r->ddt_amt ?? 0),
            'ddtEntAmt' => (float) ($r->ddt_ent_amt ?? 0),
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

    /**
     * Filter + smart-filter + autosuggest dropdown data.
     */
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

        $payToTypes = DB::connection($db)
            ->table('lookup_details')
            ->where('lma_code_name', 'CUSTOMER_TYPE')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => $r->lde_value,
                'label' => trim(($r->lde_value ?? '') . ' - ' . ($r->lde_description ?? '')),
            ])
            ->values();

        $currencies = DB::connection($db)
            ->table('currency_master')
            ->whereNotNull('cym_currency_code')
            ->orderBy('cym_currency_code')
            ->get(['cym_currency_code', 'cym_currency_desc'])
            ->map(fn ($r) => [
                'id' => $r->cym_currency_code,
                'label' => trim(($r->cym_currency_code ?? '') . ' - ' . ($r->cym_currency_desc ?? '')),
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
            'payToType' => $payToTypes,
            'transactionType' => [
                ['id' => 'DT', 'label' => 'Debit (DT)'],
                ['id' => 'CR', 'label' => 'Credit (CR)'],
            ],
            'currency' => $currencies,
            'ptj' => $ptjs,
        ]);
    }

    /**
     * Autosuggest used by the Smart Filter comboboxes.
     */
    public function autosuggest(Request $request): JsonResponse
    {
        $field = (string) $request->input('field', '');
        $q = trim((string) $request->input('q', ''));

        $allowed = [
            'dpm_deposit_no' => 'dm.dpm_deposit_no',
            'vcs_vendor_code' => 'dm.vcs_vendor_code',
            'dpm_ref_no' => 'dm.dpm_ref_no',
            'acm_acct_code' => 'dd.acm_acct_code',
            'fty_fund_type' => 'dd.fty_fund_type',
        ];
        if (! isset($allowed[$field])) {
            return $this->sendError(400, 'BAD_REQUEST', 'Unsupported autosuggest field');
        }

        $col = $allowed[$field];
        $query = DB::connection('mysql_secondary')
            ->table('deposit_master as dm')
            ->join('deposit_details as dd', 'dm.dpm_deposit_master_id', '=', 'dd.dpm_deposit_master_id')
            ->whereNotNull(DB::raw($col))
            ->whereRaw("$col <> ''");

        if ($q !== '') {
            $like = $this->likeEscape($q);
            $query->whereRaw("LOWER($col) LIKE ?", [$like]);
        }

        $rows = $query
            ->select(DB::raw("DISTINCT $col as value"))
            ->orderBy('value')
            ->limit(50)
            ->get()
            ->map(fn ($r) => ['id' => $r->value, 'label' => $r->value])
            ->values();

        return $this->sendOk($rows);
    }

    // --------------------------------------------------------------------------
    // Helpers
    // --------------------------------------------------------------------------

    private function baseQuery(Request $request): Builder
    {
        $q = DepositMaster::query()
            ->from('deposit_master as dm')
            ->join('deposit_details as dd', 'dm.dpm_deposit_master_id', '=', 'dd.dpm_deposit_master_id')
            ->join('deposit_category as dc', 'dc.dct_category_code', '=', 'dm.dpm_deposit_category');

        // ---- Top filter ---------------------------------------------------------
        if ($request->filled('category')) {
            $q->where('dc.dct_category_code', $request->input('category'));
        }
        if ($request->filled('pay_to_type')) {
            $values = $this->asList($request->input('pay_to_type'));
            if (! empty($values)) {
                $q->whereIn('dm.dpm_payto_type', $values);
            }
        }
        if ($request->filled('transaction_type')) {
            $q->where('dd.ddt_type', $request->input('transaction_type'));
        }
        if ($request->filled('currency')) {
            $q->where('dd.ddt_currency_code', $request->input('currency'));
        }
        if ($request->filled('ptj')) {
            $values = $this->asList($request->input('ptj'));
            if (! empty($values)) {
                $q->whereIn('dd.oun_code', $values);
            }
        }
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $q->whereRaw("DATE_FORMAT(dd.createddate,'%Y/%m/%d') BETWEEN STR_TO_DATE(?,'%d/%m/%Y') AND STR_TO_DATE(?,'%d/%m/%Y')", [
                (string) $request->input('date_from'),
                (string) $request->input('date_to'),
            ]);
        } elseif ($request->filled('date_from')) {
            $q->whereRaw("DATE_FORMAT(dd.createddate,'%Y/%m/%d') >= STR_TO_DATE(?,'%d/%m/%Y')", [
                (string) $request->input('date_from'),
            ]);
        } elseif ($request->filled('date_to')) {
            $q->whereRaw("DATE_FORMAT(dd.createddate,'%Y/%m/%d') <= STR_TO_DATE(?,'%d/%m/%Y')", [
                (string) $request->input('date_to'),
            ]);
        }

        return $q;
    }

    private function applySmartFilter(Builder $q, Request $request): void
    {
        $map = [
            'smart_deposit_no' => 'dm.dpm_deposit_no',
            'smart_vendor_code' => 'dm.vcs_vendor_code',
            'smart_vendor_name' => 'dm.dpm_vendor_name',
            'smart_ref_no' => 'dm.dpm_ref_no',
            'smart_acct_code' => 'dd.acm_acct_code',
            'smart_amount' => 'dd.ddt_amt',
            'smart_fund_type' => 'dd.fty_fund_type',
        ];

        foreach ($map as $param => $col) {
            if (! $request->filled($param)) {
                continue;
            }
            $like = $this->likeEscape((string) $request->input($param));
            $q->whereRaw('LOWER(IFNULL(' . $col . ', "")) LIKE ?', [$like]);
        }
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
