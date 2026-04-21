<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\BudgetMovementMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only list controller for the legacy FIMS Budget list screens
 * (Increment / Decrement / Virement).
 *
 * Derived from the legacy PHP BLs:
 *   docs/migration/fims-budget/PAGE_1273.json  (API_BUDGET_INCREMENT_V2)
 *   docs/migration/fims-budget/PAGE_1274.json  (API_BUDGET_DECREMENT_V2)
 *   docs/migration/fims-budget/PAGE_1275.json  (API_BUDGET_VIREMENT_V2)
 *
 * Intentionally omitted from this initial migration:
 *   - Access control (UNIT_BUDGET / FLC_USER_GROUP_MAPPING / organization_authorization).
 *     All authenticated users currently see all rows. TODO(access-control).
 *   - wf_task / wf_application_status workflow joins.
 *   - cancelProcess (the legacy stored proc update_budget). The cancel button is
 *     surfaced in the UI but disabled; the underlying editor pages live at
 *     menuID=1558 / 1559 which are not part of this migration batch.
 */
class BudgetMovementController extends Controller
{
    use ApiResponse;

    /**
     * Map of URL type slug → legacy bmm_trans_type value.
     */
    private const TYPE_MAP = [
        'increment' => 'INCREMENT',
        'decrement' => 'DECREMENT',
        'virement' => 'VIREMENT',
    ];

    public function index(Request $request, string $type): JsonResponse
    {
        $transType = self::TYPE_MAP[strtolower($type)] ?? null;
        if ($transType === null) {
            return $this->sendError(400, 'BAD_REQUEST', 'Unknown budget movement type.');
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(200, (int) $request->input('limit', 10)));
        $q = $request->input('q');
        $smYear = $request->input('sm_bmm_year');
        $smStatus = $request->input('sm_bmm_status');
        $smMovementType = $request->input('sm_bmm_movement_type');

        $allowedSortBy = [
            'bmm_budget_movement_no',
            'bmm_year',
            'bmm_total_amt',
            'bmm_status',
            'createddate',
            'updateddate',
        ];
        $sortBy = $request->input('sort_by', 'createddate');
        if (! in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'createddate';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc'));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query = BudgetMovementMaster::query()
            ->where('bmm_trans_type', $transType);

        if ($smYear) {
            $query->where('bmm_year', $smYear);
        }
        if ($smStatus) {
            $query->where('bmm_status', $smStatus);
        }
        if ($transType === 'VIREMENT' && $smMovementType) {
            $query->where('bmm_movement_type', $smMovementType);
        }

        if ($q) {
            // mysql_secondary uses a case-insensitive collation and
            // `NULL LIKE '%x%'` is NULL (no match), so plain fluent
            // `like` is sufficient — no LOWER()/IFNULL() wrappers
            // needed and the controller stays raw-SQL-free.
            $needle = trim((string) $q);
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('bmm_budget_movement_no', 'like', $like)
                    ->orWhere('bmm_year', 'like', $like)
                    ->orWhere('bmm_reason', 'like', $like)
                    ->orWhere('bmm_description', 'like', $like)
                    ->orWhere('bmm_endorse_doc', 'like', $like)
                    ->orWhere('bmm_status', 'like', $like);
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (BudgetMovementMaster $row, int $i) use ($page, $limit) {
            $effectiveDate = $row->updateddate ?? $row->createddate;

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'bmm_budget_movement_id' => $row->bmm_budget_movement_id,
                'bmm_budget_movement_no' => $row->bmm_budget_movement_no,
                'bmm_year' => $row->bmm_year,
                'qbu_quarter_id' => $row->qbu_quarter_id,
                'bmm_trans_type' => $row->bmm_trans_type,
                'bmm_movement_type' => $row->bmm_movement_type,
                'bmm_total_amt' => $row->bmm_total_amt,
                'bmm_status' => $row->bmm_status,
                'bmm_reason' => $row->bmm_reason,
                'bmm_description' => $row->bmm_description,
                'bmm_endorse_doc' => $row->bmm_endorse_doc,
                'createdby' => $row->createdby,
                'updatedby' => $row->updatedby,
                'date' => optional($effectiveDate)->toIso8601String(),
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $row = BudgetMovementMaster::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Budget movement not found');
        }

        $effectiveDate = $row->updateddate ?? $row->createddate;

        return $this->sendOk([
            'bmm_budget_movement_id' => $row->bmm_budget_movement_id,
            'bmm_budget_movement_no' => $row->bmm_budget_movement_no,
            'bmm_year' => $row->bmm_year,
            'qbu_quarter_id' => $row->qbu_quarter_id,
            'bmm_trans_type' => $row->bmm_trans_type,
            'bmm_movement_type' => $row->bmm_movement_type,
            'bmm_total_amt' => $row->bmm_total_amt,
            'bmm_status' => $row->bmm_status,
            'bmm_reason' => $row->bmm_reason,
            'bmm_description' => $row->bmm_description,
            'bmm_endorse_doc' => $row->bmm_endorse_doc,
            'createdby' => $row->createdby,
            'updatedby' => $row->updatedby,
            'date' => optional($effectiveDate)->toIso8601String(),
        ]);
    }

    /**
     * Dropdown feeders for the smart-filter modal (years + statuses + movement types).
     * Pulled directly from the table using DISTINCT so the options stay in sync
     * with actual data rather than being hardcoded.
     */
    public function options(Request $request, string $type): JsonResponse
    {
        $transType = self::TYPE_MAP[strtolower($type)] ?? null;
        if ($transType === null) {
            return $this->sendError(400, 'BAD_REQUEST', 'Unknown budget movement type.');
        }

        $base = BudgetMovementMaster::query()->where('bmm_trans_type', $transType);

        $years = (clone $base)
            ->select('bmm_year')
            ->distinct()
            ->whereNotNull('bmm_year')
            ->orderByDesc('bmm_year')
            ->pluck('bmm_year')
            ->filter()
            ->map(fn ($y) => ['id' => (string) $y, 'label' => (string) $y])
            ->values();

        $statuses = (clone $base)
            ->select('bmm_status')
            ->distinct()
            ->whereNotNull('bmm_status')
            ->orderBy('bmm_status')
            ->pluck('bmm_status')
            ->filter()
            ->map(fn ($s) => ['id' => (string) $s, 'label' => (string) $s])
            ->values();

        $movementTypes = collect();
        if ($transType === 'VIREMENT') {
            $movementTypes = (clone $base)
                ->select('bmm_movement_type')
                ->distinct()
                ->whereNotNull('bmm_movement_type')
                ->orderBy('bmm_movement_type')
                ->pluck('bmm_movement_type')
                ->filter()
                ->map(fn ($t) => ['id' => (string) $t, 'label' => (string) $t])
                ->values();
        }

        return $this->sendOk([
            'smartFilter' => [
                'year' => $years,
                'status' => $statuses,
                'movementType' => $movementTypes,
            ],
        ]);
    }
}
