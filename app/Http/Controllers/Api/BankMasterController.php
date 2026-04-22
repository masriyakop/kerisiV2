<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankMasterRequest;
use App\Http\Requests\UpdateBankMasterRequest;
use App\Http\Traits\ApiResponse;
use App\Models\BankMaster;
use App\Models\LookupBankMain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Cashbook > Bank Master" listing & CRUD endpoints (PAGEID 1682 / MENUID 2036).
 *
 * Source: FIMS BL `ZR_MODUL_SETUP_BANKMASTER_API`. Backed by table `bank_master`
 * on `mysql_secondary`. Lookups for Main Code dropdown come from
 * `lookup_bank_main`. Filtering uses the Eloquent query builder.
 */
class BankMasterController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'bnm_bank_code');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSort = [
            'bnm_bank_id', 'bnm_bank_code', 'bnm_bank_code_main', 'bnm_bank_desc',
            'bnm_shortname', 'bnm_address_city', 'bnm_contact_person',
        ];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'bnm_bank_code';
        }

        $query = BankMaster::query();

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                foreach ([
                    'bnm_bank_code', 'bnm_bank_code_main', 'bnm_bank_desc', 'bnm_shortname',
                    'bnm_bank_address', 'bnm_address_city', 'bnm_contact_person',
                    'bnm_branch_name', 'bnm_office_telno', 'bnm_office_faxno',
                    'bnm_url_address', 'bnm_swift_code', 'bnm_business_nature',
                ] as $col) {
                    $builder->orWhereRaw('LOWER(IFNULL(' . $col . ', "")) LIKE ?', [$like]);
                }
            });
        }

        if ($request->filled('bnm_bank_code_sm')) {
            $query->where('bnm_bank_code', $request->input('bnm_bank_code_sm'));
        }
        if ($request->filled('bnm_bank_code_main_sm')) {
            $query->where('bnm_bank_code_main', $request->input('bnm_bank_code_main_sm'));
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->values()
            ->map(fn (BankMaster $r, int $i) => [
                'index' => (($page - 1) * $limit) + $i + 1,
                'bnm_bank_id' => (int) $r->bnm_bank_id,
                'bnm_bank_code_main' => $r->bnm_bank_code_main,
                'bnm_bank_code' => $r->bnm_bank_code,
                'bnm_bank_desc' => $r->bnm_bank_desc,
                'bnm_shortname' => $r->bnm_shortname,
                'bnm_bank_address' => $r->bnm_bank_address,
                'bnm_address_city' => $r->bnm_address_city,
                'bnm_contact_person' => $r->bnm_contact_person,
                'bnm_branch_name' => $r->bnm_branch_name,
                'bnm_office_telno' => $r->bnm_office_telno,
                'bnm_office_faxno' => $r->bnm_office_faxno,
                'bnm_url_address' => $r->bnm_url_address,
                'bnm_swift_code' => $r->bnm_swift_code,
                'bnm_business_nature' => $r->bnm_business_nature,
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
        $row = BankMaster::query()->where('bnm_bank_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Bank Master not found');
        }

        return $this->sendOk([
            'bnm_bank_id' => (int) $row->bnm_bank_id,
            'bnm_bank_code' => $row->bnm_bank_code,
            'bnm_bank_code_main' => $row->bnm_bank_code_main,
            'bnm_bank_desc' => $row->bnm_bank_desc,
            'bnm_shortname' => $row->bnm_shortname,
            'bnm_bank_address' => $row->bnm_bank_address,
            'bnm_address_country' => $row->bnm_address_country,
            'bnm_address_postcode' => $row->bnm_address_postcode,
            'bnm_address_city' => $row->bnm_address_city,
            'bnm_contact_person' => $row->bnm_contact_person,
            'bnm_branch_name' => $row->bnm_branch_name,
            'bnm_office_telno' => $row->bnm_office_telno,
            'bnm_office_faxno' => $row->bnm_office_faxno,
            'bnm_url_address' => $row->bnm_url_address,
            'bnm_swift_code' => $row->bnm_swift_code,
            'bnm_business_nature' => $row->bnm_business_nature,
        ]);
    }

    public function options(): JsonResponse
    {
        $mainBankOptions = LookupBankMain::query()
            ->select('lbm_bank_code', 'lbm_bank_name')
            ->orderBy('lbm_bank_code')
            ->get()
            ->map(fn (LookupBankMain $r) => [
                'id' => $r->lbm_bank_code,
                'label' => trim(($r->lbm_bank_code ?? '') . ' - ' . ($r->lbm_bank_name ?? '')),
            ]);

        $bankCodeOptions = BankMaster::query()
            ->select('bnm_bank_code', 'bnm_bank_desc')
            ->whereNotNull('bnm_bank_code')
            ->orderBy('bnm_bank_code')
            ->get()
            ->map(fn (BankMaster $r) => [
                'id' => $r->bnm_bank_code,
                'label' => trim(($r->bnm_bank_code ?? '') . ' - ' . ($r->bnm_bank_desc ?? '')),
            ]);

        return $this->sendOk([
            'smartFilter' => [
                'bankCode' => $bankCodeOptions,
                'mainBank' => $mainBankOptions,
            ],
            'popupModal' => [
                'mainBank' => $mainBankOptions,
            ],
        ]);
    }

    public function store(StoreBankMasterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $code = trim((string) $data['bnm_bank_code']);

        $existing = BankMaster::query()->where('bnm_bank_code', $code)->first();
        if ($existing) {
            return $this->sendError(400, 'BAD_REQUEST', 'Bank Code already exist. Bank info: ' . $existing->bnm_bank_code . ' - ' . $existing->bnm_bank_desc);
        }

        $nextId = ((int) BankMaster::query()->max('bnm_bank_id')) + 1;

        BankMaster::create([
            'bnm_bank_id' => $nextId,
            'bnm_bank_code' => $code,
            'bnm_bank_code_main' => $data['bnm_bank_code_main'] ?? null,
            'bnm_bank_desc' => trim((string) $data['bnm_bank_desc']),
            'bnm_shortname' => $data['bnm_shortname'] ?? null,
            'bnm_bank_address' => trim((string) $data['bnm_bank_address']),
            'bnm_address_city' => $data['bnm_address_city'] ?? null,
            'bnm_contact_person' => trim((string) $data['bnm_contact_person']),
            'bnm_branch_name' => $data['bnm_branch_name'] ?? null,
            'bnm_office_telno' => $data['bnm_office_telno'] ?? null,
            'bnm_office_faxno' => $data['bnm_office_faxno'] ?? null,
            'bnm_url_address' => trim((string) $data['bnm_url_address']),
            'bnm_swift_code' => trim((string) $data['bnm_swift_code']),
            'bnm_business_nature' => $data['bnm_business_nature'] ?? null,
            'createddate' => now(),
            'createdby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendCreated(['bnm_bank_id' => $nextId]);
    }

    public function update(UpdateBankMasterRequest $request, int $id): JsonResponse
    {
        $row = BankMaster::query()->where('bnm_bank_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Bank Master not found');
        }

        $data = $request->validated();

        $row->update([
            'bnm_bank_code' => trim((string) $data['bnm_bank_code']),
            'bnm_bank_code_main' => $data['bnm_bank_code_main'] ?? null,
            'bnm_bank_desc' => trim((string) $data['bnm_bank_desc']),
            'bnm_shortname' => $data['bnm_shortname'] ?? null,
            'bnm_bank_address' => trim((string) $data['bnm_bank_address']),
            'bnm_address_city' => $data['bnm_address_city'] ?? null,
            'bnm_contact_person' => trim((string) $data['bnm_contact_person']),
            'bnm_branch_name' => $data['bnm_branch_name'] ?? null,
            'bnm_office_telno' => $data['bnm_office_telno'] ?? null,
            'bnm_office_faxno' => $data['bnm_office_faxno'] ?? null,
            'bnm_url_address' => trim((string) $data['bnm_url_address']),
            'bnm_swift_code' => trim((string) $data['bnm_swift_code']),
            'bnm_business_nature' => $data['bnm_business_nature'] ?? null,
            'updateddate' => now(),
            'updatedby' => $request->user()?->name ?? 'system',
        ]);

        return $this->sendOk(['success' => true]);
    }
}
