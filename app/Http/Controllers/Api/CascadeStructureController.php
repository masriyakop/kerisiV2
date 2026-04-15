<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCascadeStructureRequest;
use App\Http\Requests\UpdateCascadeStructureRequest;
use App\Http\Traits\ApiResponse;
use App\Models\ActivityType;
use App\Models\CostCentre;
use App\Models\FundType;
use App\Models\OrgUnitCostCentre;
use App\Models\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CascadeStructureController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 10);
        $q = trim((string) $request->input('q', ''));

        $query = OrgUnitCostCentre::query()
            ->from('org_unit_costcentre as OUCC')
            ->leftJoin('fund_type as FT', 'OUCC.fty_fund_type', '=', 'FT.fty_fund_type')
            ->leftJoin('activity_type as AT', 'OUCC.at_activity_code', '=', 'AT.at_activity_code')
            ->leftJoin('organization_unit as OUN', 'OUCC.oun_code', '=', 'OUN.oun_code')
            ->leftJoin('costcentre as CCR', 'OUCC.ccr_costcentre', '=', 'CCR.ccr_costcentre')
            ->select([
                'OUCC.ouc_ounit_costcentre_id',
                'OUCC.fty_fund_type',
                'FT.fty_fund_desc',
                'OUCC.at_activity_code',
                'AT.at_activity_description_bm',
                'OUCC.oun_code',
                'OUN.oun_desc',
                'OUCC.ccr_costcentre',
                'CCR.ccr_costcentre_desc',
                'OUCC.ouc_status',
            ]);

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(OUCC.fty_fund_type, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(FT.fty_fund_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(OUCC.at_activity_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(AT.at_activity_description_bm, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(OUCC.oun_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(OUN.oun_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(OUCC.ccr_costcentre, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(CCR.ccr_costcentre_desc, "")) LIKE ?', [$like]);
            });
        }

        foreach (['fty_fund_type_sm' => 'OUCC.fty_fund_type', 'activity_sm' => 'OUCC.at_activity_code', 'oun_code_ptj' => 'OUCC.oun_code', 'costcenter_sm' => 'OUCC.ccr_costcentre'] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }
        if ($request->filled('ouc_status')) {
            $query->where('OUCC.ouc_status', strtoupper((string) $request->input('ouc_status')) === 'ACTIVE' ? '1' : '0');
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('OUCC.ouc_ounit_costcentre_id')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->values()
            ->map(fn ($row) => [
                'ouc_ounit_costcentre_id' => (int) $row->ouc_ounit_costcentre_id,
                'fty_fund_type' => $row->fty_fund_type,
                'fty_fund_desc' => $row->fty_fund_desc,
                'at_activity_code' => $row->at_activity_code,
                'at_activity_description_bm' => $row->at_activity_description_bm,
                'oun_code' => $row->oun_code,
                'oun_desc' => $row->oun_desc,
                'ccr_costcentre' => $row->ccr_costcentre,
                'ccr_costcentre_desc' => $row->ccr_costcentre_desc,
                'ouc_status' => (string) $row->ouc_status === '1' ? 'ACTIVE' : 'INACTIVE',
                'ouc_status_value' => (string) $row->ouc_status === '1' ? 1 : 0,
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
        $row = OrgUnitCostCentre::query()->where('ouc_ounit_costcentre_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Cascade Structure not found');
        }

        return $this->sendOk($row);
    }

    public function options(Request $request): JsonResponse
    {
        $ptjCode = $request->input('ptj_code');

        $fund = FundType::query()->select('fty_fund_type', 'fty_fund_desc')->where('fty_status', 1)->orderBy('fty_fund_type')->get()
            ->map(fn ($row) => ['id' => $row->fty_fund_type, 'label' => $row->fty_fund_type . ' - ' . $row->fty_fund_desc]);
        $activity = ActivityType::query()->select('at_activity_code', 'at_activity_description_bm')->where('at_status', 1)->orderBy('at_activity_code')->get()
            ->map(fn ($row) => ['id' => $row->at_activity_code, 'label' => $row->at_activity_code . ' - ' . $row->at_activity_description_bm]);
        $ptj = OrganizationUnit::query()->select('oun_code', 'oun_desc')->whereNotNull('oun_code')->orderBy('oun_code')->get()
            ->map(fn ($row) => ['id' => $row->oun_code, 'label' => $row->oun_code . ' - ' . $row->oun_desc]);
        $cost = CostCentre::query()->select('ccr_costcentre', 'ccr_costcentre_desc')
            ->when($ptjCode, fn ($q) => $q->where('oun_code', Str::upper((string) $ptjCode)))
            ->orderBy('ccr_costcentre')->get()
            ->map(fn ($row) => ['id' => $row->ccr_costcentre, 'label' => $row->ccr_costcentre . ' - ' . $row->ccr_costcentre_desc]);

        return $this->sendOk([
            'smartFilter' => [
                'fund' => $fund,
                'activity' => $activity,
                'ptj' => $ptj,
                'costCenter' => $cost,
                'status' => [
                    ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
                    ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
                ],
            ],
            'popupModal' => [
                'fund' => $fund,
                'activity' => $activity,
                'ptj' => $ptj,
                'costCenter' => $cost,
                'status' => [
                    ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
                    ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
                ],
            ],
        ]);
    }

    public function store(StoreCascadeStructureRequest $request): JsonResponse
    {
        $data = $request->validated();
        $exists = OrgUnitCostCentre::query()
            ->where('fty_fund_type', $data['fty_fund_type'])
            ->where('at_activity_code', $data['at_activity_code'])
            ->where('oun_code', $data['oun_code'])
            ->where('ccr_costcentre', $data['ccr_costcentre'])
            ->exists();

        if ($exists) {
            return $this->sendError(400, 'BAD_REQUEST', 'The data you selected already exists.');
        }

        $nextId = ((int) OrgUnitCostCentre::query()->max('ouc_ounit_costcentre_id')) + 1;
        $row = OrgUnitCostCentre::create([
            'ouc_ounit_costcentre_id' => $nextId,
            'fty_fund_type' => Str::upper(trim($data['fty_fund_type'])),
            'at_activity_code' => Str::upper(trim($data['at_activity_code'])),
            'oun_code' => Str::upper(trim($data['oun_code'])),
            'ccr_costcentre' => Str::upper(trim($data['ccr_costcentre'])),
            'ouc_status' => strtoupper($data['ouc_status']) === 'ACTIVE' ? '1' : '0',
            'createddate' => now(),
            'createdby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendCreated(['id' => (int) $row->ouc_ounit_costcentre_id]);
    }

    public function update(UpdateCascadeStructureRequest $request, int $id): JsonResponse
    {
        $row = OrgUnitCostCentre::query()->where('ouc_ounit_costcentre_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Cascade Structure not found');
        }

        $data = $request->validated();
        $row->update([
            'fty_fund_type' => Str::upper(trim($data['fty_fund_type'])),
            'at_activity_code' => Str::upper(trim($data['at_activity_code'])),
            'oun_code' => Str::upper(trim($data['oun_code'])),
            'ccr_costcentre' => Str::upper(trim($data['ccr_costcentre'])),
            'ouc_status' => strtoupper($data['ouc_status']) === 'ACTIVE' ? '1' : '0',
            'updateddate' => now(),
            'updatedby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendOk(['success' => true]);
    }
}
