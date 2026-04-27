<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\BankMaster;
use App\Models\InvestmentInstitution;
use App\Models\InvestmentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Investment > Summary List of Investments (PAGEID 2316 / MENUID 2808).
 *
 * Source: FIMS BL `API_SUMMARY_LIST_OF_NEW_INVESTMENT`
 * (action=listing_all_dt). Read-only datatable joining
 * `investment_profile` with `investment_institution`. Scope matches
 * legacy SQL: `ipf_status IN ('APPROVE','WITHDRAW','PENDING')`.
 *
 * The legacy BL also LEFT JOINs voucher_master / voucher_details /
 * bills_master / bills_details, but the page's `dt_bi` (displayed
 * columns) is ["Batch No", "Institution", "Investment No/Cert No",
 * "Investment Type", "Fund Type", "Activity", "Tenure / Period
 * Duration", "Amount (RM)", "Rate (%)", "Status", "Action"] — none of
 * the voucher/bills columns are surfaced. Per CLAUDE.md rule 6
 * ("non-displayed columns should not be fetched"), those joins are
 * omitted so the query is simpler and faster with identical row set.
 *
 * The Smart Filter exposes 10 fields (Year of Batch, Batch No, Bank,
 * Institution, Investment Type, Fund Type, Activity, Tenure, Amount,
 * Status) matching the page JSON's form items exactly.
 *
 * Grand total (footer) = SUM(ipf_principal_amt) over the filtered
 * rows, returned in meta.grandTotal (same semantics as the legacy
 * `footer.Amount`).
 *
 * Action column deep-links to MENUID 2820 (investment detail summary
 * view) in the legacy system. That detail page is NOT migrated; the
 * Vue view renders a disabled View button until it lands.
 */
class SummaryListInvestmentsController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    /** Allowed sort keys, mapped to concrete SQL expressions in index(). */
    private const SORTABLE = [
        'dt_batch',
        'dt_institution',
        'dt_invest_no',
        'dt_invest_type',
        'dt_fund_type',
        'dt_activity',
        'dt_tenure',
        'dt_amount',
        'dt_rate',
        'dt_status',
    ];

    /** Status dropdown values per the page JSON (APPROVE / WITHDRAW). */
    private const STATUS_FILTER = ['APPROVE', 'WITHDRAW'];

    /** Scope statuses per the legacy BL. */
    private const STATUS_SCOPE = ['APPROVE', 'WITHDRAW', 'PENDING'];

    public function options(): JsonResponse
    {
        // Year of Batch — distinct 4-digit year from batch_no
        // (SUBSTR(...,-5,4) in the legacy SQL).
        $years = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->selectRaw('DISTINCT SUBSTR(ipf.ipf_batch_no, -5, 4) as year')
            ->whereIn('ipf.ipf_status', self::STATUS_SCOPE)
            ->whereNotNull('ipf.ipf_batch_no')
            ->orderByRaw('year DESC')
            ->pluck('year')
            ->filter(fn ($y) => $y !== null && $y !== '')
            ->map(fn ($y) => ['id' => (string) $y, 'label' => (string) $y])
            ->values();

        $batchNos = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->select('ipf_batch_no')
            ->whereIn('ipf.ipf_status', self::STATUS_SCOPE)
            ->whereNotNull('ipf.ipf_batch_no')
            ->where('ipf.ipf_batch_no', '!=', '')
            ->distinct()
            ->orderBy('ipf_batch_no', 'desc')
            ->pluck('ipf_batch_no')
            ->map(fn ($b) => ['id' => (string) $b, 'label' => (string) $b])
            ->values();

        // Banks — legacy lookup filters to bnm_branch_name IS NULL
        // (head-office rows only, same lookup as the page JSON).
        $banks = BankMaster::query()
            ->select(['bnm_bank_code', 'bnm_bank_desc'])
            ->whereNull('bnm_branch_name')
            ->whereNotNull('bnm_bank_code')
            ->where('bnm_bank_code', '!=', '')
            ->orderBy('bnm_bank_desc')
            ->get()
            ->map(fn ($b) => [
                'id' => (string) $b->bnm_bank_code,
                'label' => $b->bnm_bank_desc ?: (string) $b->bnm_bank_code,
            ])
            ->values();

        $institutions = InvestmentInstitution::query()
            ->select(['iit_inst_code', 'iit_inst_name', 'iit_bank_branch'])
            ->whereNotNull('iit_inst_code')
            ->where('iit_inst_code', '!=', '')
            ->orderBy('iit_inst_code')
            ->get()
            ->map(fn ($i) => [
                'id' => (string) $i->iit_inst_code,
                'label' => '['.$i->iit_inst_code.'] '
                    .($i->iit_inst_name ?? '')
                    .($i->iit_bank_branch ? ' - '.$i->iit_bank_branch : ''),
            ])
            ->values();

        $investmentTypes = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->selectRaw("DISTINCT ipf.ivt_type_code as code, ipf.ipf_extended_field->>'\$.ivt_type_desc' as label")
            ->whereIn('ipf.ipf_status', self::STATUS_SCOPE)
            ->whereNotNull('ipf.ivt_type_code')
            ->orderBy('ipf.ivt_type_code')
            ->get()
            ->filter(fn ($r) => $r->code !== null && $r->code !== '')
            ->map(fn ($r) => [
                'id' => (string) $r->code,
                'label' => $r->label ? $r->code.' - '.$r->label : (string) $r->code,
            ])
            ->values();

        $fundTypes = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->selectRaw("DISTINCT ipf.fty_fund_type as code, ipf.ipf_extended_field->>'\$.fty_fund_desc' as label")
            ->whereIn('ipf.ipf_status', self::STATUS_SCOPE)
            ->whereNotNull('ipf.fty_fund_type')
            ->orderBy('ipf.fty_fund_type')
            ->get()
            ->filter(fn ($r) => $r->code !== null && $r->code !== '')
            ->map(fn ($r) => [
                'id' => (string) $r->code,
                'label' => $r->label ? $r->code.' - '.$r->label : (string) $r->code,
            ])
            ->values();

        $activities = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->selectRaw("DISTINCT ipf.at_activity_code as code, ipf.ipf_extended_field->>'\$.at_activity_desc' as label")
            ->whereIn('ipf.ipf_status', self::STATUS_SCOPE)
            ->whereNotNull('ipf.at_activity_code')
            ->orderBy('ipf.at_activity_code')
            ->get()
            ->filter(fn ($r) => $r->code !== null && $r->code !== '')
            ->map(fn ($r) => [
                'id' => (string) $r->code,
                'label' => $r->label ? $r->code.' - '.$r->label : (string) $r->code,
            ])
            ->values();

        $tenures = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->selectRaw("DISTINCT CONCAT(IFNULL(ipf.ipf_estimated_period, ''), ' ', IFNULL(ipf.ipf_extended_field->>'\$.ipf_tenure_desc', '')) as tenure")
            ->whereIn('ipf.ipf_status', self::STATUS_SCOPE)
            ->orderBy('tenure')
            ->pluck('tenure')
            ->filter(fn ($t) => $t !== null && trim($t) !== '')
            ->map(fn ($t) => ['id' => trim($t), 'label' => trim($t)])
            ->values();

        $status = collect(self::STATUS_FILTER)
            ->map(fn ($s) => ['id' => $s, 'label' => $s])
            ->values();

        return $this->sendOk([
            'yearOfBatch' => $years,
            'batchNo' => $batchNos,
            'bank' => $banks,
            'institution' => $institutions,
            'investmentType' => $investmentTypes,
            'fundType' => $fundTypes,
            'activity' => $activities,
            'tenure' => $tenures,
            'status' => $status,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dt_batch');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'dt_batch';
        }

        $filterYear = trim((string) $request->input('filter_year', ''));
        $filterBatch = trim((string) $request->input('filter_batch', ''));
        $filterBank = trim((string) $request->input('filter_bank', ''));
        $filterInstitution = trim((string) $request->input('filter_institution', ''));
        $filterInvestType = trim((string) $request->input('filter_invest_type', ''));
        $filterFundType = trim((string) $request->input('filter_fund_type', ''));
        $filterActivity = trim((string) $request->input('filter_activity', ''));
        $filterTenure = trim((string) $request->input('filter_tenure', ''));
        $filterAmount = trim((string) $request->input('filter_amount', ''));
        $filterStatus = trim((string) $request->input('filter_status', ''));

        // Collation-safe cross-table comparisons — see CollationSafeSql
        // trait for rationale (handles utf8mb3 vs utf8mb4_0900_ai_ci
        // mismatches on mysql_secondary).
        $base = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->join('investment_institution as iit', function ($join) {
                $join->on(
                    DB::raw($this->cs('ipf.iit_inst_code')),
                    '=',
                    DB::raw($this->cs('iit.iit_inst_code')),
                );
            })
            ->whereIn('ipf.ipf_status', self::STATUS_SCOPE);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $concatParts = [
                $this->cs("IFNULL(ipf.ipf_batch_no, '')"),
                $this->cs("IFNULL(ipf.iit_inst_code, '')"),
                $this->cs("IFNULL(ipf.ipf_extended_field->>'$.iit_inst_desc', '')"),
                $this->cs("IFNULL(iit.iit_bank_branch, '')"),
                $this->cs("IFNULL(ipf.ipf_investment_no, '')"),
                $this->cs("IFNULL(ipf.ipf_certifcate_no, '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_estimated_period AS CHAR), '')"),
                $this->cs("IFNULL(ipf.ipf_extended_field->>'$.ipf_tenure_desc', '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_start_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_end_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_principal_amt AS CHAR), '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_rate AS CHAR), '')"),
                $this->cs("IFNULL(ipf.ipf_receipt_withdraw, '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_receipt_date_withdraw, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(ipf.ipf_status, '')"),
            ];
            $concat = "CONCAT_WS('__', ".implode(', ', $concatParts).')';
            $base->whereRaw("LOWER($concat) LIKE ?", [$like]);
        }

        // Smart filters — exact match on dropdown IDs, substring on
        // free-text inputs, matching the legacy setSmartFilter surface.
        if ($filterYear !== '') {
            $base->whereRaw('SUBSTR(ipf.ipf_batch_no, -5, 4) = ?', [$filterYear]);
        }
        if ($filterBatch !== '') {
            $base->whereRaw($this->cs('ipf.ipf_batch_no').' = ?', [$filterBatch]);
        }
        if ($filterBank !== '') {
            $base->whereRaw($this->cs('iit.bnm_bank_code').' = ?', [$filterBank]);
        }
        if ($filterInstitution !== '') {
            $base->whereRaw($this->cs('ipf.iit_inst_code').' = ?', [$filterInstitution]);
        }
        if ($filterInvestType !== '') {
            $base->whereRaw($this->cs('ipf.ivt_type_code').' = ?', [$filterInvestType]);
        }
        if ($filterFundType !== '') {
            $base->whereRaw($this->cs('ipf.fty_fund_type').' = ?', [$filterFundType]);
        }
        if ($filterActivity !== '') {
            $base->whereRaw($this->cs('ipf.at_activity_code').' = ?', [$filterActivity]);
        }
        if ($filterTenure !== '') {
            $tenureExpr = "TRIM(CONCAT(IFNULL(ipf.ipf_estimated_period, ''), ' ', IFNULL(ipf.ipf_extended_field->>'\$.ipf_tenure_desc', '')))";
            $base->whereRaw($this->cs($tenureExpr).' = ?', [$filterTenure]);
        }
        if ($filterAmount !== '') {
            // Keep the legacy substring match so "1000" hits 10000 and 100000.
            $like = $this->likeEscape(mb_strtolower($filterAmount, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(CAST(ipf.ipf_principal_amt AS CHAR), '')").') LIKE ?',
                [$like]
            );
        }
        if ($filterStatus !== '') {
            $base->whereRaw($this->cs('ipf.ipf_status').' = ?', [$filterStatus]);
        }

        $total = (clone $base)->count();
        $grandTotal = (float) (clone $base)->sum('ipf.ipf_principal_amt');

        $orderColumn = match ($sortBy) {
            'dt_batch' => 'ipf.ipf_batch_no',
            'dt_institution' => 'ipf.iit_inst_code',
            'dt_invest_no' => 'ipf.ipf_investment_no',
            'dt_invest_type' => 'ipf.ivt_type_code',
            'dt_fund_type' => 'ipf.fty_fund_type',
            'dt_activity' => 'ipf.at_activity_code',
            'dt_tenure' => 'ipf.ipf_estimated_period',
            'dt_amount' => 'ipf.ipf_principal_amt',
            'dt_rate' => 'ipf.ipf_rate',
            'dt_status' => 'ipf.ipf_status',
            default => 'ipf.ipf_batch_no',
        };

        $rows = (clone $base)
            ->select([
                'ipf.ipf_investment_id',
                'ipf.ipf_batch_no',
                'ipf.iit_inst_code',
                DB::raw("ipf.ipf_extended_field->>'\$.iit_inst_desc' as inst_desc"),
                'iit.iit_inst_name',
                'iit.iit_bank_branch',
                'ipf.ipf_investment_no',
                'ipf.ipf_certifcate_no',
                'ipf.ivt_type_code',
                DB::raw("ipf.ipf_extended_field->>'\$.ivt_type_desc' as invest_type_desc"),
                'ipf.fty_fund_type',
                DB::raw("ipf.ipf_extended_field->>'\$.fty_fund_desc' as fund_type_desc"),
                'ipf.at_activity_code',
                DB::raw("ipf.ipf_extended_field->>'\$.at_activity_desc' as activity_desc"),
                'ipf.ipf_estimated_period',
                DB::raw("ipf.ipf_extended_field->>'\$.ipf_tenure_desc' as tenure_desc"),
                DB::raw("DATE_FORMAT(ipf.ipf_start_date, '%d/%m/%Y') as start_date_fmt"),
                DB::raw("DATE_FORMAT(ipf.ipf_end_date, '%d/%m/%Y') as end_date_fmt"),
                'ipf.ipf_principal_amt',
                'ipf.ipf_rate',
                'ipf.ipf_status',
            ])
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('ipf.ipf_investment_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'investmentId' => (int) $r->ipf_investment_id,
            'batchNo' => $r->ipf_batch_no,
            'institutionCode' => $r->iit_inst_code,
            'institutionName' => $r->iit_inst_name ?? $r->inst_desc,
            'institutionBranch' => $r->iit_bank_branch,
            'investmentNo' => $r->ipf_investment_no,
            'certificateNo' => $r->ipf_certifcate_no,
            'investmentTypeCode' => $r->ivt_type_code,
            'investmentTypeDesc' => $r->invest_type_desc,
            'fundTypeCode' => $r->fty_fund_type,
            'fundTypeDesc' => $r->fund_type_desc,
            'activityCode' => $r->at_activity_code,
            'activityDesc' => $r->activity_desc,
            'period' => $r->ipf_estimated_period !== null ? (int) $r->ipf_estimated_period : null,
            'tenureDesc' => $r->tenure_desc,
            'startDate' => $r->start_date_fmt,
            'endDate' => $r->end_date_fmt,
            'principalAmount' => $r->ipf_principal_amt !== null
                ? (float) $r->ipf_principal_amt
                : null,
            'rate' => $r->ipf_rate !== null ? (float) $r->ipf_rate : null,
            'status' => $r->ipf_status,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'grandTotal' => $grandTotal,
        ]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
