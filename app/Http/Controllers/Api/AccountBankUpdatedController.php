<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessAccountBankUpdatedRequest;
use App\Http\Traits\ApiResponse;
use App\Models\BillsDetail;
use App\Models\BillsMaster;
use App\Models\Sponsor;
use App\Models\Staff;
use App\Models\StaffAccount;
use App\Models\StudAccount;
use App\Models\Student;
use App\Models\VendSupplierAccount;
use App\Models\VoucherDetail;
use App\Models\VoucherMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Account Payable > Account Bank Updated" endpoints
 * (PAGEID 1719 / MENUID 2078).
 *
 * Source: FIMS BL `SNA_API_AP_ACCOUNTBANKUPDATED` + onload JS
 * `SNA_JS_AP_ACCOUNTBANKUPDATED`. The legacy page compares the bank account
 * stored on each Bill / Voucher line (`bills_details`, `voucher_details`)
 * against the payee's current active bank account in the payee masters
 * (stud_account / staff_account / vend_supplier_account / sponsor), and lets
 * a user select rows + apply the payee's current bank via a bulk UPDATE.
 *
 *   Payee type codes (per BL):
 *     A = Student    (stud_account)
 *     B = Staff      (staff_account, salary bank = 'Y')
 *     C = Creditor   (vend_supplier_account)
 *     D = Debtor     (vend_supplier_account)
 *     E = Sponsor    (sponsor)
 *     G = Others     (vend_supplier_account, other payee)
 */
class AccountBankUpdatedController extends Controller
{
    use ApiResponse;

    private const PAYEE_TYPES = ['A', 'B', 'C', 'D', 'E', 'G'];

    public function options(Request $request): JsonResponse
    {
        $payeeType = [
            ['id' => 'A', 'label' => 'A - STUDENT'],
            ['id' => 'B', 'label' => 'B - STAFF'],
            ['id' => 'C', 'label' => 'C - CREDITOR'],
            ['id' => 'D', 'label' => 'D - DEBTOR'],
            ['id' => 'E', 'label' => 'E - SPONSOR'],
            ['id' => 'G', 'label' => 'G - OTHER PAYEE'],
        ];

        $type = strtoupper((string) $request->input('payee_type', ''));
        $ids = [];

        if ($type !== '' && in_array($type, self::PAYEE_TYPES, true)) {
            $ids = $this->idOptions($type);
        }

        return $this->sendOk([
            'payeeType' => $payeeType,
            'ids' => $ids,
        ]);
    }

    public function listBills(Request $request): JsonResponse
    {
        $type = strtoupper((string) $request->input('payee_type', ''));
        $id = trim((string) $request->input('payee_id', ''));
        $q = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));

        if (! in_array($type, self::PAYEE_TYPES, true)) {
            return $this->sendError(400, 'BAD_REQUEST', 'payee_type is required');
        }
        if ($id === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'payee_id is required');
        }

        $newBank = $this->payeeBank($type, $id);

        $query = BillsMaster::query()
            ->from('bills_master as BIM')
            ->join('bills_details as BID', 'BIM.bim_bills_id', '=', 'BID.bim_bills_id')
            ->leftJoin('bank_master as BM_OLD', 'BM_OLD.bnm_bank_code', '=', 'BID.vsa_vendor_bank')
            ->where('BID.bid_trans_type', 'DT')
            ->where('BID.bid_payto_id', $id)
            ->where(function (Builder $b) use ($newBank) {
                $b->whereNull('BID.vsa_bank_accno');
                if ($newBank['accno'] !== null) {
                    $b->orWhere('BID.vsa_bank_accno', '!=', $newBank['accno']);
                }
            });

        // 'G' legacy BL comments out bid_payto_type filter — mirror that.
        if ($type !== 'G') {
            $query->where('BID.bid_payto_type', $type);
        }

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function (Builder $b) use ($like) {
                foreach ([
                    'BIM.bim_bills_no', 'BIM.bim_bills_desc',
                    'BID.bid_payto_id', 'BID.bid_payto_name',
                    'BID.vsa_vendor_bank', 'BID.vsa_bank_accno',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL($col, '')) LIKE ?", [$like]);
                }
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->select([
                'BIM.bim_bills_id',
                'BIM.bim_bills_no',
                'BIM.bim_bills_desc',
                'BID.bid_payto_id',
                'BID.bid_payto_name',
                'BID.bid_payto_type',
                'BID.vsa_vendor_bank',
                'BID.vsa_bank_accno',
                'BM_OLD.bnm_bank_desc as old_bank_desc',
            ])
            ->orderBy('BIM.bim_bills_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $newBankDesc = $this->bankDescFor($newBank['code']);

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'billId' => (string) $r->bim_bills_id,
            'billNo' => $r->bim_bills_no,
            'billDesc' => $r->bim_bills_desc,
            'payeeType' => $r->bid_payto_type,
            'payeeId' => $r->bid_payto_id,
            'payeeName' => $r->bid_payto_name,
            'currentBank' => $this->combineBank($r->vsa_vendor_bank, $r->old_bank_desc),
            'currentAccNo' => $r->vsa_bank_accno,
            'newBank' => $this->combineBank($newBank['code'], $newBankDesc),
            'newAccNo' => $newBank['accno'],
        ]);

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function listVouchers(Request $request): JsonResponse
    {
        $type = strtoupper((string) $request->input('payee_type', ''));
        $id = trim((string) $request->input('payee_id', ''));
        $q = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));

        if (! in_array($type, self::PAYEE_TYPES, true)) {
            return $this->sendError(400, 'BAD_REQUEST', 'payee_type is required');
        }
        if ($id === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'payee_id is required');
        }

        $newBank = $this->payeeBank($type, $id);

        $query = VoucherMaster::query()
            ->from('voucher_master as VM')
            ->join('voucher_details as VD', 'VM.vma_voucher_id', '=', 'VD.vma_voucher_id')
            ->leftJoin('bank_master as BM_OLD', 'BM_OLD.bnm_bank_code', '=', 'VD.vde_bank_name')
            ->where('VD.vde_payto_id', $id)
            ->where(function (Builder $b) use ($newBank) {
                $b->whereNull('VD.vde_bank_acctno');
                if ($newBank['accno'] !== null) {
                    $b->orWhere('VD.vde_bank_acctno', '!=', $newBank['accno']);
                }
            });

        // 'G' legacy BL comments out vde_payto_type filter — mirror that.
        if ($type !== 'G') {
            $query->where('VD.vde_payto_type', $type);
        }

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function (Builder $b) use ($like) {
                foreach ([
                    'VM.vma_voucher_no', 'VM.vma_vch_description',
                    'VD.vde_payto_id', 'VD.vde_payto_name',
                    'VD.vde_bank_name', 'VD.vde_bank_acctno',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL($col, '')) LIKE ?", [$like]);
                }
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->select([
                'VM.vma_voucher_id',
                'VM.vma_voucher_no',
                'VM.vma_vch_description',
                'VD.vde_payto_id',
                'VD.vde_payto_name',
                'VD.vde_payto_type',
                'VD.vde_bank_name',
                'VD.vde_bank_acctno',
                'BM_OLD.bnm_bank_desc as old_bank_desc',
            ])
            ->orderBy('VM.vma_voucher_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $newBankDesc = $this->bankDescFor($newBank['code']);

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'voucherId' => (string) $r->vma_voucher_id,
            'voucherNo' => $r->vma_voucher_no,
            'voucherDesc' => $r->vma_vch_description,
            'payeeType' => $r->vde_payto_type,
            'payeeId' => $r->vde_payto_id,
            'payeeName' => $r->vde_payto_name,
            'currentBank' => $this->combineBank($r->vde_bank_name, $r->old_bank_desc),
            'currentAccNo' => $r->vde_bank_acctno,
            'newBank' => $this->combineBank($newBank['code'], $newBankDesc),
            'newAccNo' => $newBank['accno'],
        ]);

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function processBills(ProcessAccountBankUpdatedRequest $request): JsonResponse
    {
        $type = strtoupper((string) $request->validated()['payee_type']);
        $ids = array_values(array_unique($request->validated()['ids']));
        $username = $request->user()?->name ?? 'system';

        $affected = 0;
        DB::connection('mysql_secondary')->transaction(function () use ($ids, $type, $username, &$affected) {
            foreach ($ids as $billId) {
                $row = BillsDetail::query()
                    ->where('bim_bills_id', $billId)
                    ->first(['bim_bills_id', 'bid_payto_id']);
                if (! $row) {
                    continue;
                }
                $bank = $this->payeeBank($type, (string) $row->bid_payto_id);
                $affected += BillsDetail::query()
                    ->where('bim_bills_id', $billId)
                    ->update([
                        'vsa_vendor_bank' => $bank['code'],
                        'vsa_bank_accno' => $bank['accno'],
                        'updatedby' => $username,
                        'updateddate' => now(),
                    ]);
            }
        });

        return $this->sendOk([
            'success' => true,
            'affected' => $affected,
            'message' => 'Bank Account Number Successfully updated.',
        ]);
    }

    public function processVouchers(ProcessAccountBankUpdatedRequest $request): JsonResponse
    {
        $type = strtoupper((string) $request->validated()['payee_type']);
        $ids = array_values(array_unique($request->validated()['ids']));
        $username = $request->user()?->name ?? 'system';

        $affected = 0;
        DB::connection('mysql_secondary')->transaction(function () use ($ids, $type, $username, &$affected) {
            foreach ($ids as $voucherId) {
                $row = VoucherDetail::query()
                    ->where('vma_voucher_id', $voucherId)
                    ->first(['vma_voucher_id', 'vde_payto_id']);
                if (! $row) {
                    continue;
                }
                $bank = $this->payeeBank($type, (string) $row->vde_payto_id);
                $affected += VoucherDetail::query()
                    ->where('vma_voucher_id', $voucherId)
                    ->update([
                        'vde_bank_name' => $bank['code'],
                        'vde_bank_acctno' => $bank['accno'],
                        'updatedby' => $username,
                        'updateddate' => now(),
                    ]);
            }
        });

        return $this->sendOk([
            'success' => true,
            'affected' => $affected,
            'message' => 'Bank Account Number Successfully updated.',
        ]);
    }

    /**
     * Resolve the payee's current active (bank code, bank account no.) for
     * the given payee type + id. Mirrors legacy BL lookups.
     *
     * @return array{code:?string, accno:?string}
     */
    private function payeeBank(string $type, string $id): array
    {
        switch ($type) {
            case 'A':
                $row = StudAccount::query()
                    ->where('std_student_id', $id)
                    ->where('sac_status', '1')
                    ->first(['sac_bank_code', 'sac_bank_acc_no']);

                return ['code' => $row?->sac_bank_code, 'accno' => $row?->sac_bank_acc_no];
            case 'B':
                $row = StaffAccount::query()
                    ->where('stf_staff_id', $id)
                    ->where('sta_salary_bank', 'Y')
                    ->where('sta_status', '1')
                    ->first(['sta_acct_code', 'sta_acct_no']);

                return ['code' => $row?->sta_acct_code, 'accno' => $row?->sta_acct_no];
            case 'E':
                $row = Sponsor::query()
                    ->where('spn_sponsor_code', $id)
                    ->first(['spn_bank_name_cd', 'spn_bank_acc_no']);

                return ['code' => $row?->spn_bank_name_cd, 'accno' => $row?->spn_bank_acc_no];
            case 'C':
            case 'D':
            case 'G':
                $row = VendSupplierAccount::query()
                    ->where('vcs_vendor_code', $id)
                    ->where('vsa_status', '1')
                    ->first(['vsa_vendor_bank', 'vsa_bank_accno']);

                return ['code' => $row?->vsa_vendor_bank, 'accno' => $row?->vsa_bank_accno];
        }

        return ['code' => null, 'accno' => null];
    }

    private function bankDescFor(?string $bankCode): ?string
    {
        if ($bankCode === null || $bankCode === '') {
            return null;
        }

        return DB::connection('mysql_secondary')
            ->table('bank_master')
            ->where('bnm_bank_code', $bankCode)
            ->value('bnm_bank_desc');
    }

    /**
     * Payee "ID No" dropdown options for the top filter. Legacy source:
     * `autoSuggestID` in the BL — we replicate it as a simple list. Scoped
     * to 500 rows per type to keep the SPA payload light.
     *
     * @return array<int, array{id:string, label:string}>
     */
    private function idOptions(string $type): array
    {
        switch ($type) {
            case 'A':
                return $this->payeeOptionsFromModel(Student::class, 'std_student_id', 'std_student_name');
            case 'B':
                return $this->payeeOptionsFromModel(Staff::class, 'stf_staff_id', 'stf_staff_name');
            case 'E':
                return $this->payeeOptionsFromModel(Sponsor::class, 'spn_sponsor_code', 'spn_sponsor_name');
            case 'C':
            case 'D':
            case 'G':
                return VendSupplierAccount::query()
                    ->from('vend_supplier_account as vsa')
                    ->join('vend_customer_supplier as vcs', 'vcs.vcs_vendor_code', '=', 'vsa.vcs_vendor_code')
                    ->where('vsa.vsa_status', '1')
                    ->selectRaw("vsa.vcs_vendor_code as id, CONCAT_WS(' - ', vsa.vcs_vendor_code, vcs.vcs_vendor_name) as label")
                    ->distinct()
                    ->orderBy('vsa.vcs_vendor_code')
                    ->limit(500)
                    ->get()
                    ->map(fn ($r) => ['id' => (string) $r->id, 'label' => (string) $r->label])
                    ->toArray();
        }

        return [];
    }

    /**
     * @param  class-string  $modelClass
     * @return array<int, array{id:string, label:string}>
     */
    private function payeeOptionsFromModel(string $modelClass, string $idColumn, string $nameColumn): array
    {
        /** @var \Illuminate\Database\Eloquent\Builder $q */
        $q = $modelClass::query();

        return $q
            ->selectRaw("$idColumn as id, CONCAT_WS(' - ', $idColumn, $nameColumn) as label")
            ->orderBy($idColumn)
            ->limit(500)
            ->get()
            ->map(fn ($r) => ['id' => (string) $r->id, 'label' => (string) $r->label])
            ->toArray();
    }

    private function combineBank(?string $code, ?string $desc): ?string
    {
        if (($code === null || $code === '') && ($desc === null || $desc === '')) {
            return null;
        }
        if ($code !== null && $code !== '' && $desc !== null && $desc !== '') {
            return "$code - $desc";
        }

        return ($code !== null && $code !== '') ? $code : $desc;
    }

    private function likeEscape(string $needle): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
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
}
