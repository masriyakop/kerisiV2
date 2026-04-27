<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromoteIntegrationCostCentreRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CostCentre;
use App\Models\IntCostCentre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Integration - Cost center (PAGEID 1861 / MENUID 2278).
 *
 * Migrated from legacy BL `AS_BL_SM_INTEGRATIONCOSTCENTRE`. The screen lists
 * `int_costcentre` rows that have not yet been promoted into the production
 * `costcentre` table (i.e. `ics_costcentre` is null/empty), exposes a
 * read-only popup for one row, and the promote action mirrors the legacy
 * `process_insert` branch (insert into `costcentre` + stamp the staging row).
 */
class IntegrationCostCentreController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 5));
        $q = (string) ($request->input('q') ?? '');
        $sortBy = $request->input('sort_by', 'ics_costcentre_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc'));

        $allowedSort = ['ics_costcentre_id', 'ics_costcentre', 'ics_costcentre_desc', 'ics_hostel_code', 'ics_status'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'ics_costcentre_id';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $query = IntCostCentre::query()->where(function ($builder) {
            $builder->whereNull('ics_costcentre')
                ->orWhereIn('ics_costcentre', ['', 'null', 'NULL']);
        });

        if ($q !== '') {
            $needle = mb_strtolower(trim($q), 'UTF-8');
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->whereRaw('LOWER(IFNULL(ics_costcentre, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(ics_costcentre_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(ics_hostel_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(ics_status, "")) LIKE ?', [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $statusMap = ['1' => 'Active', '2' => 'Unactive'];

        $data = $rows->values()->map(function (IntCostCentre $row, int $i) use ($page, $limit, $statusMap) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'ics_costcentre_id' => (int) $row->ics_costcentre_id,
                'ics_costcentre' => $row->ics_costcentre,
                'ics_costcentre_desc' => $row->ics_costcentre_desc,
                'ics_hostel_code' => $row->ics_hostel_code,
                'ics_status' => $statusMap[(string) $row->ics_status] ?? $row->ics_status,
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
        $row = IntCostCentre::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Integration Cost Centre row not found');
        }

        $statusMap = ['1' => 'Active', '2' => 'Unactive'];

        return $this->sendOk([
            'ics_costcentre_id' => (int) $row->ics_costcentre_id,
            'ics_costcentre' => $row->ics_costcentre,
            'ics_costcentre_desc' => $row->ics_costcentre_desc,
            'ics_hostel_code' => $row->ics_hostel_code,
            'ics_status' => $statusMap[(string) $row->ics_status] ?? $row->ics_status,
        ]);
    }

    public function promote(PromoteIntegrationCostCentreRequest $request, int $id): JsonResponse
    {
        $row = IntCostCentre::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Integration Cost Centre row not found');
        }

        $data = $request->validated();
        $ccrCode = trim((string) $data['ics_costcentre']);

        if (IntCostCentre::query()
            ->where('ics_costcentre', $ccrCode)
            ->where('ics_costcentre_id', '!=', $row->ics_costcentre_id)
            ->exists()
        ) {
            return $this->sendError(400, 'BAD_REQUEST', 'Cost Centre code already exists in the integration table.');
        }

        $existsInProd = CostCentre::query()->where('ccr_costcentre', $ccrCode)->exists();
        if ($existsInProd) {
            return $this->sendError(400, 'BAD_REQUEST', "Cost Centre {$ccrCode} already exists in the production table.");
        }

        $statusInput = (string) ($data['ics_status'] ?? 'Active');
        $newStatus = in_array($statusInput, ['Active', '1'], true) ? '1' : '2';
        $username = $request->user()?->name ?? 'system';

        DB::connection('mysql_secondary')->transaction(function () use ($row, $data, $ccrCode, $newStatus, $username) {
            $maxId = ((int) CostCentre::query()->max('ccr_costcentre_id')) + 1;

            CostCentre::create([
                'ccr_costcentre_id' => $maxId,
                'ccr_costcentre' => $ccrCode,
                'ccr_costcentre_desc' => $data['ics_costcentre_desc'],
                'ccr_hostel_code' => $data['ics_hostel_code'] ?? null,
                'ccr_status' => $newStatus,
                'createddate' => now(),
                'createdby' => $username,
            ]);

            $row->update([
                'ics_costcentre' => $ccrCode,
                'ics_costcentre_desc' => $data['ics_costcentre_desc'],
                'updateddate' => now(),
                'updatedby' => $username,
            ]);
        });

        return $this->sendOk(['success' => true]);
    }
}
