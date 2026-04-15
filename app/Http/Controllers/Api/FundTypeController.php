<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFundTypeRequest;
use App\Http\Requests\UpdateFundTypeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\FundType;
use App\Models\LookupParameterMain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FundTypeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 10);
        $q = $request->input('q');
        $sortBy = $request->input('sort_by', 'fty_fund_type');
        $sortDir = $request->input('sort_dir', 'asc');

        $fundTypeSm = $request->input('fty_fund_type_sm');
        $basisSm = $request->input('fty_basis_sm');
        $statusSm = $request->input('fty_status_sm');

        $allowedSortBy = [
            'fty_fund_type',
            'fty_fund_desc',
            'fty_fund_desc_eng',
            'fty_basis',
            'fty_status',
        ];
        if (! in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'fty_fund_type';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $query = FundType::query();

        if ($q) {
            $needle = mb_strtolower(trim((string) $q), 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(fty_fund_type, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(fty_fund_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(fty_fund_desc_eng, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(fty_basis, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(fty_remark, "")) LIKE ?', [$like]);
            });
        }

        if ($fundTypeSm) {
            $query->where('fty_fund_type', $fundTypeSm);
        }
        if ($basisSm) {
            $query->where('fty_basis', $basisSm);
        }
        if ($statusSm === 'ACTIVE') {
            $query->where('fty_status', 1);
        } elseif ($statusSm === 'INACTIVE') {
            $query->where('fty_status', 0);
        }

        $total = $query->count();
        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (FundType $row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'fty_fund_id' => (int) $row->fty_fund_id,
                'fty_fund_type' => $row->fty_fund_type,
                'fty_fund_desc' => $row->fty_fund_desc,
                'fty_fund_desc_eng' => $row->fty_fund_desc_eng,
                'fty_basis' => $row->fty_basis,
                'fty_remark' => $row->fty_remark,
                'fty_status' => ((int) $row->fty_status === 1) ? 'ACTIVE' : 'INACTIVE',
                'fty_status_value' => (int) $row->fty_status,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $row = FundType::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Fund Type not found');
        }

        return $this->sendOk([
            'id' => (int) $row->fty_fund_id,
            'fty_fund_type' => $row->fty_fund_type,
            'fty_fund_desc' => $row->fty_fund_desc,
            'fty_fund_desc_eng' => $row->fty_fund_desc_eng,
            'fty_basis' => $row->fty_basis,
            'fty_status' => (int) $row->fty_status,
            'fty_remark' => $row->fty_remark,
            'fty_extended_field' => $row->fty_extended_field,
        ]);
    }

    public function formOptions(): JsonResponse
    {
        $fundTypeOptions = FundType::query()
            ->select('fty_fund_type', 'fty_fund_desc')
            ->distinct()
            ->orderBy('fty_fund_type')
            ->get()
            ->map(fn (FundType $row) => [
                'id' => $row->fty_fund_type,
                'label' => trim(($row->fty_fund_type ?? '').' - '.($row->fty_fund_desc ?? '')),
            ]);

        return $this->sendOk([
            'smartFilter' => [
                'fundType' => $fundTypeOptions,
                'basis' => [
                    ['id' => 'ALLOCATION BASIS', 'label' => 'ALLOCATION BASIS'],
                    ['id' => 'ALLOCATION AND CASH BASIS', 'label' => 'ALLOCATION AND CASH BASIS'],
                    ['id' => 'CASH BASIS', 'label' => 'CASH BASIS'],
                ],
                'status' => [
                    ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
                    ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
                ],
            ],
            'popupModal' => [
                'basis' => [
                    ['id' => 'ALLOCATION BASIS', 'label' => 'ALLOCATION BASIS'],
                    ['id' => 'ALLOCATION AND PROJECT BASIS', 'label' => 'ALLOCATION AND PROJECT BASIS'],
                    ['id' => 'CASH BASIS', 'label' => 'CASH BASIS'],
                    ['id' => 'PROJECT BASIS', 'label' => 'PROJECT BASIS'],
                ],
                'status' => [
                    ['id' => 1, 'label' => 'ACTIVE'],
                    ['id' => 0, 'label' => 'INACTIVE'],
                ],
            ],
        ]);
    }

    public function store(StoreFundTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $fundType = Str::upper(trim($data['fty_fund_type']));

        $codeLength = (int) (LookupParameterMain::query()
            ->where('lpm_code', 'FINAL_FUND_TYPE_LENGTH')
            ->value('lpm_value') ?? 0);

        if ($codeLength > 0 && strlen($fundType) !== $codeLength) {
            return $this->sendError(400, 'BAD_REQUEST', "Please make sure you insert {$codeLength} digit number to continue.");
        }

        $existing = FundType::query()->where('fty_fund_type', $fundType)->first();
        if ($existing) {
            $currentStatus = ((int) $existing->fty_status === 1) ? 'ACTIVE' : 'INACTIVE';
            return $this->sendError(400, 'BAD_REQUEST', "Fund Code for {$existing->fty_fund_type} - {$existing->fty_fund_desc} already exist with status {$currentStatus}. Please fill in another code.");
        }

        $nextId = ((int) FundType::query()->max('fty_fund_id')) + 1;
        $status = (int) $data['fty_status'] === 1 ? 1 : 0;
        $statusDesc = $status === 1 ? 'ACTIVE' : 'INACTIVE';

        $row = FundType::create([
            'fty_fund_id' => $nextId,
            'fty_fund_type' => $fundType,
            'fty_fund_desc' => Str::upper(trim($data['fty_fund_desc'])),
            'fty_fund_desc_eng' => filled($data['fty_fund_desc_eng'] ?? null) ? Str::upper(trim((string) $data['fty_fund_desc_eng'])) : null,
            'fty_basis' => trim($data['fty_basis']),
            'fty_status' => $status,
            'fty_remark' => $data['fty_remark'] ?? null,
            'fty_extended_field' => json_encode(['statusDesc' => $statusDesc]),
            'createdby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendCreated(['id' => (int) $row->fty_fund_id]);
    }

    public function update(UpdateFundTypeRequest $request, int $id): JsonResponse
    {
        $row = FundType::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Fund Type not found');
        }

        $data = $request->validated();
        $fundType = Str::upper(trim($data['fty_fund_type']));

        $codeLength = (int) (LookupParameterMain::query()
            ->where('lpm_code', 'FINAL_FUND_TYPE_LENGTH')
            ->value('lpm_value') ?? 0);

        if ($codeLength > 0 && strlen($fundType) !== $codeLength) {
            return $this->sendError(400, 'BAD_REQUEST', "Please make sure you insert {$codeLength} digit number to continue.");
        }

        $status = (int) $data['fty_status'] === 1 ? 1 : 0;
        $statusDesc = $status === 1 ? 'ACTIVE' : 'INACTIVE';

        $row->update([
            'fty_fund_type' => $fundType,
            'fty_fund_desc' => Str::upper(trim($data['fty_fund_desc'])),
            'fty_fund_desc_eng' => filled($data['fty_fund_desc_eng'] ?? null) ? Str::upper(trim((string) $data['fty_fund_desc_eng'])) : null,
            'fty_basis' => trim($data['fty_basis']),
            'fty_status' => $status,
            'fty_remark' => $data['fty_remark'] ?? null,
            'fty_extended_field' => json_encode(['statusDesc' => $statusDesc]),
            'updatedby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendOk(['success' => true]);
    }
}
