<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromoteIntegrationPtjRequest;
use App\Http\Traits\ApiResponse;
use App\Models\IntOrganizationUnit;
use App\Models\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Integration - PTJ (PAGEID 1860 / MENUID 2277).
 *
 * Migrated from legacy BL `AS_BL_SM_INTEGRATIONPTJ`:
 *   - `?dt_integrationPTJ=1`     → datatable listing of staged rows.
 *   - `?edit_detailsMdl=1&id=…` → single record fetched into the popup
 *     modal for promotion.
 *   - `?process_insert=1`        → promote a staged row into
 *     `organization_unit` and stamp `int_organization_unit.iou_code`/
 *     `iou_desc` so it falls out of the listing.
 *
 * The listing is restricted (legacy filter) to rows whose `iou_code` has
 * not yet been promoted — i.e. it is `NULL` or one of `''`, `'null'`,
 * `'NULL'`. All persistence is performed via Eloquent on the
 * `mysql_secondary` connection; no raw SQL is used.
 */
class IntegrationPtjController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 5));
        $q = (string) ($request->input('q') ?? '');
        $sortBy = $request->input('sort_by', 'iou_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc'));

        $allowedSort = [
            'iou_id', 'iou_code', 'iou_code_persis', 'iou_desc',
            'iou_bursar_flag', 'org_code', 'org_desc', 'iou_address',
            'iou_tel_no', 'iou_fax_no',
        ];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'iou_id';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $query = IntOrganizationUnit::query()
            ->where(function ($builder) {
                $builder->whereNull('iou_code')
                    ->orWhereIn('iou_code', ['', 'null', 'NULL']);
            });

        if ($q !== '') {
            $needle = mb_strtolower(trim($q), 'UTF-8');
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->whereRaw('LOWER(IFNULL(iou_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iou_code_persis, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iou_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iou_bursar_flag, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(org_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(org_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iou_address, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iou_tel_no, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(iou_fax_no, "")) LIKE ?', [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (IntOrganizationUnit $row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'iou_id' => (int) $row->iou_id,
                'iou_code' => $row->iou_code,
                'iou_code_persis' => $row->iou_code_persis,
                'iou_desc' => $row->iou_desc,
                'iou_bursar_flag' => $row->iou_bursar_flag,
                'org_code' => $row->org_code,
                'org_desc' => $row->org_desc,
                'iou_address' => $row->iou_address,
                'iou_tel_no' => $row->iou_tel_no,
                'iou_fax_no' => $row->iou_fax_no,
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
        $row = IntOrganizationUnit::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Integration PTJ row not found');
        }

        return $this->sendOk([
            'iou_id' => (int) $row->iou_id,
            'iou_code' => $row->iou_code,
            'iou_code_persis' => $row->iou_code_persis,
            'iou_desc' => $row->iou_desc,
            'iou_bursar_flag' => $row->iou_bursar_flag,
            'org_code' => $row->org_code,
            'org_desc' => $row->org_desc,
            'iou_address' => $row->iou_address,
            'iou_tel_no' => $row->iou_tel_no,
            'iou_fax_no' => $row->iou_fax_no,
        ]);
    }

    public function options(): JsonResponse
    {
        $levels = OrganizationUnit::query()
            ->whereNotNull('oun_level')
            ->select('oun_level')
            ->distinct()
            ->orderBy('oun_level')
            ->pluck('oun_level')
            ->filter()
            ->values()
            ->map(fn ($v) => ['id' => (string) $v, 'label' => (string) $v]);

        return $this->sendOk([
            'levels' => $levels,
        ]);
    }

    public function parents(Request $request): JsonResponse
    {
        $level = (string) $request->input('level', '');
        $rows = OrganizationUnit::query()
            ->when($level !== '', fn ($q) => $q->where('oun_level', $level))
            ->orderBy('oun_code')
            ->limit(200)
            ->get(['oun_code', 'oun_desc']);

        return $this->sendOk(
            $rows->map(fn (OrganizationUnit $r) => [
                'id' => $r->oun_code,
                'label' => trim(($r->oun_code ?? '').' - '.($r->oun_desc ?? '')),
            ])
        );
    }

    public function promote(PromoteIntegrationPtjRequest $request, int $id): JsonResponse
    {
        $row = IntOrganizationUnit::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Integration PTJ row not found');
        }

        $data = $request->validated();
        $ptjCode = trim((string) $data['iou_code']);

        if (IntOrganizationUnit::query()
            ->where('iou_code', $ptjCode)
            ->where('iou_id', '!=', $row->iou_id)
            ->exists()
        ) {
            return $this->sendError(400, 'BAD_REQUEST', 'PTJ Code already exists in the integration table.');
        }

        $existsInProd = OrganizationUnit::query()
            ->where('oun_code', $ptjCode)
            ->exists();

        $username = $request->user()?->name ?? 'system';
        $orgCode = $data['org_code'] ?? null;
        $state = $orgCode === 'UUM' ? '02' : null;

        DB::connection('mysql_secondary')->transaction(function () use ($row, $data, $ptjCode, $existsInProd, $orgCode, $state, $username) {
            if (! $existsInProd) {
                $maxId = ((int) OrganizationUnit::query()->max('oun_id')) + 1;

                OrganizationUnit::create([
                    'oun_id' => $maxId,
                    'oun_code' => $ptjCode,
                    'oun_desc' => $data['iou_desc'],
                    'org_code' => $orgCode,
                    'org_desc' => $data['org_desc'] ?? null,
                    'oun_address' => $data['iou_address'] ?? null,
                    'oun_tel_no' => $data['iou_tel_no'] ?? null,
                    'oun_fax_no' => $data['iou_fax_no'] ?? null,
                    'oun_level' => $data['oun_level'] ?? null,
                    'oun_code_parent' => $data['oun_code_parent'] ?? null,
                    'oun_status' => '1',
                    'oun_state' => $state,
                    'createddate' => now(),
                    'createdby' => $username,
                    'oun_extended_field' => json_encode([
                        'persis_code' => $data['iou_code_persis'] ?? null,
                        'bursar_flag' => $data['iou_bursar_flag'] ?? null,
                    ]),
                ]);

                $row->update([
                    'iou_code' => $ptjCode,
                    'iou_desc' => $data['iou_desc'],
                    'updateddate' => now(),
                    'updatedby' => $username,
                ]);

                return;
            }

            // Existing row in organization_unit — sync the int_ row to its description.
            $prodDesc = OrganizationUnit::query()->where('oun_code', $ptjCode)->value('oun_desc');
            $row->update([
                'iou_code' => $ptjCode,
                'iou_desc' => $prodDesc ?: $data['iou_desc'],
                'updateddate' => now(),
                'updatedby' => $username,
            ]);
        });

        return $this->sendOk(['success' => true]);
    }
}
