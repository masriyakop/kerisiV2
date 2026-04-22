<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\BankMaster;
use App\Models\LookupDetail;
use App\Models\VendCustomerSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Account Payable > Payee Registration (Others)" listing endpoint
 * (PAGEID 1403 / MENUID 1711).
 *
 * Source: FIMS BL `NF_BL_AP_PAY_REGISTRATION` — lists
 * `vend_customer_supplier` rows joined with `vend_category` where
 * `vc_category_code = 'G'`. Legacy "Edit" deep-linked to menuID 1713 which is
 * NOT in the migrated menu set, so this screen is read-only with smart filters
 * (payee code, state, status, year register).
 */
class PayeeRegistrationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'vcs_vendor_code');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSort = ['vcs_vendor_code', 'vcs_vendor_name', 'vcs_state', 'vcs_vendor_status'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'vcs_vendor_code';
        }

        $query = $this->baseQuery();

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function (Builder $b) use ($like) {
                foreach ([
                    'vcs_vendor_code', 'vcs_vendor_name', 'vcs_addr1', 'vcs_addr2', 'vcs_addr3',
                    'vcs_town', 'vcs_state', 'vcs_vendor_bank', 'vcs_bank_accno', 'vcs_biller_code',
                    'vcs_tel_no', 'vcs_email_address', 'vcs_contact_person', 'vcs_ic_no',
                    'vcs_registration_no',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL(VCS.$col, '')) LIKE ?", [$like]);
                }
            });
        }

        if ($request->filled('vcs_vendor_code')) {
            $query->where('VCS.vcs_vendor_code', (string) $request->input('vcs_vendor_code'));
        }
        if ($request->filled('state')) {
            $query->where('VCS.vcs_state', (string) $request->input('state'));
        }
        if ($request->filled('vcs_vendor_status')) {
            $want = strtoupper((string) $request->input('vcs_vendor_status')) === 'N' ? '0' : '1';
            $query->where('VCS.vcs_vendor_status', $want);
        }
        if ($request->filled('year_register')) {
            $query->whereYear('VCS.createddate', (int) $request->input('year_register'));
        }

        $total = (clone $query)->count();

        $rows = $query
            ->select([
                'VCS.vcs_id',
                'VCS.vcs_vendor_code',
                'VCS.vcs_vendor_name',
                'VCS.vcs_addr1',
                'VCS.vcs_addr2',
                'VCS.vcs_addr3',
                'VCS.vcs_town',
                'VCS.vcs_state',
                'VCS.vcs_vendor_bank',
                'VCS.vcs_bank_accno',
                'VCS.vcs_biller_code',
                'VCS.vcs_tel_no',
                'VCS.vcs_email_address',
                'VCS.vcs_contact_person',
                'VCS.vcs_ic_no',
                'VCS.vcs_registration_no',
                'VCS.vcs_vendor_status',
            ])
            ->orderBy("VCS.$sortBy", $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $stateMap = $this->lookupMap('STATE');
        $bankMap = $this->bankMap();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit, $stateMap, $bankMap) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'vcs_id' => $row->vcs_id,
                'vcs_vendor_code' => $row->vcs_vendor_code,
                'vcs_vendor_name' => $row->vcs_vendor_name,
                'vcs_addr1' => $row->vcs_addr1,
                'vcs_addr2' => $row->vcs_addr2,
                'vcs_addr3' => $row->vcs_addr3,
                'vcs_town' => $row->vcs_town,
                'state' => $stateMap[$row->vcs_state] ?? $row->vcs_state,
                'vcs_state' => $row->vcs_state,
                'vendor_bank' => $bankMap[$row->vcs_vendor_bank] ?? $row->vcs_vendor_bank,
                'vcs_vendor_bank' => $row->vcs_vendor_bank,
                'vcs_bank_accno' => $row->vcs_bank_accno,
                'vcs_biller_code' => $row->vcs_biller_code,
                'vcs_tel_no' => $row->vcs_tel_no,
                'vcs_email_address' => $row->vcs_email_address,
                'vcs_contact_person' => $row->vcs_contact_person,
                'vcs_ic_no' => $row->vcs_ic_no,
                'vcs_registration_no' => $row->vcs_registration_no,
                'vcs_vendor_status' => (string) $row->vcs_vendor_status === '1' ? 'ACTIVE' : 'INACTIVE',
                'vcs_vendor_status_value' => (int) $row->vcs_vendor_status,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function options(): JsonResponse
    {
        $payeeCodes = $this->baseQuery()
            ->select('VCS.vcs_vendor_code', 'VCS.vcs_vendor_name')
            ->orderBy('VCS.vcs_vendor_code')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->vcs_vendor_code,
                'label' => trim(($r->vcs_vendor_code ?? '') . ' - ' . ($r->vcs_vendor_name ?? '')),
            ]);

        $states = LookupDetail::query()
            ->where('lma_code_name', 'STATE')
            ->orderBy('lde_sorting')
            ->orderBy('lde_description')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->lde_value,
                'label' => $r->lde_description,
            ]);

        $years = VendCustomerSupplier::query()
            ->selectRaw('DISTINCT YEAR(createddate) AS yr')
            ->whereNotNull('createddate')
            ->orderByDesc('yr')
            ->pluck('yr')
            ->filter()
            ->values()
            ->map(fn ($y) => [
                'id' => (int) $y,
                'label' => (string) $y,
            ]);

        return $this->sendOk([
            'smartFilter' => [
                'payeeCode' => $payeeCodes,
                'state' => $states,
                'status' => [
                    ['id' => 'Y', 'label' => 'ACTIVE'],
                    ['id' => 'N', 'label' => 'INACTIVE'],
                ],
                'yearRegister' => $years,
            ],
        ]);
    }

    private function baseQuery(): Builder
    {
        return VendCustomerSupplier::query()
            ->from('vend_customer_supplier as VCS')
            ->leftJoin('vend_category as VC', 'VCS.vcs_vendor_code', '=', 'VC.vcs_vendor_code')
            ->where('VC.vc_category_code', 'G');
    }

    /**
     * @return array<string, string>
     */
    private function lookupMap(string $codeName): array
    {
        return LookupDetail::query()
            ->where('lma_code_name', $codeName)
            ->pluck('lde_description', 'lde_value')
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    private function bankMap(): array
    {
        return BankMaster::query()
            ->pluck('bnm_bank_desc', 'bnm_bank_code')
            ->toArray();
    }
}
