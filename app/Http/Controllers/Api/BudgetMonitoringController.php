<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Budget;
use App\Models\CostCentre;
use App\Models\FundType;
use App\Models\OrganizationUnit;
use App\Services\BudgetMonitoringQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only Budget Monitoring (PAGEID 1201, MENUID 1471) list screen.
 *
 * This controller stays ORM-only per the project rule ("No Raw SQL…" /
 * CLAUDE.md Forbidden Pattern #10). SQL-function heavy work (CONCAT_WS,
 * IFNULL, SUM inside SELECT / GROUP BY) is quarantined in
 * {@see BudgetMonitoringQueryService}; this class only orchestrates.
 */
class BudgetMonitoringController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BudgetMonitoringQueryService $monitoring,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(500, (int) $request->input('limit', 10)));

        $sortBy = (string) $request->input('sort_by', 'budgetid');
        if (! in_array($sortBy, BudgetMonitoringQueryService::SORTABLE, true)) {
            $sortBy = 'budgetid';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc'));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $rows = $this->monitoring->rows($request, $page, $limit, $sortBy, $sortDir);
        $total = $this->monitoring->total($request);
        $totals = $this->monitoring->grandTotals($request);

        $data = $rows->values()->map(function (object $r, int $i) use ($page, $limit): array {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'sbg_budget_id' => $r->sbg_budget_id,
                'bdg_year' => $r->bdg_year,
                'bdg_status' => $r->bdg_status,
                'budgetid' => $r->budgetid,
                'bdg_bal_carryforward' => (float) $r->bdg_bal_carryforward,
                'bdg_topup_amt' => (float) $r->bdg_topup_amt,
                'bdg_initial_amt' => (float) $r->bdg_initial_amt,
                'bdg_additional_amt' => (float) $r->bdg_additional_amt,
                'bdg_virement_amt' => (float) $r->bdg_virement_amt,
                'bdg_allocated_amt' => (float) $r->bdg_allocated_amt,
                'bdg_lock_amt' => (float) $r->bdg_lock_amt,
                'bdg_pre_request_amt' => (float) $r->bdg_pre_request_amt,
                'bdg_request_amt' => (float) $r->bdg_request_amt,
                'bdg_commit_amt' => (float) $r->bdg_commit_amt,
                'bdg_expenses_amt' => (float) $r->bdg_expenses_amt,
                'bdg_balance_amt' => (float) $r->bdg_balance_amt,
                'fty_fund_type' => $r->fty_fund_type,
                'fty_fund_desc' => $r->fty_fund_desc,
                'at_activity_code' => $r->at_activity_code,
                'at_activity_desc' => $r->at_activity_description_bm,
                'oun_code' => $r->oun_code,
                'oun_desc' => $r->oun_desc,
                'ccr_costcentre' => $r->ccr_costcentre,
                'ccr_costcentre_desc' => $r->ccr_costcentre_desc,
                'lbc_budget_code' => $r->lbc_budget_code,
                'acm_acct_desc' => $r->acm_acct_desc,
                'bdg_closing' => $r->bdg_closing,
                'bdg_closing_by' => $r->bdg_closing_by,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => $totals,
        ]);
    }

    /**
     * Dropdown feeders for the top filter + smart filter. Sourced from
     * the same models the index query joins, so the choices stay in
     * sync. All queries are pure ORM — no raw SQL.
     */
    public function options(Request $request): JsonResponse
    {
        $years = Budget::query()
            ->select('bdg_year')
            ->distinct()
            ->whereNotNull('bdg_year')
            ->orderByDesc('bdg_year')
            ->pluck('bdg_year')
            ->filter()
            ->map(fn ($y) => ['id' => (string) $y, 'label' => (string) $y])
            ->values();

        $statuses = Budget::query()
            ->select('bdg_status')
            ->distinct()
            ->whereNotNull('bdg_status')
            ->orderBy('bdg_status')
            ->pluck('bdg_status')
            ->filter()
            ->map(fn ($s) => ['id' => (string) $s, 'label' => (string) $s])
            ->values();

        $funds = FundType::query()
            ->select('fty_fund_type', 'fty_fund_desc')
            ->where(function ($q) {
                $q->whereNull('fty_status')->orWhere('fty_status', 1);
            })
            ->orderBy('fty_fund_type')
            ->get()
            ->map(fn ($f) => [
                'id' => (string) $f->fty_fund_type,
                'label' => trim(($f->fty_fund_type ?? '').' - '.($f->fty_fund_desc ?? '')),
            ])
            ->values();

        $ptjLevels = OrganizationUnit::query()
            ->select('oun_level')
            ->distinct()
            ->whereNotNull('oun_level')
            ->orderBy('oun_level')
            ->pluck('oun_level')
            ->filter()
            ->map(fn ($l) => ['id' => (string) $l, 'label' => (string) $l])
            ->values();

        $ptjs = OrganizationUnit::query()
            ->select('oun_code', 'oun_desc', 'oun_level')
            ->where(function ($q) {
                $q->whereNull('oun_status')->orWhere('oun_status', 1);
            })
            ->orderBy('oun_code')
            ->limit(2000)
            ->get()
            ->map(fn ($o) => [
                'id' => (string) $o->oun_code,
                'label' => trim(($o->oun_code ?? '').' - '.($o->oun_desc ?? '')),
                'level' => $o->oun_level,
            ])
            ->values();

        $costCentres = CostCentre::query()
            ->select('ccr_costcentre', 'ccr_costcentre_desc')
            ->where(function ($q) {
                $q->whereNull('ccr_status')->orWhere('ccr_status', 1);
            })
            ->orderBy('ccr_costcentre')
            ->limit(5000)
            ->get()
            ->map(fn ($c) => [
                'id' => (string) $c->ccr_costcentre,
                'label' => trim(($c->ccr_costcentre ?? '').' - '.($c->ccr_costcentre_desc ?? '')),
            ])
            ->values();

        return $this->sendOk([
            'topFilter' => [
                'year' => $years,
                'fund' => $funds,
                'ptjLevel' => $ptjLevels,
                'ptj' => $ptjs,
                'costCentre' => $costCentres,
            ],
            'smartFilter' => [
                'status' => $statuses,
            ],
        ]);
    }
}
