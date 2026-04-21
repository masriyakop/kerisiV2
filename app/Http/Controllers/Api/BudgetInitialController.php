<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\BudgetAllocationMaster;
use App\Models\QuarterBudget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Budget Initial V2 list (PAGEID 1264 / MENUID 1541).
 *
 * Source of truth: `/Users/nurinaamira/BUDGET.json` (component 3488,
 * api/SWS_DT_BUDGET_INITIAL_V2). The `Business Logic (BL) Details` was not
 * shipped, so the controller is reconstructed from the datatable
 * `dt_bi` / `dt_key` metadata plus the Smart-Filter lookup queries:
 *
 *   dt_bi       | dt_key       | source column
 *   ------------+--------------+------------------------------------
 *   ID (hidden) | ID           | bam_id
 *   Year        | YEARS        | bam_year
 *   Quarter     | DESCR        | quarter_budget.qbu_description
 *   Reference   | ALLOCATE_NO  | bam_allocation_no
 *   Authority   | ENDORSE      | bam_endorse_doc
 *   Amount      | AMT          | bam_total
 *   Status      | STAT         | bam_status_cd
 *   Date        | date         | createddate
 *
 * Smart Filter (component 6686) supplies Year / Quarter / Status. The
 * legacy Year dropdown sources distinct `qbu_year` from
 * `quarter_budget`; Status sources distinct `bam_status_cd` from
 * `budget_allocation_master`.
 *
 * Editor / warrant / cancel flows are NOT part of this migration batch
 * (no BL shipped for them either); the frontend leaves those buttons as
 * "not migrated yet" toasts.
 */
class BudgetInitialController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        // dt_sort defaults to YEARS desc. The frontend exposes the same
        // keys the table displays, so translate them to real columns here.
        'years' => 'BAM.bam_year',
        'quarter' => 'BAM.bam_quarter_id',
        'allocateNo' => 'BAM.bam_allocation_no',
        'endorse' => 'BAM.bam_endorse_doc',
        'amt' => 'BAM.bam_total',
        'stat' => 'BAM.bam_status_cd',
        'date' => 'BAM.createddate',
        'id' => 'BAM.bam_id',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(200, (int) $request->input('limit', 10)));
        $sortKey = (string) $request->input('sort_by', 'years');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortCol = self::SORTABLE[$sortKey] ?? self::SORTABLE['years'];

        $query = $this->baseQuery($request);

        $total = (clone $query)->count('BAM.bam_id');

        $rows = (clone $query)
            ->select([
                'BAM.bam_id',
                'BAM.bam_year',
                'BAM.bam_quarter_id',
                'BAM.bam_allocation_no',
                'BAM.bam_endorse_doc',
                'BAM.bam_total',
                'BAM.bam_status_cd',
                'BAM.createddate',
                'BAM.bam_cancel_remark',
                'QB.qbu_description',
            ])
            ->orderBy($sortCol, $sortDir)
            ->orderByDesc('BAM.bam_id')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $baseIndex = ($page - 1) * $limit;
        $data = $rows->values()->map(fn ($row, $idx) => [
            'index' => $baseIndex + $idx + 1,
            'id' => (int) $row->bam_id,
            'years' => $row->bam_year,
            'quarter' => $row->qbu_description,
            'quarterId' => $row->bam_quarter_id,
            'descr' => $row->qbu_description,
            'allocateNo' => $row->bam_allocation_no,
            'endorse' => $row->bam_endorse_doc,
            'amt' => $row->bam_total !== null ? (float) $row->bam_total : null,
            'stat' => $row->bam_status_cd,
            'date' => optional($row->createddate)?->toIso8601String(),
            'cancelRemark' => $row->bam_cancel_remark,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil(max(1, $total) / $limit),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        // Year: legacy source is `SELECT DISTINCT qbu_year FROM quarter_budget`.
        $years = QuarterBudget::query()
            ->select('qbu_year')
            ->distinct()
            ->whereNotNull('qbu_year')
            ->orderByDesc('qbu_year')
            ->pluck('qbu_year')
            ->filter()
            ->map(fn ($y) => ['id' => (string) $y, 'label' => (string) $y])
            ->values();

        // Quarter: the legacy Smart-Filter quarter dropdown has no lookup
        // query and is populated client-side from the year. We return the
        // full list with an optional `year` scope so the UI can filter
        // in-place.
        $quarterQuery = QuarterBudget::query()
            ->select('qbu_quarter_id', 'qbu_description', 'qbu_year')
            ->orderBy('qbu_year')
            ->orderBy('qbu_quarter_id');

        if ($year = $request->input('year')) {
            $quarterQuery->where('qbu_year', $year);
        }

        $quarters = $quarterQuery
            ->limit(500)
            ->get()
            ->map(fn ($q) => [
                'id' => (string) $q->qbu_quarter_id,
                'label' => trim(((string) $q->qbu_description !== '' ? $q->qbu_description : ('Q'.$q->qbu_quarter_id)).' ('.$q->qbu_year.')'),
                'year' => (string) $q->qbu_year,
            ])
            ->values();

        // Status: legacy source is `SELECT DISTINCT bam_status_cd FROM
        // budget_allocation_master`.
        $statuses = BudgetAllocationMaster::query()
            ->select('bam_status_cd')
            ->distinct()
            ->whereNotNull('bam_status_cd')
            ->orderBy('bam_status_cd')
            ->pluck('bam_status_cd')
            ->filter()
            ->map(fn ($s) => ['id' => (string) $s, 'label' => (string) $s])
            ->values();

        return $this->sendOk([
            'smartFilter' => [
                'year' => $years,
                'quarter' => $quarters,
                'status' => $statuses,
            ],
        ]);
    }

    private function baseQuery(Request $request): Builder
    {
        $query = BudgetAllocationMaster::query()
            ->from('budget_allocation_master as BAM')
            ->leftJoin('quarter_budget as QB', 'BAM.bam_quarter_id', '=', 'QB.qbu_quarter_id');

        // Debounced free-text search. mysql_secondary tables are on a
        // case-insensitive collation and `NULL LIKE '%x%'` is NULL (no
        // match) in MySQL, so we can use plain fluent `like` here — no
        // LOWER()/IFNULL() wrappers needed, which keeps the query
        // builder raw-SQL-free.
        if (($needle = trim((string) $request->input('q'))) !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($w) use ($like) {
                $w->where('BAM.bam_allocation_no', 'like', $like)
                    ->orWhere('BAM.bam_endorse_doc', 'like', $like)
                    ->orWhere('BAM.bam_year', 'like', $like)
                    ->orWhere('QB.qbu_description', 'like', $like)
                    ->orWhere('BAM.bam_status_cd', 'like', $like);
            });
        }

        // Smart filter (camelCase incoming from CamelCaseMiddleware):
        // smYear → sm_year, smQuarter → sm_quarter, smStatus → sm_status.
        if (($v = $request->input('sm_year')) !== null && $v !== '') {
            $query->where('BAM.bam_year', (string) $v);
        }
        if (($v = $request->input('sm_quarter')) !== null && $v !== '') {
            $query->where('BAM.bam_quarter_id', (string) $v);
        }
        if (($v = $request->input('sm_status')) !== null && $v !== '') {
            $query->where('BAM.bam_status_cd', (string) $v);
        }

        // Warrant Filter (top filter) — optional narrowing. The backend
        // `generate warrant` action itself is not part of this batch, but
        // we still honour the filter values if the UI sends them so the
        // list reflects what the user sees.
        if (($v = trim((string) $request->input('reference'))) !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v).'%';
            $query->where('BAM.bam_allocation_no', 'like', $like);
        }
        if (($v = trim((string) $request->input('year'))) !== '') {
            $query->where('BAM.bam_year', $v);
        }
        if (($v = trim((string) $request->input('quarter'))) !== '') {
            $query->where('BAM.bam_quarter_id', $v);
        }

        return $query;
    }
}
