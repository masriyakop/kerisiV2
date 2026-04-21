<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLookupBankMainRequest;
use App\Http\Requests\UpdateLookupBankMainRequest;
use App\Http\Traits\ApiResponse;
use App\Models\LookupBankMain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * "Cashbook > Bank Setup" listing & CRUD endpoints (PAGEID 2680 / MENUID 3246).
 *
 * Source: FIMS BL `SNA_API_CASHBOOK_SETUPBANKMAIN`. Backed by table
 * `lookup_bank_main` on `mysql_secondary`. All filtering uses the Eloquent
 * query builder — no raw SQL strings.
 */
class BankSetupController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'lbm_bank_code');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSort = ['lbm_bank_code', 'lbm_bank_name', 'isBankMain', 'lbm_status', 'updateddate'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'lbm_bank_code';
        }

        $query = LookupBankMain::query();

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(lbm_bank_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(lbm_bank_name, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(isBankMain, "")) LIKE ?', [$like]);
            });
        }

        if ($request->filled('lbm_bank_code_sm')) {
            $query->where('lbm_bank_code', $request->input('lbm_bank_code_sm'));
        }
        if ($request->filled('is_bank_main_sm')) {
            $query->where('isBankMain', $request->input('is_bank_main_sm'));
        }
        if ($request->filled('lbm_status_sm')) {
            $want = strtoupper((string) $request->input('lbm_status_sm')) === 'ACTIVE' ? '1' : '0';
            $query->where('lbm_status', $want);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->values()
            ->map(fn (LookupBankMain $row, int $i) => [
                'index' => (($page - 1) * $limit) + $i + 1,
                'lbm_bank_code' => $row->lbm_bank_code,
                'lbm_bank_name' => $row->lbm_bank_name,
                'is_bank_main' => $row->isBankMain,
                'main_bank_label' => $row->isBankMain === 'Y' ? 'YES' : 'NO',
                'lbm_status' => (int) $row->lbm_status === 1 ? 'ACTIVE' : 'INACTIVE',
                'lbm_status_value' => (int) $row->lbm_status,
                'updated_date' => $this->formatDate($row->updateddate ?? $row->createddate),
            ]);

        return $this->sendOk($rows, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function show(string $code): JsonResponse
    {
        $row = LookupBankMain::query()->where('lbm_bank_code', $code)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Bank Setup not found');
        }

        return $this->sendOk([
            'lbm_bank_code' => $row->lbm_bank_code,
            'lbm_bank_name' => $row->lbm_bank_name,
            'is_bank_main' => $row->isBankMain,
            'lbm_status' => (int) $row->lbm_status,
        ]);
    }

    public function options(): JsonResponse
    {
        $bankCode = LookupBankMain::query()
            ->select('lbm_bank_code', 'lbm_bank_name')
            ->orderBy('lbm_bank_code')
            ->get()
            ->map(fn (LookupBankMain $r) => [
                'id' => $r->lbm_bank_code,
                'label' => trim(($r->lbm_bank_code ?? '') . ' - ' . ($r->lbm_bank_name ?? '')),
            ]);

        return $this->sendOk([
            'smartFilter' => [
                'bankCode' => $bankCode,
                'isBankMain' => [
                    ['id' => 'Y', 'label' => 'YES'],
                    ['id' => 'N', 'label' => 'NO'],
                ],
                'status' => [
                    ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
                    ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
                ],
            ],
            'popupModal' => [
                'isBankMain' => [
                    ['id' => 'N', 'label' => 'NO'],
                    ['id' => 'Y', 'label' => 'YES'],
                ],
                'status' => [
                    ['id' => 1, 'label' => 'ACTIVE'],
                    ['id' => 0, 'label' => 'INACTIVE'],
                ],
            ],
        ]);
    }

    public function store(StoreLookupBankMainRequest $request): JsonResponse
    {
        $data = $request->validated();
        $code = Str::upper(trim((string) $data['lbm_bank_code']));

        $existing = LookupBankMain::query()->where('lbm_bank_code', $code)->first();
        if ($existing) {
            return $this->sendError(400, 'BAD_REQUEST', "Code that you enter already exists. Please enter another code. Code Detail: {$existing->lbm_bank_code} - {$existing->lbm_bank_name}");
        }

        LookupBankMain::create([
            'lbm_bank_code' => $code,
            'lbm_bank_name' => trim((string) $data['lbm_bank_name']),
            'isBankMain' => $data['is_bank_main'] ?? 'N',
            'lbm_status' => (int) $data['lbm_status'],
            'createddate' => now(),
            'createdby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendCreated(['lbm_bank_code' => $code]);
    }

    private function formatDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function update(UpdateLookupBankMainRequest $request, string $code): JsonResponse
    {
        $row = LookupBankMain::query()->where('lbm_bank_code', $code)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Bank Setup not found');
        }

        $data = $request->validated();

        $row->update([
            'lbm_bank_name' => trim((string) $data['lbm_bank_name']),
            'isBankMain' => $data['is_bank_main'] ?? 'N',
            'lbm_status' => (int) $data['lbm_status'],
            'updateddate' => now(),
            'updatedby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendOk(['success' => true]);
    }
}
