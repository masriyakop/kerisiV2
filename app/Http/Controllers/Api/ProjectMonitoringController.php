<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProjectMonitoringBalanceRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Budget;
use App\Models\CapitalProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Project Monitoring — List of Project (MENUID 1544) and
 * Updated Balance (MENUID 2065).
 *
 * The "List of Project" screen is a datatable backed by the legacy BL
 * `ANIS_LIST_OF_PROJECT?dt_listingOfProject=1` (see
 * `PAGE_SECOND_LEVEL_MENU.json`, MENUID 1544). The Updated Balance
 * screen is a *form* — see legacy onload BL `SNA_JS_UPDATEDBALANCE_PM`
 * (`FLC_TRIGGER_PAGE.json`, `PAGEID = 1707`) and the server-side BL
 * `SNA_API_UPDATEDBALANCE_PM` (autosuggest + `updateAmount=1` write
 * path).
 *
 * Autosuggest mirrors the legacy SELECT (capital_project ⨝ fund_type ⨝
 * costcentre ⨝ activity_type ⨝ structure_budget ⨝ organization_unit ⨝
 * budget) and exposes the same `_fund / _aktiviti / _ptj / _costcenter
 * / _kodSo / _balAmt / _budgetID / _budgetAmt / _seqStrbudget /
 * _seqbudget` keys (renamed to camelCase by middleware).
 *
 * Save mirrors the legacy two-step UPDATE:
 *
 *   UPDATE capital_project
 *      SET cpa_ytd_balance_amt = :curr,
 *          updateddate         = NOW(),
 *          updatedby           = :user
 *    WHERE cpa_project_no      = :no;
 *
 *   UPDATE budget
 *      SET bdg_topup_amt = :curr,
 *          updateddate   = NOW(),
 *          updatedby     = :user
 *    WHERE bdg_budget_id = :seqBudget;
 *
 * Wrapped in a single `mysql_secondary` transaction so the two rows
 * stay in sync.
 *
 * Deviation from legacy: the legacy autosuggest joins
 * `sb.sbg_budget_id = bdg.bdg_balance_amt`, which compares a budget id
 * to a numeric balance and is almost certainly a typo (the
 * `Budget.sbg_budget_id` FK references `StructureBudget.sbg_budget_id`).
 * We use the corrected `bdg.sbg_budget_id = sb.sbg_budget_id` so the
 * Cash-Balance card resolves to real data; documented here for
 * traceability.
 */
class ProjectMonitoringController extends Controller
{
    use ApiResponse;

    private const LIST_SORTABLE = [
        'cpa_project_id',
        'cpa_project_no',
        'cpa_project_desc',
        'cpa_project_type',
        'fty_fund_type',
        'lat_activity_code',
        'oun_code',
        'ccr_costcentre',
        'so_code',
        'cpa_start_date',
        'cpa_end_date',
        'cpa_source',
        'cpa_project_status',
    ];

    /**
     * GET `/project-monitoring/projects`. List of Project (MENUID 1544).
     */
    public function projects(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(200, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = $this->sortBy($request, self::LIST_SORTABLE, 'cpa_project_no');
        $sortDir = $this->sortDir($request, 'asc');

        $base = $this->applyProjectFilters(CapitalProject::query(), $request);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(cpa_project_id, ''),
                    IFNULL(cpa_project_no, ''),
                    IFNULL(fty_fund_type, ''),
                    IFNULL(lat_activity_code, ''),
                    IFNULL(oun_code, ''),
                    IFNULL(ccr_costcentre, ''),
                    IFNULL(so_code, ''),
                    IFNULL(cpa_project_desc, ''),
                    DATE_FORMAT(cpa_start_date, '%d/%m/%Y'),
                    DATE_FORMAT(cpa_end_date, '%d/%m/%Y'),
                    IFNULL(cpa_project_type, ''),
                    IFNULL(cpa_source, ''),
                    IFNULL(cpa_project_status, '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (int) (clone $base)->count();
        $rows = (clone $base)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('cpa_project_id', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->map(function (CapitalProject $p, int $i) use ($page, $limit) {
            return $this->serializeProjectRow($p, (($page - 1) * $limit) + $i + 1);
        })->all();

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    /**
     * GET `/project-monitoring/updated-balance/search`.
     *
     * Powers the Project ID autosuggest on the Updated Balance form.
     * Mirrors `autoSuggestprojectID` on the legacy BL
     * `SNA_API_UPDATEDBALANCE_PM`.
     */
    public function searchProjects(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $limit = max(1, min(50, (int) $request->input('limit', 20)));

        $builder = $this->balanceQuery();

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $builder->where(function ($b) use ($like) {
                $b->whereRaw("LOWER(IFNULL(cp.cpa_project_no, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(cp.cpa_project_desc, '')) LIKE ?", [$like]);
            });
        }

        $rows = $builder
            ->orderBy('cp.cpa_project_no')
            ->limit($limit)
            ->get();

        return $this->sendOk(
            $rows->map(fn ($row) => $this->serializeBalancePayload($row))->values()->all()
        );
    }

    /**
     * GET `/project-monitoring/updated-balance/{cpaProjectNo}`.
     *
     * Returns the same shape as a single suggest entry. 404 when the
     * project number does not exist.
     */
    public function showBalance(Request $request, string $cpaProjectNo): JsonResponse
    {
        $cpaProjectNo = trim($cpaProjectNo);
        if ($cpaProjectNo === '') {
            return $this->sendError(404, 'NOT_FOUND', 'Project not found');
        }

        $row = $this->balanceQuery()
            ->where('cp.cpa_project_no', $cpaProjectNo)
            ->first();

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Project not found', [
                'cpaProjectNo' => $cpaProjectNo,
            ]);
        }

        return $this->sendOk($this->serializeBalancePayload($row));
    }

    /**
     * POST `/project-monitoring/updated-balance`.
     *
     * Legacy: `api/SNA_API_UPDATEDBALANCE_PM?updateAmount=1` with body
     * `{ info: <balanceInfo serializeJson>, bal: <balanceCash serializeJson> }`.
     */
    public function saveBalance(SaveProjectMonitoringBalanceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $cpaProjectNo = (string) $validated['info']['cpa_project_no'];
        $currBalCash = (float) ($validated['bal']['curr_bal_cash_bal']);
        $bdgBudgetId = (string) $validated['bal']['seq_budget_bal'];
        $username = $request->user()?->name ?? 'system';

        $project = CapitalProject::query()
            ->where('cpa_project_no', $cpaProjectNo)
            ->first();
        if (! $project) {
            return $this->sendError(404, 'NOT_FOUND', 'Project not found', [
                'cpaProjectNo' => $cpaProjectNo,
            ]);
        }

        $budget = Budget::query()
            ->where('bdg_budget_id', $bdgBudgetId)
            ->first();
        if (! $budget) {
            return $this->sendError(404, 'NOT_FOUND', 'Budget row not found', [
                'bdgBudgetId' => $bdgBudgetId,
            ]);
        }

        DB::connection('mysql_secondary')->transaction(function () use ($project, $budget, $currBalCash, $username) {
            $project->update([
                'cpa_ytd_balance_amt' => $currBalCash,
                'updateddate' => now(),
                'updatedby' => $username,
            ]);

            $budget->update([
                'bdg_topup_amt' => $currBalCash,
                'updateddate' => now(),
                'updatedby' => $username,
            ]);
        });

        return $this->sendOk(['success' => true]);
    }

    private function applyProjectFilters($query, Request $request)
    {
        $st = trim((string) $request->input('cpa_project_status', ''));
        if ($st !== '') {
            $query->where('cpa_project_status', $st);
        }
        $src = trim((string) $request->input('cpa_source', ''));
        if ($src !== '') {
            $query->where('cpa_source', $src);
        }
        $oun = trim((string) $request->input('oun_code', ''));
        if ($oun !== '') {
            $query->where('oun_code', $oun);
        }
        $sFrom = trim((string) $request->input('cpa_start_date_from', ''));
        if ($sFrom !== '') {
            $query->whereRaw("DATE(cpa_start_date) >= STR_TO_DATE(?, '%d/%m/%Y')", [$sFrom]);
        }
        $sTo = trim((string) $request->input('cpa_start_date_to', ''));
        if ($sTo !== '') {
            $query->whereRaw("DATE(cpa_start_date) <= STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')", [$sTo.' 23:59:59']);
        }
        $eFrom = trim((string) $request->input('cpa_end_date_from', ''));
        if ($eFrom !== '') {
            $query->whereRaw("DATE(cpa_end_date) >= STR_TO_DATE(?, '%d/%m/%Y')", [$eFrom]);
        }
        $eTo = trim((string) $request->input('cpa_end_date_to', ''));
        if ($eTo !== '') {
            $query->whereRaw("DATE(cpa_end_date) <= STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')", [$eTo.' 23:59:59']);
        }

        return $query;
    }

    private function serializeProjectRow(CapitalProject $p, int $index): array
    {
        return [
            'index' => $index,
            'cpaProjectId' => (int) $p->cpa_project_id,
            'cpaProjectNo' => $p->cpa_project_no,
            'cpaProjectDesc' => $p->cpa_project_desc,
            'cpaProjectType' => $p->getAttribute('cpa_project_type'),
            'ftyFundType' => $p->getAttribute('fty_fund_type'),
            'latActivityCode' => $p->getAttribute('lat_activity_code'),
            'ounCode' => $p->oun_code,
            'ccrCostcentre' => $p->getAttribute('ccr_costcentre'),
            'soCode' => $p->getAttribute('so_code'),
            'cpaStartDate' => $p->cpa_start_date?->toIso8601String(),
            'cpaEndDate' => $p->cpa_end_date?->toIso8601String(),
            'cpaSource' => $p->cpa_source,
            'cpaProjectStatus' => $p->cpa_project_status,
        ];
    }

    /**
     * Joined query that powers the Updated Balance form.
     *
     * Mirrors the legacy autosuggest SELECT (capital_project ⨝
     * fund_type ⨝ costcentre ⨝ activity_type ⨝ structure_budget ⨝
     * organization_unit ⨝ budget). The `budget` join uses
     * `sb.sbg_budget_id = bdg.sbg_budget_id` (corrected — see class
     * doc) and picks the most recent `bdg_year` per project so that a
     * project never duplicates in the dropdown.
     *
     * Returns one row per project with the autosuggest payload columns
     * already aliased into `_fund_*`, `_act_*`, etc.
     */
    private function balanceQuery()
    {
        $latestYearSub = '(SELECT MAX(b2.bdg_year) FROM budget b2 WHERE b2.sbg_budget_id = sb.sbg_budget_id)';

        return DB::connection('mysql_secondary')
            ->table('capital_project as cp')
            ->leftJoin('fund_type as ft', 'cp.fty_fund_type', '=', 'ft.fty_fund_type')
            ->leftJoin('costcentre as cc', 'cp.ccr_costcentre', '=', 'cc.ccr_costcentre')
            ->leftJoin('activity_type as atype', 'cp.lat_activity_code', '=', 'atype.at_activity_code')
            ->leftJoin('organization_unit as ou', 'cp.oun_code', '=', 'ou.oun_code')
            ->leftJoin('structure_budget as sb', function ($j) {
                $j->on('sb.fty_fund_type', '=', 'cp.fty_fund_type')
                    ->on('sb.oun_code', '=', 'cp.oun_code')
                    ->on('sb.ccr_costcentre', '=', 'cp.ccr_costcentre')
                    ->on('sb.at_activity_code', '=', 'cp.lat_activity_code')
                    ->on('sb.kod_so', '=', 'cp.so_code');
            })
            ->leftJoin('budget as bdg', function ($j) use ($latestYearSub) {
                $j->on('bdg.sbg_budget_id', '=', 'sb.sbg_budget_id')
                    ->whereRaw("bdg.bdg_year = $latestYearSub");
            })
            ->select([
                'cp.cpa_project_id',
                'cp.cpa_project_no',
                'cp.cpa_project_desc',
                'cp.cpa_ytd_balance_amt',
                'cp.fty_fund_type',
                'ft.fty_fund_desc',
                'cp.lat_activity_code',
                'atype.at_activity_description_bm',
                'cp.oun_code',
                'ou.oun_desc',
                'cp.ccr_costcentre',
                'cc.ccr_costcentre_desc',
                'cp.so_code',
                'sb.lbc_budget_code',
                'sb.sbg_budget_id as seq_strt_budget',
                'bdg.bdg_budget_id as seq_budget',
                'bdg.bdg_balance_amt',
            ]);
    }

    /**
     * Build the autosuggest / show payload for the Updated Balance form.
     * Mirrors the legacy autosuggest shape (`_fund`, `_aktiviti`, `_ptj`,
     * `_costcenter`, `_kodSo`, `_desc`, `_balAmt`, `_budgetID`,
     * `_budgetAmt`, `_seqStrbudget`, `_seqbudget`).
     */
    private function serializeBalancePayload(object $row): array
    {
        $fundLabel = $this->concatLabel($row->fty_fund_type ?? null, $row->fty_fund_desc ?? null);
        $activityLabel = $this->concatLabel($row->lat_activity_code ?? null, $row->at_activity_description_bm ?? null);
        $ptjLabel = $this->concatLabel($row->oun_code ?? null, $row->oun_desc ?? null);
        $ccLabel = $this->concatLabel($row->ccr_costcentre ?? null, $row->ccr_costcentre_desc ?? null);

        return [
            'cpaProjectId' => (int) ($row->cpa_project_id ?? 0),
            'cpaProjectNo' => $row->cpa_project_no ?? null,
            'cpaProjectDesc' => $row->cpa_project_desc ?? null,
            'ftyFundType' => $row->fty_fund_type ?? null,
            'ftyFundLabel' => $fundLabel,
            'latActivityCode' => $row->lat_activity_code ?? null,
            'latActivityLabel' => $activityLabel,
            'ounCode' => $row->oun_code ?? null,
            'ounLabel' => $ptjLabel,
            'ccrCostcentre' => $row->ccr_costcentre ?? null,
            'ccrCostcentreLabel' => $ccLabel,
            'soCode' => $row->so_code ?? null,
            'balAmt' => $this->toFloat($row->cpa_ytd_balance_amt ?? null),
            'budgetId' => $row->lbc_budget_code ?? null,
            'budgetAmt' => $this->toFloat($row->bdg_balance_amt ?? null),
            'seqStrtBudget' => $row->seq_strt_budget ?? null,
            'seqBudget' => $row->seq_budget ?? null,
        ];
    }

    private function concatLabel(?string $code, ?string $desc): ?string
    {
        $code = $code !== null ? trim($code) : '';
        $desc = $desc !== null ? trim($desc) : '';
        if ($code === '' && $desc === '') {
            return null;
        }
        if ($code !== '' && $desc !== '') {
            return $code.' - '.$desc;
        }

        return $code !== '' ? $code : $desc;
    }

    private function toFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (float) $v;
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }

    private function sortBy(Request $request, array $allowed, string $default): string
    {
        $s = (string) $request->input('sort_by', $default);

        return in_array($s, $allowed, true) ? $s : $default;
    }

    private function sortDir(Request $request, string $default): string
    {
        $d = strtolower((string) $request->input('sort_dir', $default));

        return in_array($d, ['asc', 'desc'], true) ? $d : $default;
    }
}
