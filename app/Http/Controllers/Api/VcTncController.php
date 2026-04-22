<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVcTncRequest;
use App\Http\Traits\ApiResponse;
use App\Models\OrganizationUnit;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HOD, VC & TNC setup (legacy PAGEID 1715 / MENUID 2073).
 *
 * Backed by `organization_unit` joined with `staff`. The legacy
 * `API_VC_TNC_SETUP` BL exposed:
 *   - vctnc       → datatable list with HOD name and superior from
 *                   `oun_extended_field->>'$.st_staff_name_superior'`
 *   - displayData → single-row fetch for the edit modal
 *   - saveData    → updates `st_staff_id_head`, `st_staff_id_superior`, and the
 *                   `oun_extended_field` JSON keys `st_staff_name_superior` and
 *                   `st_staff_title_superior`
 *
 * The VC/TNC dropdown source query was
 *   SELECT stf_staff_id FLC_ID,
 *          CONCAT(UPPER(stf_extended_field->>'$.stf_title_desc'), stf_staff_name) FLC_NAME,
 *          stf_extended_field->>'$.stf_title_desc' _title
 *   FROM fims_usr.staff
 *   WHERE stf_staff_id IN ('VC0','TNC001','TNC002','TNC003');
 */
class VcTncController extends Controller
{
    use ApiResponse;

    private const SUPERIOR_STAFF_IDS = ['VC0', 'TNC001', 'TNC002', 'TNC003'];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'oun_code');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc'));

        $allowedSortBy = ['oun_code', 'oun_desc', 'st_staff_id_head', 'st_staff_id_superior'];
        if (! in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'oun_code';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $query = OrganizationUnit::query()
            ->from('organization_unit')
            ->leftJoin('staff as head_staff', 'organization_unit.st_staff_id_head', '=', 'head_staff.stf_staff_id')
            ->select([
                'organization_unit.oun_id',
                'organization_unit.oun_code',
                'organization_unit.oun_desc',
                'organization_unit.st_staff_id_head',
                'organization_unit.st_staff_id_superior',
                'head_staff.stf_staff_name as head_stf_staff_name',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(organization_unit.oun_extended_field, '$.st_staff_name_superior')) as superior_stf_staff_name"),
            ]);

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(organization_unit.oun_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(organization_unit.oun_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(organization_unit.st_staff_id_superior, "")) LIKE ?', [$like]);
            });
        }

        $total = (clone $query)->count('organization_unit.oun_id');
        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            $headId = $row->st_staff_id_head;
            $headName = $row->head_stf_staff_name;
            $superiorId = $row->st_staff_id_superior;
            $superiorName = $row->superior_stf_staff_name;

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'id' => (int) $row->oun_id,
                'oun_code' => $row->oun_code,
                'oun_desc' => $row->oun_desc,
                'st_staff_id_head' => $headId ? (string) $headId : null,
                'st_staff_id_head_label' => $headId
                    ? trim(($headId ?? '').($headName ? ' - '.$headName : ''))
                    : null,
                'st_staff_id_superior' => $superiorId ? (string) $superiorId : null,
                'st_staff_id_superior_label' => $superiorId
                    ? trim(($superiorId ?? '').($superiorName ? ' - '.$superiorName : ''))
                    : null,
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
        $row = OrganizationUnit::query()
            ->from('organization_unit')
            ->leftJoin('staff as head_staff', 'organization_unit.st_staff_id_head', '=', 'head_staff.stf_staff_id')
            ->where('organization_unit.oun_id', $id)
            ->select([
                'organization_unit.oun_id',
                'organization_unit.oun_code',
                'organization_unit.oun_desc',
                'organization_unit.st_staff_id_head',
                'organization_unit.st_staff_id_superior',
                'head_staff.stf_staff_name as head_stf_staff_name',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(organization_unit.oun_extended_field, '$.st_staff_name_superior')) as superior_stf_staff_name"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(organization_unit.oun_extended_field, '$.st_staff_title_superior')) as superior_stf_title_superior"),
            ])
            ->first();

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Organization Unit not found');
        }

        $headId = $row->st_staff_id_head;
        $headName = $row->head_stf_staff_name;

        return $this->sendOk([
            'id' => (int) $row->oun_id,
            'oun_code' => $row->oun_code,
            'oun_desc' => $row->oun_desc,
            'st_staff_id_head' => $headId ? (string) $headId : null,
            'st_staff_id_head_label' => $headId
                ? trim(($headId ?? '').($headName ? ' - '.$headName : ''))
                : null,
            'st_staff_id_superior' => $row->st_staff_id_superior ? (string) $row->st_staff_id_superior : null,
            'st_staff_name_superior' => $row->superior_stf_staff_name,
            'st_staff_title_superior' => $row->superior_stf_title_superior,
        ]);
    }

    public function options(): JsonResponse
    {
        $rows = Staff::query()
            ->whereIn('stf_staff_id', self::SUPERIOR_STAFF_IDS)
            ->select([
                'stf_staff_id',
                'stf_staff_name',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(stf_extended_field, '$.stf_title_desc')) as stf_title_desc"),
            ])
            ->orderBy('stf_staff_id')
            ->get();

        $superior = $rows->map(function ($row) {
            $title = $row->stf_title_desc ? mb_strtoupper((string) $row->stf_title_desc) : '';
            $name = (string) ($row->stf_staff_name ?? '');
            $label = trim($title.' '.$name);
            if ($label === '') {
                $label = (string) $row->stf_staff_id;
            }

            return [
                'id' => (string) $row->stf_staff_id,
                'label' => $label,
                'title' => $row->stf_title_desc,
                'staffName' => $row->stf_staff_name,
            ];
        })->values();

        return $this->sendOk([
            'popupModal' => [
                'superior' => $superior,
            ],
        ]);
    }

    public function update(UpdateVcTncRequest $request, int $id): JsonResponse
    {
        $row = OrganizationUnit::query()->where('oun_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Organization Unit not found');
        }

        $superiorId = trim((string) $request->validated('st_staff_id_superior'));
        if (! in_array($superiorId, self::SUPERIOR_STAFF_IDS, true)) {
            return $this->sendError(
                422,
                'VALIDATION_ERROR',
                'Selected VC/TNC staff is not in the allowed list.',
                ['st_staff_id_superior' => ['Selected VC/TNC staff is not in the allowed list.']],
            );
        }

        $staff = Staff::query()
            ->select([
                'stf_staff_id',
                'stf_staff_name',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(stf_extended_field, '$.stf_title_desc')) as stf_title_desc"),
            ])
            ->where('stf_staff_id', $superiorId)
            ->first();

        if (! $staff) {
            return $this->sendError(404, 'NOT_FOUND', 'Superior staff not found');
        }

        $existing = [];
        if (filled($row->oun_extended_field)) {
            $decoded = json_decode((string) $row->oun_extended_field, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        $existing['st_staff_name_superior'] = (string) ($staff->stf_staff_name ?? '');
        $existing['st_staff_title_superior'] = (string) ($staff->stf_title_desc ?? '');

        $row->update([
            'st_staff_id_superior' => $superiorId,
            'oun_extended_field' => json_encode($existing, JSON_UNESCAPED_UNICODE),
            'updatedby' => $request->user()?->name ?? 'system',
            'updateddate' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }
}
