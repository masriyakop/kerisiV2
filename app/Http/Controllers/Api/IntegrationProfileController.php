<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\IntCapitalProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Integration - Profile (PAGEID 2000 / MENUID 2443).
 *
 * Migrated from legacy BL `SNA_API_SM_INTEGRATION_PROFILE`. The screen is a
 * read-only review datatable: rows from `int_capital_project` that have not
 * yet been pushed to the production `capital_project` table
 * (`icp_send_date IS NULL`) and whose project status is anything other than
 * `OPEN`. A smart-filter modal exposes the legacy filter set
 * (project number, sub-system, fund type, activity, cost centre, oun code,
 * SO code, period, project status). A read-only popup modal shows a single
 * row's full record.
 */
class IntegrationProfileController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 5));
        $q = (string) ($request->input('q') ?? '');
        $sortBy = $request->input('sort_by', 'icp_project_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc'));

        $allowedSort = [
            'icp_project_id', 'icp_project_no', 'subsystemcode',
            'fty_fund_type', 'lat_activity_code', 'ccr_costcentre',
            'oun_code', 'icp_so_code', 'icp_yearnum', 'icp_period',
            'icp_project_status',
        ];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'icp_project_id';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query = IntCapitalProject::query()
            ->whereNull('icp_send_date')
            ->where(function ($builder) {
                $builder->whereNull('icp_project_status')
                    ->orWhere('icp_project_status', '!=', 'OPEN');
            });

        $smartMap = [
            'icp_project_no' => 'icp_project_no',
            'subsystemcode' => 'subsystemcode',
            'fty_fund_type' => 'fty_fund_type',
            'lat_activity_code' => 'lat_activity_code',
            'ccr_costcentre' => 'ccr_costcentre',
            'oun_code' => 'oun_code',
            'icp_so_code' => 'icp_so_code',
            'icp_period' => 'icp_period',
            'icp_yearnum' => 'icp_yearnum',
            'icp_project_status' => 'icp_project_status',
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
                    ->whereRaw('LOWER(IFNULL(icp_project_no, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(icp_project_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(subsystemcode, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(fty_fund_type, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(lat_activity_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(ccr_costcentre, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(oun_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(icp_so_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(icp_project_status, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(icp_project_type, "")) LIKE ?', [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (IntCapitalProject $row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'icp_project_id' => (int) $row->icp_project_id,
                'icp_project_no' => $row->icp_project_no,
                'icp_subsystem_id' => $row->icp_subsystem_id,
                'subsystemcode' => $row->subsystemcode,
                'fty_fund_type' => $row->fty_fund_type,
                'lat_activity_code' => $row->lat_activity_code,
                'ccr_costcentre' => $row->ccr_costcentre,
                'oun_code' => $row->oun_code,
                'icp_so_code' => $row->icp_so_code,
                'icp_start_date' => optional($row->icp_start_date)->format('Y-m-d'),
                'icp_end_date' => optional($row->icp_end_date)->format('Y-m-d'),
                'icp_yearnum' => $row->icp_yearnum,
                'icp_project_type' => $row->icp_project_type,
                'icp_project_desc' => $row->icp_project_desc,
                'icp_period' => $row->icp_period,
                'icp_project_status' => $row->icp_project_status,
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
        $row = IntCapitalProject::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Integration profile row not found');
        }

        return $this->sendOk([
            'icp_project_id' => (int) $row->icp_project_id,
            'icp_project_no' => $row->icp_project_no,
            'icp_subsystem_id' => $row->icp_subsystem_id,
            'subsystemcode' => $row->subsystemcode,
            'fty_fund_type' => $row->fty_fund_type,
            'lat_activity_code' => $row->lat_activity_code,
            'ccr_costcentre' => $row->ccr_costcentre,
            'oun_code' => $row->oun_code,
            'icp_so_code' => $row->icp_so_code,
            'icp_start_date' => optional($row->icp_start_date)->format('Y-m-d'),
            'icp_end_date' => optional($row->icp_end_date)->format('Y-m-d'),
            'icp_yearnum' => $row->icp_yearnum,
            'icp_project_type' => $row->icp_project_type,
            'icp_project_desc' => $row->icp_project_desc,
            'icp_period' => $row->icp_period,
            'icp_project_status' => $row->icp_project_status,
        ]);
    }
}
