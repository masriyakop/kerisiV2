<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\BankMaster;
use App\Models\InvestmentInstitution;
use App\Models\LookupDetail;
use App\Models\Sponsor;
use App\Models\Staff;
use App\Models\StaffAccount;
use App\Models\StudAccount;
use App\Models\Student;
use App\Models\VendCustomerSupplier;
use App\Models\VendSupplierAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Account Payable > Account Bank By Payee" listing endpoint
 * (PAGEID 2262 / MENUID 2751).
 *
 * Source: FIMS BL `AS_BL_AP_ACCOUNTBANKBPAYEE` — a single screen that swaps
 * data source based on the "Payee Type" top-filter:
 *   B   → staff + staff_account  (salary bank = 'Y')
 *   A   → student + stud_account
 *   CDG → vend_customer_supplier + vend_supplier_account  (Creditor/Debtor/Others)
 *   E   → sponsor
 *   F   → investment_institution
 * Read-only; no CRUD in the legacy page for this listing.
 */
class AccountBankByPayeeController extends Controller
{
    use ApiResponse;

    private const GENERIC = ['B', 'A', 'CDG'];

    public function index(Request $request): JsonResponse
    {
        $type = strtoupper((string) $request->input('payee_type', ''));
        if ($type === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'payee_type is required');
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));

        return match ($type) {
            'B' => $this->listStaff($request, $q, $page, $limit),
            'A' => $this->listStudent($request, $q, $page, $limit),
            'CDG' => $this->listVendor($request, $q, $page, $limit),
            'E' => $this->listSponsor($request, $q, $page, $limit),
            'F' => $this->listInvestment($request, $q, $page, $limit),
            default => $this->sendError(400, 'BAD_REQUEST', 'Unsupported payee_type: ' . $type),
        };
    }

    public function options(Request $request): JsonResponse
    {
        $payeeType = [
            ['id' => 'A', 'label' => 'A - STUDENT'],
            ['id' => 'B', 'label' => 'B - STAFF'],
            ['id' => 'CDG', 'label' => 'C/D/G - VENDOR'],
            ['id' => 'E', 'label' => 'E - SPONSOR'],
            ['id' => 'F', 'label' => 'F - INVESTMENT INSTITUTION'],
        ];

        $type = strtoupper((string) $request->input('payee_type', ''));
        $smart = [];

        if ($type === 'B') {
            $smart = [
                'name' => StaffAccount::query()
                    ->join('staff', 'staff.stf_staff_id', '=', 'staff_account.stf_staff_id')
                    ->where('staff_account.sta_status', 1)
                    ->where('staff_account.sta_salary_bank', 'Y')
                    ->selectRaw("staff.stf_staff_id AS id, CONCAT_WS(' - ', staff.stf_staff_id, staff.stf_staff_name) AS label")
                    ->distinct()
                    ->orderBy('staff.stf_staff_id')
                    ->get()
                    ->map(fn ($r) => ['id' => $r->id, 'label' => $r->label]),
                'status' => $this->lookupOptions('STAFFSTATUS'),
                'accountName' => $this->bankOptions(),
            ];
        } elseif ($type === 'A') {
            $smart = [
                'name' => StudAccount::query()
                    ->join('student', 'student.std_student_id', '=', 'stud_account.std_student_id')
                    ->where('stud_account.sac_status', 1)
                    ->selectRaw("student.std_student_id AS id, CONCAT_WS(' - ', student.std_student_id, student.std_student_name) AS label")
                    ->distinct()
                    ->orderBy('student.std_student_id')
                    ->get()
                    ->map(fn ($r) => ['id' => $r->id, 'label' => $r->label]),
                'status' => $this->lookupOptions('STUDENT_STATUS'),
                'accountName' => $this->bankOptions(),
            ];
        } elseif ($type === 'CDG') {
            $smart = [
                'name' => VendSupplierAccount::query()
                    ->join('vend_customer_supplier', 'vend_customer_supplier.vcs_vendor_code', '=', 'vend_supplier_account.vcs_vendor_code')
                    ->where('vend_supplier_account.vsa_status', 1)
                    ->selectRaw("vend_supplier_account.vcs_vendor_code AS id, CONCAT_WS(' - ', vend_supplier_account.vcs_vendor_code, vend_customer_supplier.vcs_vendor_name) AS label")
                    ->distinct()
                    ->orderBy('vend_supplier_account.vcs_vendor_code')
                    ->get()
                    ->map(fn ($r) => ['id' => $r->id, 'label' => $r->label]),
                'status' => [
                    ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
                    ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
                ],
                'accountName' => $this->bankOptions(),
            ];
        } elseif ($type === 'E') {
            $smart = [
                'sponsorCode' => Sponsor::query()
                    ->where('spn_status_cd', 1)
                    ->orderBy('spn_sponsor_code')
                    ->get(['spn_sponsor_code', 'spn_sponsor_name'])
                    ->map(fn ($r) => [
                        'id' => $r->spn_sponsor_code,
                        'label' => trim(($r->spn_sponsor_code ?? '') . ' - ' . ($r->spn_sponsor_name ?? '')),
                    ]),
                'sponsorName' => Sponsor::query()
                    ->where('spn_status_cd', 1)
                    ->orderBy('spn_sponsor_name')
                    ->get(['spn_sponsor_name'])
                    ->map(fn ($r) => ['id' => $r->spn_sponsor_name, 'label' => $r->spn_sponsor_name]),
                'bankName' => $this->bankOptions(),
            ];
        } elseif ($type === 'F') {
            $smart = [
                'instCode' => InvestmentInstitution::query()
                    ->where('itt_status', 1)
                    ->orderBy('iit_inst_code')
                    ->get(['iit_inst_code', 'iit_inst_name'])
                    ->map(fn ($r) => [
                        'id' => $r->iit_inst_code,
                        'label' => trim(($r->iit_inst_code ?? '') . ' - ' . ($r->iit_inst_name ?? '')),
                    ]),
                'bankCode' => $this->bankOptions(),
            ];
        }

        return $this->sendOk([
            'payeeType' => $payeeType,
            'smartFilter' => $smart,
        ]);
    }

    private function listStaff(Request $request, string $q, int $page, int $limit): JsonResponse
    {
        $query = StaffAccount::query()
            ->from('staff_account as sta')
            ->join('staff as s', 'sta.stf_staff_id', '=', 's.stf_staff_id')
            ->where('sta.sta_status', 1)
            ->where('sta.sta_salary_bank', 'Y');

        if ($request->filled('smlist_name')) {
            $query->where('sta.stf_staff_id', (string) $request->input('smlist_name'));
        }
        if ($request->filled('smlist_status_B')) {
            $query->where('s.stf_staff_status', (string) $request->input('smlist_status_B'));
        }
        if ($request->filled('smlist_acct_name')) {
            $query->where('sta.sta_acct_code', (string) $request->input('smlist_acct_name'));
        }
        if ($request->filled('acct_no')) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->input('acct_no')) . '%';
            $query->where('sta.sta_acct_no', 'like', $like);
        }

        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q, 'UTF-8')) . '%';
            $query->where(function (Builder $b) use ($like) {
                $b->orWhereRaw("LOWER(IFNULL(sta.stf_staff_id, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(s.stf_staff_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(sta.sta_acct_code, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(sta.sta_acct_no, '')) LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->select([
                'sta.stf_staff_id',
                's.stf_staff_name',
                's.stf_staff_status',
                'sta.sta_acct_code',
                'sta.sta_acct_no',
            ])
            ->orderBy('sta.stf_staff_id')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $bankMap = $this->bankMap();
        $statusMap = $this->lookupMap('STAFFSTATUS');

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'name' => trim(($r->stf_staff_id ?? '') . ' - ' . ($r->stf_staff_name ?? '')),
            'status' => $statusMap[$r->stf_staff_status] ?? $r->stf_staff_status,
            'acct_code' => $r->sta_acct_code,
            'acct_name' => $bankMap[$r->sta_acct_code] ?? $r->sta_acct_code,
            'acct_no' => $r->sta_acct_no,
        ]);

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    private function listStudent(Request $request, string $q, int $page, int $limit): JsonResponse
    {
        $query = StudAccount::query()
            ->from('stud_account as stud')
            ->join('student as s', 'stud.std_student_id', '=', 's.std_student_id')
            ->where('stud.sac_status', 1);

        if ($request->filled('smlist_name')) {
            $query->where('stud.std_student_id', (string) $request->input('smlist_name'));
        }
        if ($request->filled('smlist_status_A')) {
            $query->where('s.std_status', (string) $request->input('smlist_status_A'));
        }
        if ($request->filled('smlist_acct_name')) {
            $query->where('stud.sac_bank_code', (string) $request->input('smlist_acct_name'));
        }
        if ($request->filled('acct_no')) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->input('acct_no')) . '%';
            $query->where('stud.sac_bank_acc_no', 'like', $like);
        }

        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q, 'UTF-8')) . '%';
            $query->where(function (Builder $b) use ($like) {
                $b->orWhereRaw("LOWER(IFNULL(stud.std_student_id, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(s.std_student_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(stud.sac_bank_code, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(stud.sac_bank_acc_no, '')) LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->select([
                'stud.std_student_id',
                's.std_student_name',
                's.std_status',
                'stud.sac_bank_code',
                'stud.sac_bank_acc_no',
            ])
            ->orderBy('stud.std_student_id')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $bankMap = $this->bankMap();
        $statusMap = $this->lookupMap('STUDENT_STATUS');

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'name' => trim(($r->std_student_id ?? '') . ' - ' . ($r->std_student_name ?? '')),
            'status' => $statusMap[$r->std_status] ?? $r->std_status,
            'acct_code' => $r->sac_bank_code,
            'acct_name' => $bankMap[$r->sac_bank_code] ?? $r->sac_bank_code,
            'acct_no' => $r->sac_bank_acc_no,
        ]);

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    private function listVendor(Request $request, string $q, int $page, int $limit): JsonResponse
    {
        $query = VendSupplierAccount::query()
            ->from('vend_supplier_account as vend')
            ->join('vend_customer_supplier as vcs', 'vend.vcs_vendor_code', '=', 'vcs.vcs_vendor_code')
            ->where('vend.vsa_status', 1);

        if ($request->filled('smlist_name')) {
            $query->where('vend.vcs_vendor_code', (string) $request->input('smlist_name'));
        }
        if ($request->filled('smlist_status_CDG')) {
            $want = strtoupper((string) $request->input('smlist_status_CDG')) === 'ACTIVE' ? '1' : '0';
            $query->where('vcs.vcs_vendor_status', $want);
        }
        if ($request->filled('smlist_acct_name')) {
            $query->where('vend.vsa_vendor_bank', (string) $request->input('smlist_acct_name'));
        }
        if ($request->filled('acct_no')) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->input('acct_no')) . '%';
            $query->where('vend.vsa_bank_accno', 'like', $like);
        }

        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q, 'UTF-8')) . '%';
            $query->where(function (Builder $b) use ($like) {
                $b->orWhereRaw("LOWER(IFNULL(vend.vcs_vendor_code, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(vcs.vcs_vendor_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(vend.vsa_vendor_bank, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(vend.vsa_bank_accno, '')) LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->select([
                'vend.vcs_vendor_code',
                'vcs.vcs_vendor_name',
                'vcs.vcs_vendor_status',
                'vend.vsa_vendor_bank',
                'vend.vsa_bank_accno',
            ])
            ->orderBy('vend.vcs_vendor_code')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $bankMap = $this->bankMap();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'name' => trim(($r->vcs_vendor_code ?? '') . ' - ' . ($r->vcs_vendor_name ?? '')),
            'status' => (string) $r->vcs_vendor_status === '1' ? 'ACTIVE' : 'INACTIVE',
            'acct_code' => $r->vsa_vendor_bank,
            'acct_name' => $bankMap[$r->vsa_vendor_bank] ?? $r->vsa_vendor_bank,
            'acct_no' => $r->vsa_bank_accno,
        ]);

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    private function listSponsor(Request $request, string $q, int $page, int $limit): JsonResponse
    {
        $query = Sponsor::query()->where('spn_status_cd', 1);

        foreach (['spn_sponsor_code', 'spn_sponsor_name', 'spn_bank_name_cd'] as $col) {
            if ($request->filled($col)) {
                $query->where($col, (string) $request->input($col));
            }
        }
        if ($request->filled('spn_bank_acc_no')) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->input('spn_bank_acc_no')) . '%';
            $query->where('spn_bank_acc_no', 'like', $like);
        }
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q, 'UTF-8')) . '%';
            $query->where(function (Builder $b) use ($like) {
                foreach ([
                    'spn_sponsor_code', 'spn_sponsor_name', 'spn_bank_acc_no', 'spn_address1',
                    'spn_address2', 'spn_city', 'spn_postcode', 'spn_state', 'spn_contact_person',
                    'spn_contact_no',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL($col, '')) LIKE ?", [$like]);
                }
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy('spn_sponsor_code')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $bankMap = $this->bankMap();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'spn_sponsor_code' => $r->spn_sponsor_code,
            'spn_sponsor_name' => $r->spn_sponsor_name,
            'spn_bank_name_cd' => $bankMap[$r->spn_bank_name_cd] ?? $r->spn_bank_name_cd,
            'spn_bank_acc_no' => $r->spn_bank_acc_no,
            'spn_address1' => $r->spn_address1,
            'spn_address2' => $r->spn_address2,
            'spn_city' => $r->spn_city,
            'spn_postcode' => $r->spn_postcode,
            'spn_state' => $r->spn_state,
            'spn_contact_person' => $r->spn_contact_person,
            'spn_contact_no' => $r->spn_contact_no,
        ]);

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    private function listInvestment(Request $request, string $q, int $page, int $limit): JsonResponse
    {
        $query = InvestmentInstitution::query()
            ->from('investment_institution as ii')
            ->where('ii.itt_status', 1);

        if ($request->filled('iit_inst_code')) {
            $query->where('ii.iit_inst_code', (string) $request->input('iit_inst_code'));
        }
        if ($request->filled('bnm_bank_code')) {
            $query->where('ii.bnm_bank_code', (string) $request->input('bnm_bank_code'));
        }

        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q, 'UTF-8')) . '%';
            $query->where(function (Builder $b) use ($like) {
                foreach ([
                    'ii.iit_inst_code', 'ii.iit_inst_name', 'ii.bnm_bank_code', 'ii.bnm_shortname',
                    'ii.iit_bank_branch', 'ii.iit_address1', 'ii.iit_address2', 'ii.iit_address3',
                    'ii.iit_pcode', 'ii.iit_city', 'ii.iit_state', 'ii.iit_country',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL($col, '')) LIKE ?", [$like]);
                }
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy('ii.iit_inst_code')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $bankMap = $this->bankMap();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'iit_inst_code' => $r->iit_inst_code,
            'iit_inst_name' => $r->iit_inst_name,
            'bnm_bank_code' => $r->bnm_bank_code,
            'bnm_shortname' => $r->bnm_shortname,
            'bank_name' => $bankMap[$r->bnm_bank_code] ?? null,
            'iit_address1' => $r->iit_address1,
            'iit_address2' => $r->iit_address2,
            'iit_address3' => $r->iit_address3,
            'iit_city' => $r->iit_city,
            'iit_pcode' => $r->iit_pcode,
            'iit_state' => $r->iit_state,
            'iit_country' => $r->iit_country,
        ]);

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    /**
     * @return array{page:int, limit:int, total:int, totalPages:int}
     */
    private function meta(int $page, int $limit, int $total): array
    {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ];
    }

    /**
     * @return array<int, array{id:string, label:string}>
     */
    private function lookupOptions(string $codeName): array
    {
        return LookupDetail::query()
            ->where('lma_code_name', $codeName)
            ->orderBy('lde_sorting')
            ->orderBy('lde_description')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => ['id' => (string) $r->lde_value, 'label' => (string) $r->lde_description])
            ->toArray();
    }

    /**
     * @return array<int, array{id:string, label:string}>
     */
    private function bankOptions(): array
    {
        return BankMaster::query()
            ->orderBy('bnm_bank_code')
            ->get(['bnm_bank_code', 'bnm_bank_desc'])
            ->map(fn ($r) => [
                'id' => (string) $r->bnm_bank_code,
                'label' => trim(($r->bnm_bank_code ?? '') . ' - ' . ($r->bnm_bank_desc ?? '')),
            ])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    private function bankMap(): array
    {
        return BankMaster::query()->pluck('bnm_bank_desc', 'bnm_bank_code')->toArray();
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
}
