<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\ActivityType;
use App\Models\IntActivityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Integration - Activity (PAGEID 2003 / MENUID 2444).
 *
 * Migrated from legacy BL `SNA_API_SM_INTEGRATION_ACTIVITY`. Read-only
 * datatable: rows from `int_activity_type` that have not yet been promoted
 * into the production `activity_type` table (i.e. there is no matching row
 * with the same activity code). Smart-filter exposes the legacy filter set
 * (group code, subgroup code). A read-only popup modal shows a single row.
 */
class IntegrationActivityController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 5));
        $q = (string) ($request->input('q') ?? '');
        $sortBy = $request->input('sort_by', 'iat_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc'));

        $allowedSort = [
            'iat_id', 'iat_activity_code', 'iat_activity_description_bm',
            'iat_activity_group_code', 'iat_activity_subgroup_code',
            'iat_activity_subsiri_code', 'iat_status', 'iat_source',
        ];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'iat_id';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        // Legacy filter: rows still pending promotion (no match in
        // `activity_type.at_activity_code`). Use a NOT EXISTS subquery to
        // stay on Eloquent and avoid loading the full prod table.
        $promotedCodes = ActivityType::query()
            ->whereNotNull('at_activity_code')
            ->pluck('at_activity_code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = IntActivityType::query();
        if (! empty($promotedCodes)) {
            $query->whereNotIn('iat_activity_code', $promotedCodes);
        }

        $smartMap = [
            'group_code' => 'iat_activity_group_code',
            'subgroup_code' => 'iat_activity_subgroup_code',
            'subsiri_code' => 'iat_activity_subsiri_code',
            'iat_source' => 'iat_source',
            'iat_status' => 'iat_status',
        ];
        foreach ($smartMap as $param => $column) {
            $value = $request->input($param);
            if ($value === null || $value === '') {
                continue;
            }
            $query->where($column, $value);
        }

        if ($q !== '') {
            $needle = mb_strtolower(trim($q), 'UTF-8');
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->whereRaw('LOWER(IFNULL(iat_activity_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iat_activity_description_bm, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iat_activity_code_parent, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iat_activity_group_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iat_activity_subgroup_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iat_activity_subsiri_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iat_status, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iat_source, "")) LIKE ?', [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (IntActivityType $row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'iat_id' => (int) $row->iat_id,
                'iat_activity_code' => $row->iat_activity_code,
                'iat_activity_description_bm' => $row->iat_activity_description_bm,
                'iat_activity_code_parent' => $row->iat_activity_code_parent,
                'iat_activity_group_code' => $row->iat_activity_group_code,
                'iat_activity_subgroup_code' => $row->iat_activity_subgroup_code,
                'iat_activity_subsiri_code' => $row->iat_activity_subsiri_code,
                'iat_status' => $row->iat_status,
                'iat_source' => $row->iat_source,
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
        $row = IntActivityType::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Integration activity row not found');
        }

        return $this->sendOk([
            'iat_id' => (int) $row->iat_id,
            'iat_activity_code' => $row->iat_activity_code,
            'iat_activity_description_bm' => $row->iat_activity_description_bm,
            'iat_activity_code_parent' => $row->iat_activity_code_parent,
            'iat_activity_group_code' => $row->iat_activity_group_code,
            'iat_activity_subgroup_code' => $row->iat_activity_subgroup_code,
            'iat_activity_subsiri_code' => $row->iat_activity_subsiri_code,
            'iat_status' => $row->iat_status,
            'iat_source' => $row->iat_source,
            'iat_extended_field' => $row->iat_extended_field,
        ]);
    }
}
