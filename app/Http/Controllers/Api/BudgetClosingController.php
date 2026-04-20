<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\ActivityGroup;
use App\Models\ActivitySubgroup;
use App\Models\ActivityType;
use App\Models\Budget;
use App\Models\FundType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Budget Closing (PAGEID 1953 / MENUID 2389) — Filter form + Start/Reverse
 * Process buttons.
 *
 * Only the onload JS was provided in the legacy export
 * (docs/migration/fims-budget/TRIGGER_1953.json : NAD_JS_BUDGET_BUDGETCLOSING);
 * the server-side BL `NAD_API_BUDGET_BUDGETCLOSING` (the
 * startProcess / reverseProcess stored-proc orchestration) was NOT shipped.
 *
 * This controller therefore wires:
 *   - `GET  /budget/closing/options` — dropdowns for Fund, Activity Group,
 *     Activity Subgroup, Activity Code, Year (all read-only lookups).
 *   - `POST /budget/closing/process` and `POST /budget/closing/reverse` —
 *     501 NOT_IMPLEMENTED, with a clear `reason` flag so the UI can show
 *     a "Server BL not migrated yet" banner instead of silently failing.
 *
 * TODO(closing-bl): once the legacy NAD_API_BUDGET_BUDGETCLOSING server
 * code is supplied, replace the 501 responses with the real Eloquent /
 * stored-procedure orchestration.
 */
class BudgetClosingController extends Controller
{
    use ApiResponse;

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

        $funds = FundType::query()
            ->select('fty_fund_type', 'fty_fund_desc')
            ->where(function ($q) {
                $q->whereNull('fty_status')->orWhere('fty_status', 1);
            })
            ->orderBy('fty_fund_type')
            ->get()
            ->map(fn ($f) => [
                'id' => (string) $f->fty_fund_type,
                'label' => trim(((string) $f->fty_fund_type).' - '.((string) $f->fty_fund_desc)),
            ])
            ->values();

        $activityGroup = ActivityGroup::query()
            ->orderBy('activity_group_code')
            ->get()
            ->map(fn ($g) => [
                'id' => (string) ($g->activity_group_code ?? ''),
                'label' => trim(((string) ($g->activity_group_code ?? '')).' - '.((string) ($g->activity_group_desc ?? ''))),
            ])
            ->values();

        $activitySubgroup = ActivitySubgroup::query()
            ->orderBy('activity_subgroup_code')
            ->get()
            ->map(fn ($s) => [
                'id' => (string) ($s->activity_subgroup_code ?? ''),
                'label' => trim(((string) ($s->activity_subgroup_code ?? '')).' - '.((string) ($s->activity_subgroup_desc ?? ''))),
                'activityGroupCode' => $s->activity_group_code ?? null,
            ])
            ->values();

        $activityCode = ActivityType::query()
            ->select(['at_activity_code', 'at_activity_description_bm', 'activity_group_code', 'activity_subgroup_code'])
            ->where(function ($q) {
                $q->whereNull('at_status')->orWhere('at_status', 1);
            })
            ->orderBy('at_activity_code')
            ->limit(5000)
            ->get()
            ->map(fn ($a) => [
                'id' => (string) $a->at_activity_code,
                'label' => trim(((string) $a->at_activity_code).' - '.((string) $a->at_activity_description_bm)),
                'activityGroupCode' => $a->activity_group_code,
                'activitySubgroupCode' => $a->activity_subgroup_code,
            ])
            ->values();

        return $this->sendOk([
            'filter' => [
                'year' => $years,
                'fund' => $funds,
                'activityGroup' => $activityGroup,
                'activitySubgroup' => $activitySubgroup,
                'activityCode' => $activityCode,
            ],
        ]);
    }

    public function process(Request $request): JsonResponse
    {
        return $this->notImplemented('process');
    }

    public function reverse(Request $request): JsonResponse
    {
        return $this->notImplemented('reverse');
    }

    private function notImplemented(string $mode): JsonResponse
    {
        return $this->sendError(
            501,
            'NOT_IMPLEMENTED',
            "Budget Closing {$mode} is not available yet.",
            [
                'reason' => 'BL_NOT_PROVIDED',
                'reasonDetail' => 'Legacy BL NAD_API_BUDGET_BUDGETCLOSING ('.$mode.' mode) was not included in the migration export.',
            ]
        );
    }
}
