<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCostCentreRequest;
use App\Http\Requests\UpdateCostCentreRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CostCentre;
use App\Models\LookupParameterMain;
use App\Models\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CostCentreController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 10);
        $q = trim((string) $request->input('q', ''));

        $query = CostCentre::query()->from('costcentre as CC')
            ->leftJoin('organization_unit as OU', 'CC.oun_code', '=', 'OU.oun_code')
            ->select([
                'CC.ccr_costcentre_id',
                'CC.ccr_costcentre',
                'CC.ccr_costcentre_desc',
                'CC.ccr_costcentre_desc_eng',
                'CC.oun_code',
                'OU.oun_desc as oun_codeDESC',
                'CC.ccr_address',
                'CC.ccr_hostel_code',
                'CC.ccr_status',
                'CC.ccr_flag_salary',
            ]);

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(CC.ccr_costcentre) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(CC.ccr_costcentre_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(CC.ccr_costcentre_desc_eng, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(CC.oun_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(OU.oun_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(CC.ccr_address, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(CC.ccr_hostel_code, "")) LIKE ?', [$like]);
            });
        }

        if ($request->filled('ccr_costcentre')) {
            $query->where('CC.ccr_costcentre', $request->input('ccr_costcentre'));
        }
        if ($request->filled('ptj_code_sm')) {
            $query->where('CC.oun_code', $request->input('ptj_code_sm'));
        }
        if ($request->filled('status_sm')) {
            $status = strtoupper((string) $request->input('status_sm')) === 'ACTIVE' ? '1' : '0';
            $query->where('CC.ccr_status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('CC.ccr_costcentre', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->values()
            ->map(fn ($row, $idx) => [
                'index' => (($page - 1) * $limit) + $idx + 1,
                'ccr_costcentre_id' => (int) $row->ccr_costcentre_id,
                'ccr_costcentre' => $row->ccr_costcentre,
                'ccr_costcentre_desc' => $row->ccr_costcentre_desc,
                'ccr_costcentre_desc_eng' => $row->ccr_costcentre_desc_eng,
                'oun_code' => $row->oun_code,
                'oun_codeDESC' => $row->oun_codeDESC,
                'ccr_address' => $row->ccr_address,
                'ccr_hostel_code' => $row->ccr_hostel_code,
                'ccr_status' => (string) $row->ccr_status === '1' ? 'ACTIVE' : 'INACTIVE',
                'ccr_status_value' => (string) $row->ccr_status === '1' ? 1 : 0,
                'ccr_flag_salary' => $row->ccr_flag_salary,
            ]);

        return $this->sendOk($rows, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $row = CostCentre::query()->where('ccr_costcentre_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Cost Centre not found');
        }

        return $this->sendOk($row);
    }

    public function options(): JsonResponse
    {
        $codes = CostCentre::query()
            ->select('ccr_costcentre', 'ccr_costcentre_desc')
            ->orderBy('ccr_costcentre')
            ->get()
            ->map(fn ($row) => ['id' => $row->ccr_costcentre, 'label' => $row->ccr_costcentre . ' - ' . $row->ccr_costcentre_desc]);

        $ptj = OrganizationUnit::query()
            ->select('oun_code', 'oun_desc')
            ->whereNotNull('oun_code')
            ->orderBy('oun_code')
            ->get()
            ->map(fn ($row) => ['id' => $row->oun_code, 'label' => $row->oun_code . ' - ' . $row->oun_desc]);

        return $this->sendOk([
            'smartFilter' => [
                'costCentre' => $codes,
                'ptjCode' => $ptj,
                'status' => [
                    ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
                    ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
                ],
            ],
            'popupModal' => [
                'ptjCode' => $ptj,
                'status' => [
                    ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
                    ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
                ],
                'flagSalary' => [
                    ['id' => 'Y', 'label' => 'YES'],
                    ['id' => 'N', 'label' => 'NO'],
                ],
            ],
        ]);
    }

    public function store(StoreCostCentreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $code = Str::upper(trim($data['ccr_costcentre']));

        $codeLength = (int) (LookupParameterMain::query()->where('lpm_code', 'FINAL_COSTCENTRE_LENGTH')->value('lpm_value') ?? 0);
        if ($codeLength > 0 && strlen($code) !== $codeLength) {
            return $this->sendError(400, 'BAD_REQUEST', "Please make sure you insert {$codeLength} digit number to continue.");
        }

        $exists = CostCentre::query()->where('ccr_costcentre', $code)->first();
        if ($exists) {
            return $this->sendError(400, 'BAD_REQUEST', "CostCenter Code for {$exists->ccr_costcentre} - {$exists->ccr_costcentre_desc} already exist. Please fill in another code.");
        }

        $nextId = ((int) CostCentre::query()->max('ccr_costcentre_id')) + 1;
        $row = CostCentre::create([
            'ccr_costcentre_id' => $nextId,
            'ccr_costcentre' => $code,
            'ccr_costcentre_desc' => trim($data['ccr_costcentre_desc']),
            'ccr_costcentre_desc_eng' => $data['ccr_costcentre_desc_eng'] ?? null,
            'oun_code' => Str::upper(trim($data['oun_code'])),
            'ccr_address' => $data['ccr_address'] ?? null,
            'ccr_hostel_code' => $data['ccr_hostel_code'] ?? null,
            'ccr_status' => strtoupper($data['ccr_status']) === 'ACTIVE' ? '1' : '0',
            'ccr_flag_salary' => $data['ccr_flag_salary'],
            'createddate' => now(),
            'createdby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendCreated(['id' => (int) $row->ccr_costcentre_id]);
    }

    public function update(UpdateCostCentreRequest $request, int $id): JsonResponse
    {
        $row = CostCentre::query()->where('ccr_costcentre_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Cost Centre not found');
        }

        $data = $request->validated();
        $row->update([
            'ccr_costcentre' => Str::upper(trim($data['ccr_costcentre'])),
            'ccr_costcentre_desc' => trim($data['ccr_costcentre_desc']),
            'ccr_costcentre_desc_eng' => $data['ccr_costcentre_desc_eng'] ?? null,
            'oun_code' => Str::upper(trim($data['oun_code'])),
            'ccr_address' => $data['ccr_address'] ?? null,
            'ccr_hostel_code' => $data['ccr_hostel_code'] ?? null,
            'ccr_status' => strtoupper($data['ccr_status']) === 'ACTIVE' ? '1' : '0',
            'ccr_flag_salary' => $data['ccr_flag_salary'],
            'updateddate' => now(),
            'updatedby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendOk(['success' => true]);
    }
}
