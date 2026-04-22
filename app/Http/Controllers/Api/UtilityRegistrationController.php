<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUtilityRegistrationRequest;
use App\Http\Requests\UpdateUtilityRegistrationRequest;
use App\Http\Traits\ApiResponse;
use App\Models\VendCategory;
use App\Models\VendCustomerSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Account Payable > Utility Registration" listing + CRUD endpoints
 * (PAGEID 2881 / MENUID 3466).
 *
 * Source: FIMS BL `SNA_API_AP_UTILITYREGISTRATION` — reads/writes
 * `vend_customer_supplier` (with paired `vend_category` rows where
 * `vc_category_code = 'U'`). Legacy detail deep-link was menuID 3467, which is
 * not in the migrated menu set; this module keeps add/edit inline via a popup
 * modal backed by this controller's store/show/update.
 */
class UtilityRegistrationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'vcs_vendor_code');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSort = ['vcs_vendor_code', 'vcs_vendor_name', 'vcs_biller_code', 'vcs_vendor_status'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'vcs_vendor_code';
        }

        $query = $this->baseQuery();

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function (Builder $b) use ($like) {
                foreach (['vcs_vendor_code', 'vcs_vendor_name', 'vcs_biller_code'] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL(VCS.$col, '')) LIKE ?", [$like]);
                }
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->select([
                'VCS.vcs_id',
                'VCS.vcs_vendor_code',
                'VCS.vcs_vendor_name',
                'VCS.vcs_biller_code',
                'VCS.vcs_vendor_status',
            ])
            ->orderBy("VCS.$sortBy", $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($row, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'vcs_id' => $row->vcs_id,
            'vcs_vendor_code' => $row->vcs_vendor_code,
            'vcs_vendor_name' => $row->vcs_vendor_name,
            'vcs_biller_code' => $row->vcs_biller_code,
            'vcs_vendor_status' => (string) $row->vcs_vendor_status === '1' ? 'ACTIVE' : 'INACTIVE',
            'vcs_vendor_status_value' => (int) $row->vcs_vendor_status,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $row = $this->baseQuery()->where('VCS.vcs_id', $id)->first([
            'VCS.vcs_id',
            'VCS.vcs_vendor_code',
            'VCS.vcs_vendor_name',
            'VCS.vcs_biller_code',
            'VCS.vcs_vendor_status',
        ]);

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Utility Registration not found');
        }

        return $this->sendOk([
            'vcs_id' => $row->vcs_id,
            'vcs_vendor_code' => $row->vcs_vendor_code,
            'vcs_vendor_name' => $row->vcs_vendor_name,
            'vcs_biller_code' => $row->vcs_biller_code,
            'vcs_vendor_status' => (int) $row->vcs_vendor_status,
        ]);
    }

    public function store(StoreUtilityRegistrationRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Duplicate check on vendor_name + biller_code (active rows only) — mirrors
        // legacy `process_register_utility` pre-insert guard.
        $dupe = $this->baseQuery()
            ->where('VCS.vcs_vendor_name', $data['vcs_vendor_name'])
            ->where('VCS.vcs_biller_code', $data['vcs_biller_code'])
            ->where('VCS.vcs_vendor_status', '1')
            ->first(['VCS.vcs_vendor_code', 'VCS.vcs_vendor_name']);
        if ($dupe) {
            return $this->sendError(400, 'BAD_REQUEST', 'This Payee Name and Biller Code already exists.');
        }

        // Legacy uses a helper (`getRefNo`) to build `OTHER_CREDITOR/<fund>/<yyyymm>/nnn`
        // vendor codes. That helper was not ported; we generate a deterministic
        // fallback code here (UTIL + YYYYMM + 4-digit sequence based on current max).
        $yearMonth = now()->format('Ym');
        $prefix = 'UTIL' . $yearMonth;
        $lastSeq = VendCustomerSupplier::query()
            ->where('vcs_vendor_code', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(vcs_vendor_code, ?) AS UNSIGNED)) AS seq', [strlen($prefix) + 1])
            ->value('seq');
        $seq = str_pad((string) (((int) $lastSeq) + 1), 4, '0', STR_PAD_LEFT);
        $vendorCode = $prefix . $seq;

        $username = $request->user()?->name ?? 'system';
        $nextVcsId = (string) ((int) (VendCustomerSupplier::max('vcs_id') ?? 0) + 1);
        $nextVcId = (string) ((int) (VendCategory::max('vc_id') ?? 0) + 1);
        $extendedField = json_encode(['SOURCE' => 'UTILITY']);

        DB::connection('mysql_secondary')->transaction(function () use (
            $nextVcsId, $vendorCode, $data, $extendedField, $username, $nextVcId
        ) {
            VendCustomerSupplier::create([
                'vcs_id' => $nextVcsId,
                'vcs_vendor_code' => $vendorCode,
                'vcs_vendor_name' => trim($data['vcs_vendor_name']),
                'vcs_biller_code' => trim($data['vcs_biller_code']),
                'vcs_vendor_status' => (string) $data['vcs_vendor_status'],
                'vcs_iscreditor' => 'Y',
                'vcs_isdebtor' => 'N',
                'vcs_extended_field' => $extendedField,
                'createdby' => $username,
                'createddate' => now(),
            ]);

            VendCategory::create([
                'vc_id' => $nextVcId,
                'vcs_vendor_code' => $vendorCode,
                'vc_category_code' => 'U',
                'vc_extended_field' => $extendedField,
                'createdby' => $username,
                'createddate' => now(),
            ]);
        });

        return $this->sendCreated([
            'vcs_id' => $nextVcsId,
            'vcs_vendor_code' => $vendorCode,
        ]);
    }

    public function update(UpdateUtilityRegistrationRequest $request, string $id): JsonResponse
    {
        $row = VendCustomerSupplier::query()->where('vcs_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Utility Registration not found');
        }

        $data = $request->validated();

        $row->update([
            'vcs_vendor_name' => trim($data['vcs_vendor_name']),
            'vcs_biller_code' => trim($data['vcs_biller_code']),
            'vcs_vendor_status' => (string) $data['vcs_vendor_status'],
            'updatedby' => $request->user()?->name ?? 'system',
            'updateddate' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }

    private function baseQuery(): Builder
    {
        return VendCustomerSupplier::query()
            ->from('vend_customer_supplier as VCS')
            ->leftJoin('vend_category as VC', 'VCS.vcs_vendor_code', '=', 'VC.vcs_vendor_code')
            ->where('VC.vc_category_code', 'U')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(VCS.vcs_extended_field, '$.SOURCE')) = ?", ['UTILITY']);
    }
}
