<?php

namespace App\Services;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the Budget Monitoring (PAGEID 1201) aggregation query.
 *
 * Controllers in this codebase must stay ORM-only per the project rule
 * "No Raw SQL: Use Eloquent query builder in controllers. Raw DB queries
 * only in services when absolutely necessary." (see CLAUDE.md, Forbidden
 * Patterns #10). The monitoring screen genuinely needs `CONCAT_WS`,
 * `IFNULL` and `SUM(...)` expressions inside the SELECT / GROUP BY lists,
 * none of which are expressible with Laravel's fluent query builder
 * alone, so those SQL-function expressions are quarantined in this
 * service and the controller calls the three public methods below.
 */
class BudgetMonitoringQueryService
{
    /**
     * Whitelist of sortable columns. Kept in sync with the frontend
     * datatable so only safe aliases can reach the underlying SQL.
     *
     * @var list<string>
     */
    public const SORTABLE = [
        'budgetid',
        'bdg_status',
        'bdg_year',
        'bdg_bal_carryforward',
        'bdg_initial_amt',
        'bdg_additional_amt',
        'bdg_virement_amt',
        'bdg_allocated_amt',
        'bdg_lock_amt',
        'bdg_request_amt',
        'bdg_commit_amt',
        'bdg_expenses_amt',
        'bdg_balance_amt',
        'bdg_pre_request_amt',
        'fty_fund_type',
        'oun_code',
        'ccr_costcentre',
        'lbc_budget_code',
    ];

    /**
     * Paginated, grouped rows for the Monitoring datatable.
     */
    public function rows(Request $request, int $page, int $limit, string $sortBy, string $sortDir): Collection
    {
        $query = $this->groupedRowsQuery($request);

        return $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();
    }

    /**
     * Count of distinct grouped rows — wraps the grouped query in a
     * subquery so the COUNT reflects groups, not source rows.
     */
    public function total(Request $request): int
    {
        return DB::connection('mysql_secondary')
            ->query()
            ->fromSub($this->groupedRowsQuery($request), 'grouped')
            ->count();
    }

    /**
     * Grand totals for the datatable footer. Built from the same base
     * filters so the footer always matches the rows shown.
     *
     * @return array<string, float>
     */
    public function grandTotals(Request $request): array
    {
        $perRow = (clone $this->baseQuery($request))
            ->select([
                DB::raw('IFNULL(B.bdg_bal_carryforward, 0) AS bdg_bal_carryforward'),
                DB::raw('IFNULL(B.bdg_initial_amt, 0) AS bdg_initial_amt'),
                DB::raw('SUM(IFNULL(B.bdg_topup_amt, 0)) AS bdg_topup_amt'),
                DB::raw('SUM(IFNULL(B.bdg_additional_amt, 0)) AS bdg_additional_amt'),
                DB::raw('SUM(IFNULL(B.bdg_virement_amt, 0)) AS bdg_virement_amt'),
                DB::raw('SUM(IFNULL(B.bdg_allocated_amt, 0)) AS bdg_allocated_amt'),
                DB::raw('SUM(IFNULL(B.bdg_lock_amt, 0)) AS bdg_lock_amt'),
                DB::raw('SUM(IFNULL(B.bdg_pre_request_amt, 0)) AS bdg_pre_request_amt'),
                DB::raw('SUM(IFNULL(B.bdg_request_amt, 0)) AS bdg_request_amt'),
                DB::raw('SUM(IFNULL(B.bdg_commit_amt, 0)) AS bdg_commit_amt'),
                DB::raw('SUM(IFNULL(B.bdg_expenses_amt, 0)) AS bdg_expenses_amt'),
                DB::raw('SUM(IFNULL(B.bdg_balance_amt, 0)) AS bdg_balance_amt'),
            ])
            ->groupBy([
                DB::raw("CONCAT_WS('-', SB.fty_fund_type, SB.at_activity_code, SB.oun_code, SB.ccr_costcentre, SB.lbc_budget_code)"),
                'B.bdg_status',
                'B.bdg_year',
                'bdg_bal_carryforward',
                'bdg_initial_amt',
            ]);

        $row = DB::connection('mysql_secondary')
            ->query()
            ->fromSub($perRow, 'g')
            ->selectRaw('
                SUM(IFNULL(bdg_bal_carryforward, 0)) AS f_opening,
                SUM(IFNULL(bdg_topup_amt, 0))        AS f_topup,
                SUM(IFNULL(bdg_initial_amt, 0))      AS f_initial,
                SUM(IFNULL(bdg_additional_amt, 0))   AS f_additional,
                SUM(IFNULL(bdg_virement_amt, 0))     AS f_virement,
                SUM(IFNULL(bdg_allocated_amt, 0))    AS f_allocated,
                SUM(IFNULL(bdg_lock_amt, 0))         AS f_lock,
                SUM(IFNULL(bdg_pre_request_amt, 0))  AS f_pre_request,
                SUM(IFNULL(bdg_request_amt, 0))      AS f_request,
                SUM(IFNULL(bdg_commit_amt, 0))       AS f_commit,
                SUM(IFNULL(bdg_expenses_amt, 0))     AS f_expenses,
                SUM(IFNULL(bdg_balance_amt, 0))      AS f_balance
            ')
            ->first();

        return [
            'bdg_bal_carryforward' => (float) ($row->f_opening ?? 0),
            'bdg_topup_amt'        => (float) ($row->f_topup ?? 0),
            'bdg_initial_amt'      => (float) ($row->f_initial ?? 0),
            'bdg_additional_amt'   => (float) ($row->f_additional ?? 0),
            'bdg_virement_amt'     => (float) ($row->f_virement ?? 0),
            'bdg_allocated_amt'    => (float) ($row->f_allocated ?? 0),
            'bdg_lock_amt'         => (float) ($row->f_lock ?? 0),
            'bdg_pre_request_amt'  => (float) ($row->f_pre_request ?? 0),
            'bdg_request_amt'      => (float) ($row->f_request ?? 0),
            'bdg_commit_amt'       => (float) ($row->f_commit ?? 0),
            'bdg_expenses_amt'     => (float) ($row->f_expenses ?? 0),
            'bdg_balance_amt'      => (float) ($row->f_balance ?? 0),
        ];
    }

    /**
     * The grouped SELECT / GROUP BY used for both rows and count. Kept
     * private so callers cannot mutate the shape in ways the rest of
     * this service relies on.
     */
    private function groupedRowsQuery(Request $request): EloquentBuilder
    {
        return (clone $this->baseQuery($request))
            ->select([
                DB::raw("CONCAT_WS('-', SB.fty_fund_type, SB.at_activity_code, SB.oun_code, SB.ccr_costcentre, SB.lbc_budget_code) AS budgetid"),
                'B.bdg_status',
                'B.bdg_year',
                'B.sbg_budget_id',
                DB::raw('IFNULL(B.bdg_bal_carryforward, 0) AS bdg_bal_carryforward'),
                DB::raw('IFNULL(B.bdg_initial_amt, 0) AS bdg_initial_amt'),
                DB::raw('SUM(IFNULL(B.bdg_topup_amt, 0)) AS bdg_topup_amt'),
                DB::raw('SUM(IFNULL(B.bdg_additional_amt, 0)) AS bdg_additional_amt'),
                DB::raw('SUM(IFNULL(B.bdg_virement_amt, 0)) AS bdg_virement_amt'),
                DB::raw('SUM(IFNULL(B.bdg_allocated_amt, 0)) AS bdg_allocated_amt'),
                DB::raw('SUM(IFNULL(B.bdg_lock_amt, 0)) AS bdg_lock_amt'),
                DB::raw('SUM(IFNULL(B.bdg_pre_request_amt, 0)) AS bdg_pre_request_amt'),
                DB::raw('SUM(IFNULL(B.bdg_request_amt, 0)) AS bdg_request_amt'),
                DB::raw('SUM(IFNULL(B.bdg_commit_amt, 0)) AS bdg_commit_amt'),
                DB::raw('SUM(IFNULL(B.bdg_expenses_amt, 0)) AS bdg_expenses_amt'),
                DB::raw('SUM(IFNULL(B.bdg_balance_amt, 0)) AS bdg_balance_amt'),
                'SB.fty_fund_type',
                'FT.fty_fund_desc',
                'SB.at_activity_code',
                'ATS.at_activity_description_bm',
                'SB.oun_code',
                'OU.oun_desc',
                'SB.ccr_costcentre',
                'CC.ccr_costcentre_desc',
                'SB.lbc_budget_code',
                DB::raw('IFNULL(AM.acm_acct_desc, lbc.lbc_description) AS acm_acct_desc'),
                'B.bdg_closing',
                'B.bdg_closing_by',
            ])
            ->groupBy([
                'budgetid',
                'B.bdg_status',
                'B.bdg_year',
                'B.sbg_budget_id',
                'bdg_bal_carryforward',
                'bdg_initial_amt',
                'SB.fty_fund_type',
                'FT.fty_fund_desc',
                'SB.at_activity_code',
                'ATS.at_activity_description_bm',
                'SB.oun_code',
                'OU.oun_desc',
                'SB.ccr_costcentre',
                'CC.ccr_costcentre_desc',
                'SB.lbc_budget_code',
                // IFNULL(AM.acm_acct_desc, lbc.lbc_description) needs
                // BOTH underlying columns in GROUP BY under MySQL's
                // only_full_group_by mode; grouping on the alias alone
                // resolves to AM.acm_acct_desc only.
                'AM.acm_acct_desc',
                'lbc.lbc_description',
                'B.bdg_closing',
                'B.bdg_closing_by',
            ]);
    }

    /**
     * Base FROM / JOIN / WHERE chain used by both row and total queries.
     * Filtering uses fluent where / like — case-insensitive LIKE works
     * out-of-the-box because mysql_secondary tables are on utf8mb4_*_ci
     * collation, so we do not need `LOWER()` wrappers.
     */
    private function baseQuery(Request $request): EloquentBuilder
    {
        $query = Budget::query()
            ->from('budget as B')
            ->join('structure_budget as SB', 'B.sbg_budget_id', '=', 'SB.sbg_budget_id')
            ->whereColumn('B.bdg_year', 'SB.sby_year')
            ->where('B.bdg_status', 'APPROVED')
            ->leftJoin('fund_type as FT', 'SB.fty_fund_type', '=', 'FT.fty_fund_type')
            ->leftJoin('activity_type as ATS', 'SB.at_activity_code', '=', 'ATS.at_activity_code')
            ->leftJoin('organization_unit as OU', 'SB.oun_code', '=', 'OU.oun_code')
            ->leftJoin('account_main as AM', 'SB.lbc_budget_code', '=', 'AM.acm_acct_code')
            ->leftJoin('lkp_budget_code as lbc', 'SB.lbc_budget_code', '=', 'lbc.lbc_budget_code')
            ->leftJoin('costcentre as CC', 'SB.ccr_costcentre', '=', 'CC.ccr_costcentre');

        // Top-filter equality columns (legacy $_POST payload).
        if ($v = $request->input('top_year')) {
            $query->where('B.bdg_year', $v);
        }
        if ($v = $request->input('top_fund')) {
            $query->where('SB.fty_fund_type', $v);
        }
        if ($v = $request->input('top_cost_centre')) {
            $query->where('SB.ccr_costcentre', $v);
        }
        if ($v = $request->input('top_activity_code')) {
            $query->where('SB.at_activity_code', $v);
        }
        if ($ptj = $request->input('top_ptj')) {
            // Legacy BL walked oun_code_parent up to 4 levels. We reuse
            // the same self-join unroll; each leftJoin is fluent so
            // Eloquent happily builds this without raw SQL.
            $query->whereIn('SB.oun_code', function (QueryBuilder $sub) use ($ptj) {
                $sub->from('organization_unit as t')
                    ->select('t.oun_code')
                    ->leftJoin('organization_unit as p1', 'p1.oun_code', '=', 't.oun_code_parent')
                    ->leftJoin('organization_unit as p2', 'p2.oun_code', '=', 'p1.oun_code_parent')
                    ->leftJoin('organization_unit as p3', 'p3.oun_code', '=', 'p2.oun_code_parent')
                    ->leftJoin('organization_unit as p4', 'p4.oun_code', '=', 'p3.oun_code_parent')
                    ->where(function (QueryBuilder $where) use ($ptj) {
                        $where->where('t.oun_code', $ptj)
                            ->orWhere('t.oun_code_parent', $ptj)
                            ->orWhere('p1.oun_code_parent', $ptj)
                            ->orWhere('p2.oun_code_parent', $ptj)
                            ->orWhere('p3.oun_code_parent', $ptj)
                            ->orWhere('p4.oun_code_parent', $ptj);
                    });
            });
        }

        // Debounced search across user-visible text columns. mysql
        // default collation is case-insensitive; NULL LIKE '%x%' is
        // NULL which never matches in WHERE — so plain fluent `like`
        // is enough, no LOWER() / IFNULL() wrappers needed.
        $needle = trim((string) $request->input('q'));
        if ($needle !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $budgetIdLike = $like;

            $query->where(function (EloquentBuilder $w) use ($like, $budgetIdLike) {
                $w->where('B.bdg_status', 'like', $like)
                    ->orWhere('B.bdg_year', 'like', $like)
                    ->orWhere('FT.fty_fund_desc', 'like', $like)
                    ->orWhere('ATS.at_activity_description_bm', 'like', $like)
                    ->orWhere('OU.oun_desc', 'like', $like)
                    ->orWhere('CC.ccr_costcentre_desc', 'like', $like)
                    ->orWhere('AM.acm_acct_desc', 'like', $like)
                    ->orWhere('lbc.lbc_description', 'like', $like)
                    // Budget ID is a concatenation — match each leg
                    // individually so we do not need a raw CONCAT_WS
                    // expression here.
                    ->orWhere('SB.fty_fund_type', 'like', $budgetIdLike)
                    ->orWhere('SB.at_activity_code', 'like', $budgetIdLike)
                    ->orWhere('SB.oun_code', 'like', $budgetIdLike)
                    ->orWhere('SB.ccr_costcentre', 'like', $budgetIdLike)
                    ->orWhere('SB.lbc_budget_code', 'like', $budgetIdLike);
            });
        }

        // Smart filter (Budget ID / Status / Account / Kod SO / Budget Code).
        if ($v = $request->input('sm_budget_id')) {
            // Same CONCAT workaround — match against each leg.
            $like = '%'.$v.'%';
            $query->where(function (EloquentBuilder $w) use ($like) {
                $w->where('SB.fty_fund_type', 'like', $like)
                    ->orWhere('SB.at_activity_code', 'like', $like)
                    ->orWhere('SB.oun_code', 'like', $like)
                    ->orWhere('SB.ccr_costcentre', 'like', $like)
                    ->orWhere('SB.lbc_budget_code', 'like', $like);
            });
        }
        if ($v = $request->input('sm_status')) {
            $query->where('B.bdg_status', 'like', '%'.$v.'%');
        }
        if ($v = $request->input('sm_acm_acct_code')) {
            $query->where('AM.acm_acct_code', 'like', '%'.$v.'%');
        }
        if ($v = $request->input('sm_kod_so')) {
            $query->where('SB.kod_so', 'like', '%'.$v.'%');
        }
        if ($v = $request->input('sm_budget_code')) {
            $query->where('SB.lbc_budget_code', 'like', '%'.$v.'%');
        }

        return $query;
    }
}
