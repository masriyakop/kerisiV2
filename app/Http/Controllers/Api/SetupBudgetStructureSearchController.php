<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBillsCustomWfRequest;
use App\Http\Requests\UpdateBillsSetupRequest;
use App\Http\Requests\UpdateJenisCarianRequest;
use App\Http\Requests\UpdateSemiStrictRequest;
use App\Http\Traits\ApiResponse;
use App\Models\BillsSetup;
use App\Models\SetupBudgetStructureSearch;
use App\Models\WfProcess;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Setup Carian Structure Budget (legacy PAGEID 2664 / MENUID 3224).
 *
 * Replaces `MM_API_GLOBAL_SETUPCARIANSBG`:
 *   - dt_listing              → Jenis Carian datatable list
 *   - billSetup               → Bill Setup datatable list
 *   - getDataModal            → fetch a setup_budget_structure_search row
 *   - getDataBillSetup        → fetch a bills_setup row
 *   - saveModal               → activate one SBSS row (deactivate all others)
 *   - saveBillSetupModal      → update a bills_setup row
 *   - getAllDataForm          → pre-populate Semi-Strict + CustomWF forms
 *   - saveSemiStrict          → persist SEMISTRICT column/level selection
 *   - saveBillSetupCustomWF   → persist the CustomWF sequence level
 */
class SetupBudgetStructureSearchController extends Controller
{
    use ApiResponse;

    public function options(): JsonResponse
    {
        $sequence = WfProcess::query()
            ->select('wfp_sequence', 'wfp_process_name')
            ->where('wfp_workflow_code', 'BILLREG_AP')
            ->orderBy('wfp_sequence')
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->wfp_sequence,
                'label' => trim(
                    ($row->wfp_sequence !== null ? (string) $row->wfp_sequence : '')
                    .(filled($row->wfp_process_name) ? ' - '.$row->wfp_process_name : ''),
                ),
            ]);

        return $this->sendOk([
            'jenisCarianModal' => [
                'status' => [['id' => 'ACTIVE', 'label' => 'ACTIVE']],
            ],
            'billSetupModal' => [
                'status' => [
                    ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
                    ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
                ],
            ],
            'semiStrict' => [
                'column' => [
                    ['id' => 'ACCOUNT', 'label' => 'Account'],
                    ['id' => 'ACTIVITY', 'label' => 'Activity'],
                ],
                'level' => array_map(
                    static fn (int $n) => ['id' => (string) $n, 'label' => (string) $n],
                    [1, 2, 3, 4, 5, 6],
                ),
            ],
            'billsCustomWf' => [
                'sequence' => $sequence,
            ],
        ]);
    }

    public function forms(): JsonResponse
    {
        $semi = SetupBudgetStructureSearch::query()
            ->where('sbss_type', 'SEMISTRICT')
            ->first(['sbss_column_selection', 'sbss_level_selection']);

        $customWf = BillsSetup::query()
            ->where('bis_type', 'CustomWF')
            ->first(['bis_sequence_level']);

        return $this->sendOk([
            'semiStrict' => [
                'sbss_column_selection' => $semi?->sbss_column_selection,
                'sbss_level_selection' => $semi?->sbss_level_selection !== null
                    ? (string) $semi->sbss_level_selection
                    : null,
            ],
            'billsCustomWf' => [
                'bis_sequence_level' => $customWf?->bis_sequence_level !== null
                    ? (string) $customWf->bis_sequence_level
                    : null,
            ],
        ]);
    }

    public function indexJenisCarian(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        [$page, $limit, $sortBy, $sortDir] = $this->resolvePaging(
            $request,
            (string) $request->input('sort_by', 'sbss_type'),
            strtolower((string) $request->input('sort_dir', 'asc')),
            ['sbss_id', 'sbss_type', 'sbss_status'],
        );

        $query = SetupBudgetStructureSearch::query();
        $this->applyConcatWsLikeEloquent($query, ['sbss_type', 'sbss_status'], $q);

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (SetupBudgetStructureSearch $row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'sbss_id' => (int) $row->sbss_id,
                'sbss_type' => $row->sbss_type,
                'sbss_status' => $row->sbss_status,
            ];
        });

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function showJenisCarian(int $id): JsonResponse
    {
        $row = SetupBudgetStructureSearch::query()->where('sbss_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Jenis Carian not found');
        }

        return $this->sendOk([
            'sbss_id' => (int) $row->sbss_id,
            'sbss_type' => $row->sbss_type,
            'sbss_status' => $row->sbss_status,
        ]);
    }

    public function updateJenisCarian(UpdateJenisCarianRequest $request, int $id): JsonResponse
    {
        $row = SetupBudgetStructureSearch::query()->where('sbss_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Jenis Carian not found');
        }

        $status = strtoupper((string) $request->validated('sbss_status'));

        // Legacy behaviour: saving activates exactly one row. Setting all
        // others to INACTIVE before flipping the target happens inside a
        // transaction so a transient double-active state is never visible.
        DB::connection('mysql_secondary')->transaction(function () use ($id, $status, $request) {
            SetupBudgetStructureSearch::query()
                ->where('sbss_id', '!=', $id)
                ->update([
                    'sbss_status' => 'INACTIVE',
                    'updatedby' => $request->user()?->name ?? 'system',
                    'updateddate' => now(),
                ]);

            SetupBudgetStructureSearch::query()
                ->where('sbss_id', $id)
                ->update([
                    'sbss_status' => $status,
                    'updatedby' => $request->user()?->name ?? 'system',
                    'updateddate' => now(),
                ]);
        });

        return $this->sendOk(['success' => true]);
    }

    public function indexBillsSetup(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        [$page, $limit, $sortBy, $sortDir] = $this->resolvePaging(
            $request,
            (string) $request->input('sort_by', 'bis_type'),
            strtolower((string) $request->input('sort_dir', 'asc')),
            ['bis_id', 'bis_type', 'bis_status'],
        );

        $query = BillsSetup::query();
        $this->applyConcatWsLikeEloquent($query, ['bis_type', 'bis_status'], $q);

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (BillsSetup $row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'bis_id' => (int) $row->bis_id,
                'bis_type' => $row->bis_type,
                'bis_status' => $row->bis_status,
            ];
        });

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function showBillsSetup(int $id): JsonResponse
    {
        $row = BillsSetup::query()->where('bis_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Bill Setup not found');
        }

        return $this->sendOk([
            'bis_id' => (int) $row->bis_id,
            'bis_type' => $row->bis_type,
            'bis_status' => $row->bis_status,
        ]);
    }

    public function updateBillsSetup(UpdateBillsSetupRequest $request, int $id): JsonResponse
    {
        $row = BillsSetup::query()->where('bis_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Bill Setup not found');
        }

        $row->update([
            'bis_status' => strtoupper((string) $request->validated('bis_status')),
            'updatedby' => $request->user()?->name ?? 'system',
            'updateddate' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }

    public function saveSemiStrict(UpdateSemiStrictRequest $request): JsonResponse
    {
        $data = $request->validated();

        $row = SetupBudgetStructureSearch::query()->where('sbss_type', 'SEMISTRICT')->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'SEMISTRICT configuration not found');
        }

        SetupBudgetStructureSearch::query()
            ->where('sbss_type', 'SEMISTRICT')
            ->update([
                'sbss_column_selection' => $data['sbss_column_selection'],
                'sbss_level_selection' => $data['sbss_level_selection'],
                'updatedby' => $request->user()?->name ?? 'system',
                'updateddate' => now(),
            ]);

        return $this->sendOk(['success' => true]);
    }

    public function saveBillsCustomWf(UpdateBillsCustomWfRequest $request): JsonResponse
    {
        $row = BillsSetup::query()->where('bis_type', 'CustomWF')->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'CustomWF bill setup not found');
        }

        BillsSetup::query()
            ->where('bis_type', 'CustomWF')
            ->update([
                'bis_sequence_level' => $request->validated('bis_sequence_level'),
                'updatedby' => $request->user()?->name ?? 'system',
                'updateddate' => now(),
            ]);

        return $this->sendOk(['success' => true]);
    }

    /**
     * @param  array<int, string>  $allowedSortBy
     * @return array{0:int,1:int,2:string,3:string}
     */
    private function resolvePaging(Request $request, string $sortBy, string $sortDir, array $allowedSortBy): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 5));
        if (! in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = $allowedSortBy[0];
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        return [$page, $limit, $sortBy, $sortDir];
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function applyConcatWsLikeEloquent(EloquentBuilder $builder, array $columns, string $needle): void
    {
        if ($needle === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($needle, 'UTF-8')).'%';
        $joined = implode(', ', array_map(static fn (string $col) => "IFNULL($col, '')", $columns));
        $builder->whereRaw("LOWER(CONCAT_WS('__', $joined)) LIKE ?", [$like]);
    }

    /**
     * @return array<string, int>
     */
    private function meta(int $page, int $limit, int $total): array
    {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ];
    }
}
