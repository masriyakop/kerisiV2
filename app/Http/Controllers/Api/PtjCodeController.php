<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePtjCodeRequest;
use App\Http\Requests\UpdatePtjCodeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PtjCodeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $level = (int) $request->input('level', 1);
        if ($level < 1 || $level > 4) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'level must be between 1 and 4.');
        }

        $parentCode = trim((string) $request->input('oun_code_parent', ''));
        if ($level > 1 && $parentCode === '') {
            return $this->sendError(422, 'VALIDATION_ERROR', 'oun_code_parent is required for level 2 and above.');
        }

        $search = trim((string) $request->input('search', ''));
        $statusFilter = strtoupper(trim((string) $request->input('smart_filter_oun_status', '')));

        $query = OrganizationUnit::query()
            ->leftJoin('country', 'organization_unit.cny_country_code', '=', 'country.cny_country_code')
            ->leftJoin('lkp_region', 'organization_unit.oun_region', '=', 'lkp_region.lrg_region')
            ->where('organization_unit.oun_level', $level)
            ->select([
                'organization_unit.*',
                'country.cny_country_desc',
                'lkp_region.lrg_region_desc',
            ]);

        if ($level > 1) {
            $query->where('organization_unit.oun_code_parent', Str::upper($parentCode));
        }

        if ($search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(organization_unit.oun_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(organization_unit.oun_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(organization_unit.oun_desc_bi, "")) LIKE ?', [$like]);
            });
        }

        if ($statusFilter === 'ACTIVE') {
            $query->where('organization_unit.oun_status', '1');
        } elseif ($statusFilter === 'INACTIVE') {
            $query->where('organization_unit.oun_status', '0');
        }

        $rows = $query->orderBy('organization_unit.oun_code')->get()->map(function ($row) {
            $statusValue = (string) $row->oun_status === '1' ? '1' : '0';

            return [
                'oun_id' => (int) $row->oun_id,
                'oun_code' => $row->oun_code,
                'oun_desc' => $row->oun_desc,
                'oun_desc_bi' => $row->oun_desc_bi,
                'org_code' => $row->org_code,
                'org_desc' => $row->org_desc,
                'oun_address' => $row->oun_address,
                'oun_state' => $row->oun_state,
                'st_staff_id_head' => $row->st_staff_id_head,
                'st_staff_id_superior' => $row->st_staff_id_superior,
                'oun_tel_no' => $row->oun_tel_no,
                'oun_fax_no' => $row->oun_fax_no,
                'oun_code_parent' => $row->oun_code_parent,
                'oun_level' => (int) $row->oun_level,
                'oun_status' => $statusValue === '1' ? 'ACTIVE' : 'INACTIVE',
                'oun_status_value' => $statusValue,
                'tanggung_start_date' => $row->tanggung_start_date,
                'tanggung_end_date' => $row->tanggung_end_date,
                'oun_shortname' => $row->oun_shortname,
                'oun_region' => $row->oun_region,
                'lrg_region_desc' => $row->lrg_region_desc,
                'cny_country_code' => $row->cny_country_code,
                'cny_country_desc' => $row->cny_country_desc,
            ];
        });

        return $this->sendOk($rows);
    }

    public function store(StorePtjCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $level = (int) $data['oun_level'];
        $code = Str::upper(trim($data['oun_code']));
        $parentCode = filled($data['oun_code_parent'] ?? null) ? Str::upper(trim((string) $data['oun_code_parent'])) : null;

        if ($level > 1 && ! $parentCode) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'oun_code_parent is required for level 2 and above.');
        }

        if (OrganizationUnit::query()->where('oun_code', $code)->exists()) {
            return $this->sendError(400, 'BAD_REQUEST', 'PTJ code already exists.');
        }

        if ($parentCode && ! OrganizationUnit::query()->where('oun_code', $parentCode)->exists()) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'Parent PTJ does not exist.');
        }

        $row = DB::connection('mysql_secondary')->transaction(function () use ($data, $code, $level, $parentCode) {
            $maxId = (int) OrganizationUnit::query()->lockForUpdate()->max('oun_id');

            return OrganizationUnit::create([
                'oun_id' => $maxId + 1,
                'oun_code' => $code,
                'oun_desc' => trim($data['oun_desc']),
                'oun_desc_bi' => filled($data['oun_desc_bi'] ?? null) ? trim((string) $data['oun_desc_bi']) : null,
                'org_code' => trim($data['org_code']),
                'org_desc' => filled($data['org_desc'] ?? null) ? trim((string) $data['org_desc']) : null,
                'oun_address' => filled($data['oun_address'] ?? null) ? trim((string) $data['oun_address']) : null,
                'oun_state' => filled($data['oun_state'] ?? null) ? trim((string) $data['oun_state']) : null,
                'st_staff_id_head' => filled($data['st_staff_id_head'] ?? null) ? trim((string) $data['st_staff_id_head']) : null,
                'st_staff_id_superior' => filled($data['st_staff_id_superior'] ?? null) ? trim((string) $data['st_staff_id_superior']) : null,
                'oun_tel_no' => filled($data['oun_tel_no'] ?? null) ? trim((string) $data['oun_tel_no']) : null,
                'oun_fax_no' => filled($data['oun_fax_no'] ?? null) ? trim((string) $data['oun_fax_no']) : null,
                'oun_code_parent' => $parentCode,
                'oun_level' => $level,
                'oun_status' => strtoupper($data['oun_status']) === 'ACTIVE' ? '1' : '0',
                'tanggung_start_date' => $data['tanggung_start_date'] ?? null,
                'tanggung_end_date' => $data['tanggung_end_date'] ?? null,
                'oun_shortname' => filled($data['oun_shortname'] ?? null) ? trim((string) $data['oun_shortname']) : null,
                'oun_region' => filled($data['oun_region'] ?? null) ? trim((string) $data['oun_region']) : null,
                'cny_country_code' => filled($data['cny_country_code'] ?? null) ? trim((string) $data['cny_country_code']) : null,
                'createddate' => now(),
            ]);
        });

        return $this->sendCreated(['oun_id' => (int) $row->oun_id, 'oun_code' => $row->oun_code]);
    }

    public function update(UpdatePtjCodeRequest $request, string $code): JsonResponse
    {
        $row = OrganizationUnit::query()->where('oun_code', Str::upper(trim($code)))->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'PTJ code not found.');
        }

        $data = $request->validated();
        $parentCode = filled($data['oun_code_parent'] ?? null) ? Str::upper(trim((string) $data['oun_code_parent'])) : null;
        if ($parentCode && ! OrganizationUnit::query()->where('oun_code', $parentCode)->exists()) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'Parent PTJ does not exist.');
        }

        $row->update([
            'oun_desc' => trim($data['oun_desc']),
            'oun_desc_bi' => filled($data['oun_desc_bi'] ?? null) ? trim((string) $data['oun_desc_bi']) : null,
            'org_code' => filled($data['org_code'] ?? null) ? trim((string) $data['org_code']) : $row->org_code,
            'org_desc' => filled($data['org_desc'] ?? null) ? trim((string) $data['org_desc']) : null,
            'oun_address' => filled($data['oun_address'] ?? null) ? trim((string) $data['oun_address']) : null,
            'oun_state' => filled($data['oun_state'] ?? null) ? trim((string) $data['oun_state']) : null,
            'st_staff_id_head' => filled($data['st_staff_id_head'] ?? null) ? trim((string) $data['st_staff_id_head']) : null,
            'st_staff_id_superior' => filled($data['st_staff_id_superior'] ?? null) ? trim((string) $data['st_staff_id_superior']) : null,
            'oun_tel_no' => filled($data['oun_tel_no'] ?? null) ? trim((string) $data['oun_tel_no']) : null,
            'oun_fax_no' => filled($data['oun_fax_no'] ?? null) ? trim((string) $data['oun_fax_no']) : null,
            'oun_code_parent' => array_key_exists('oun_code_parent', $data) ? $parentCode : $row->oun_code_parent,
            'oun_level' => array_key_exists('oun_level', $data) ? (int) $data['oun_level'] : $row->oun_level,
            'oun_status' => strtoupper($data['oun_status']) === 'ACTIVE' ? '1' : '0',
            'tanggung_start_date' => $data['tanggung_start_date'] ?? null,
            'tanggung_end_date' => $data['tanggung_end_date'] ?? null,
            'oun_shortname' => filled($data['oun_shortname'] ?? null) ? trim((string) $data['oun_shortname']) : null,
            'oun_region' => filled($data['oun_region'] ?? null) ? trim((string) $data['oun_region']) : null,
            'cny_country_code' => filled($data['cny_country_code'] ?? null) ? trim((string) $data['cny_country_code']) : null,
            'updateddate' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }

    public function destroy(string $code): JsonResponse
    {
        $ptjCode = Str::upper(trim($code));
        $hasChildren = OrganizationUnit::query()->where('oun_code_parent', $ptjCode)->exists();
        if ($hasChildren) {
            return $this->sendError(409, 'BAD_REQUEST', 'Unable to delete PTJ because child PTJ records exist.');
        }

        OrganizationUnit::query()->where('oun_code', $ptjCode)->delete();

        return $this->sendOk(['success' => true]);
    }
}
