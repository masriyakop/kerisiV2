<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Http\Traits\ApiResponse;
use App\Models\AccountMain;
use App\Models\BankDetl;
use App\Models\BankMaster;
use App\Models\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Cashbook > Bank Account" listing & CRUD endpoints (PAGEID 1736 / MENUID 2097).
 *
 * Source: FIMS BL `SNA_API_CASHBOOK_BANKACCOUNT`. Backed by `bank_detl`
 * joined with `bank_master` and `account_main` on `mysql_secondary`.
 * Filtering uses the Eloquent query builder.
 */
class BankAccountController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'bnd_bank_detl_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSort = ['bnd_bank_detl_id', 'bnm_bank_desc', 'bnd_bank_acctno', 'acm_acct_code', 'acm_acct_desc', 'bnd_status'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'bnd_bank_detl_id';
        }

        $query = BankDetl::query()
            ->from('bank_detl as BD')
            ->leftJoin('bank_master as BM', 'BM.bnm_bank_id', '=', 'BD.bnm_bank_id')
            ->leftJoin('account_main as AM', 'AM.acm_acct_code', '=', 'BD.acm_acct_code')
            ->select([
                'BD.bnd_bank_detl_id',
                'BD.bnm_bank_id',
                'BD.bnd_bank_acctno',
                'BD.acm_acct_code',
                'AM.acm_acct_desc',
                'BM.bnm_bank_code',
                'BM.bnm_bank_desc',
                'BD.bnd_status',
                'BD.oun_code',
                'BD.bnd_is_bank_main',
                'BD.createdby',
            ]);

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(BM.bnm_bank_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(BM.bnm_bank_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(BD.bnd_bank_acctno, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(BD.acm_acct_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(AM.acm_acct_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(BD.createdby, "")) LIKE ?', [$like]);
            });
        }

        if ($request->filled('bnm_bank_id_sm')) {
            $query->where('BD.bnm_bank_id', $request->input('bnm_bank_id_sm'));
        }
        if ($request->filled('bnd_status_sm')) {
            $want = strtoupper((string) $request->input('bnd_status_sm')) === 'ACTIVE' ? '1' : '0';
            $query->where('BD.bnd_status', $want);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->values()
            ->map(fn ($r, int $i) => [
                'index' => (($page - 1) * $limit) + $i + 1,
                'bnd_bank_detl_id' => (int) $r->bnd_bank_detl_id,
                'bnm_bank_desc' => $r->bnm_bank_desc,
                'bnd_bank_acctno' => $r->bnd_bank_acctno,
                'acm_acct_code' => $r->acm_acct_code,
                'acm_acct_desc' => $r->acm_acct_desc,
                'bnd_status' => (int) $r->bnd_status === 1 ? 'ACTIVE' : 'INACTIVE',
                'bnd_status_value' => (int) $r->bnd_status,
                'createdby' => $r->createdby,
            ]);

        return $this->sendOk($rows, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $row = BankDetl::query()
            ->from('bank_detl as BD')
            ->leftJoin('bank_master as BM', 'BM.bnm_bank_id', '=', 'BD.bnm_bank_id')
            ->leftJoin('account_main as AM', 'AM.acm_acct_code', '=', 'BD.acm_acct_code')
            ->where('BD.bnd_bank_detl_id', $id)
            ->select([
                'BD.bnd_bank_detl_id',
                'BD.bnm_bank_id',
                'BM.bnm_bank_code',
                'BM.bnm_bank_desc',
                'BD.bnd_bank_acctno',
                'BD.acm_acct_code',
                'AM.acm_acct_desc',
                'BD.oun_code',
                'BD.bnd_status',
                'BD.bnd_is_bank_main',
            ])
            ->first();

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Bank Account not found');
        }

        return $this->sendOk([
            'bnd_bank_detl_id' => (int) $row->bnd_bank_detl_id,
            'bnm_bank_id' => (int) $row->bnm_bank_id,
            'bnm_bank_code' => $row->bnm_bank_code,
            'bnm_bank_desc' => $row->bnm_bank_desc,
            'bnd_bank_acctno' => $row->bnd_bank_acctno,
            'acm_acct_code' => $row->acm_acct_code,
            'acm_acct_desc' => $row->acm_acct_desc,
            'oun_code' => $row->oun_code,
            'bnd_status' => (int) $row->bnd_status,
            'bnd_is_bank_main' => $row->bnd_is_bank_main ?? 'N',
        ]);
    }

    public function options(): JsonResponse
    {
        $bankNameOptions = BankMaster::query()
            ->select('bnm_bank_id', 'bnm_bank_code', 'bnm_bank_desc', 'bnm_shortname')
            ->orderBy('bnm_bank_desc')
            ->get()
            ->map(fn (BankMaster $r) => [
                'id' => (int) $r->bnm_bank_id,
                'label' => trim(implode(' - ', array_filter([$r->bnm_shortname, $r->bnm_bank_desc]))),
            ]);

        // Account codes: leaf-level, flagged for cashbook, NOT already used by bank_detl.
        $maxLevel = (int) AccountMain::query()->max('acm_acct_level');
        $accountCodeOptions = AccountMain::query()
            ->where('acm_acct_level', $maxLevel)
            ->where('acm_flag_cashbook', 'Y')
            ->whereNotIn('acm_acct_code', BankDetl::query()->pluck('acm_acct_code')->filter())
            ->orderBy('acm_acct_code')
            ->get(['acm_acct_code', 'acm_acct_desc'])
            ->map(fn (AccountMain $r) => [
                'id' => $r->acm_acct_code,
                'label' => trim(($r->acm_acct_code ?? '') . ' - ' . ($r->acm_acct_desc ?? '')),
                'desc' => $r->acm_acct_desc,
            ]);

        $ptjOptions = OrganizationUnit::query()
            ->select('oun_code', 'oun_desc')
            ->orderBy('oun_code')
            ->get()
            ->map(fn (OrganizationUnit $r) => [
                'id' => $r->oun_code,
                'label' => trim(($r->oun_code ?? '') . ' - ' . ($r->oun_desc ?? '')),
            ]);

        return $this->sendOk([
            'smartFilter' => [
                'bankName' => $bankNameOptions,
                'status' => [
                    ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
                    ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
                ],
            ],
            'popupModal' => [
                'bankName' => $bankNameOptions,
                'accountCode' => $accountCodeOptions,
                'ptj' => $ptjOptions,
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

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $accountCode = (string) $data['acm_acct_code'];
        $status = (int) $data['bnd_status'];

        // Reject if same account code already exists with the same status (legacy rule).
        $exists = BankDetl::query()
            ->where('acm_acct_code', $accountCode)
            ->where('bnd_status', $status)
            ->exists();
        if ($exists) {
            return $this->sendError(400, 'BAD_REQUEST', 'The account code you selected already exists and active.');
        }

        if (filled($data['bnd_bank_acctno']) && ! ctype_digit((string) $data['bnd_bank_acctno'])) {
            return $this->sendError(400, 'BAD_REQUEST', 'Please insert number only at Account number field to save.');
        }

        $accountDesc = AccountMain::query()->where('acm_acct_code', $accountCode)->value('acm_acct_desc');
        $extended = json_encode([
            'statusDesc' => $status === 1 ? 'ACTIVE' : 'INACTIVE',
            'acm_acct_desc' => $accountDesc,
        ]);

        $nextId = ((int) BankDetl::query()->max('bnd_bank_detl_id')) + 1;

        BankDetl::create([
            'bnd_bank_detl_id' => $nextId,
            'bnm_bank_id' => (int) $data['bnm_bank_id'],
            'bnd_bank_acctno' => (string) $data['bnd_bank_acctno'],
            'acm_acct_code' => $accountCode,
            'oun_code' => $data['oun_code'] ?? null,
            'bnd_status' => $status,
            'bnd_is_bank_main' => $data['bnd_is_bank_main'] ?? 'N',
            'bnd_currency_code' => 'MYR',
            'bnd_extended_field' => $extended,
            'createddate' => now(),
            'createdby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendCreated(['bnd_bank_detl_id' => $nextId]);
    }

    public function update(UpdateBankAccountRequest $request, int $id): JsonResponse
    {
        $row = BankDetl::query()->where('bnd_bank_detl_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Bank Account not found');
        }

        $data = $request->validated();
        $status = (int) $data['bnd_status'];

        if (filled($data['bnd_bank_acctno']) && ! ctype_digit((string) $data['bnd_bank_acctno'])) {
            return $this->sendError(400, 'BAD_REQUEST', 'Please insert number only at Account number field to save.');
        }

        $accountDesc = AccountMain::query()->where('acm_acct_code', $row->acm_acct_code)->value('acm_acct_desc');
        $extended = json_encode([
            'statusDesc' => $status === 1 ? 'ACTIVE' : 'INACTIVE',
            'acm_acct_desc' => $accountDesc,
        ]);

        $row->update([
            'bnd_bank_acctno' => (string) $data['bnd_bank_acctno'],
            'oun_code' => $data['oun_code'] ?? null,
            'bnd_status' => $status,
            'bnd_is_bank_main' => $data['bnd_is_bank_main'] ?? 'N',
            'bnd_extended_field' => $extended,
            'updateddate' => now(),
            'updatedby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendOk(['success' => true]);
    }
}
