<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDepositDetailRequest;
use App\Http\Requests\UpdateDepositMasterRequest;
use App\Http\Traits\ApiResponse;
use App\Models\DepositDetails;
use App\Models\DepositMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Credit Control > Detail of Deposit" (PAGEID 2688 / MENUID 3397).
 *
 * Source: FIMS BL `NAD_API_CC_DEPOSIT_DETAILS`. The page has three moving
 * parts that map to this controller:
 *   - `show($id)`               → `$_GET['master']` branch, master form.
 *   - `details($id)`            → `$_GET['dt_deposit_details']` branch.
 *   - `update($id)`             → `$_GET['edit_process']` master edit.
 *   - `updateDetail(id, did)`   → `$_GET['updateModal']` popup form.
 *
 * The legacy screen does NOT create new deposits (there is no insert SQL in
 * the BL) — deposits are created by upstream subsystems (AR receipting,
 * AP payment, etc.) and this page only lets the user curate notes,
 * descriptions, and contract metadata. We mirror that scope precisely.
 */
class DepositFormController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/credit-control/deposit-form/{id} — master form payload.
     */
    public function show(int $id): JsonResponse
    {
        $row = DB::connection('mysql_secondary')
            ->table('deposit_master as dm')
            ->join('deposit_details as dd', 'dd.dpm_deposit_master_id', '=', 'dm.dpm_deposit_master_id')
            ->join('account_main as am', function ($j) {
                $j->on('am.acm_acct_code', '=', 'dd.acm_acct_code')
                    ->where('am.acm_flag_subsidiary', 'Y')
                    ->where('am.acm_flag_deposit', 'Y');
            })
            ->where('dm.dpm_deposit_master_id', $id)
            ->selectRaw(<<<'SQL'
                dm.dpm_deposit_master_id,
                dm.dpm_deposit_no,
                dm.dpm_ref_no_note,
                DATE_FORMAT(dm.dpm_start_date, '%d/%m/%Y') as dpm_start_date,
                DATE_FORMAT(dm.dpm_end_date, '%d/%m/%Y') as dpm_end_date,
                (SELECT dct_category_desc FROM deposit_category WHERE dct_category_code = dm.dpm_deposit_category) as dpm_deposit_category_desc,
                dm.dpm_deposit_category as dpm_deposit_category,
                (SELECT lde_description FROM lookup_details WHERE lma_code_name = 'CUSTOMER_TYPE' AND dm.dpm_payto_type = lde_value) as dpm_payto_type_desc,
                dm.dpm_payto_type,
                dm.vcs_vendor_code,
                dm.dpm_vendor_name,
                dm.dpm_ref_no,
                dm.dpm_contract_no,
                dm.dpm_status,
                dd.ddt_doc_no,
                dd.ddt_description,
                dd.fty_fund_type,
                dd.at_activity_code,
                dd.oun_code,
                dd.ccr_costcentre,
                dd.acm_acct_code,
                IF(dd.ddt_type='DT', dd.ddt_amt, 0) as debit_amt,
                IF(dd.ddt_type='CR', dd.ddt_amt, 0) as credit_amt
            SQL)
            ->orderBy('dd.ddt_line_no')
            ->first();

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Deposit not found');
        }

        return $this->sendOk([
            'dpmDepositMasterId' => (int) $row->dpm_deposit_master_id,
            'dpmDepositNo' => $row->dpm_deposit_no,
            'dpmRefNoNote' => $row->dpm_ref_no_note,
            'dpmStartDate' => $row->dpm_start_date,
            'dpmEndDate' => $row->dpm_end_date,
            'dpmDepositCategory' => $row->dpm_deposit_category,
            'dpmDepositCategoryDesc' => $row->dpm_deposit_category_desc,
            'dpmPaytoType' => $row->dpm_payto_type,
            'dpmPaytoTypeDesc' => $row->dpm_payto_type_desc,
            'vcsVendorCode' => $row->vcs_vendor_code,
            'dpmVendorName' => $row->dpm_vendor_name,
            'dpmRefNo' => $row->dpm_ref_no,
            'dpmContractNo' => $row->dpm_contract_no,
            'dpmStatus' => $row->dpm_status,
        ]);
    }

    /**
     * GET /api/credit-control/deposit-form/{id}/details.
     */
    public function details(Request $request, int $id): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'ddt_doc_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = [
            'ddt_doc_no', 'ddt_description', 'fty_fund_type', 'at_activity_code',
            'oun_code', 'ccr_costcentre', 'acm_acct_code', 'ddt_transaction_ref',
            'ddt_currency_code', 'ddt_ent_amt', 'debit_amt', 'credit_amt',
        ];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'ddt_doc_no';
        }
        $sortColumn = match ($sortBy) {
            'debit_amt' => DB::raw("IF(dd.ddt_type='DT', dd.ddt_amt, 0)"),
            'credit_amt' => DB::raw("IF(dd.ddt_type='CR', dd.ddt_amt, 0)"),
            default => 'dd.' . $sortBy,
        };

        $base = $this->detailsBase($id);

        if ($q !== '') {
            $like = $this->likeEscape($q);
            $base->where(function (Builder $b) use ($like) {
                foreach ([
                    'dd.ddt_doc_no', 'dd.ddt_description', 'dd.fty_fund_type',
                    'dd.at_activity_code', 'dd.oun_code', 'dd.ccr_costcentre',
                    'dd.acm_acct_code', 'dd.ddt_transaction_ref', 'dd.ddt_currency_code',
                ] as $col) {
                    $b->orWhereRaw('LOWER(IFNULL(' . $col . ', "")) LIKE ?', [$like]);
                }
            });
        }

        $totals = (clone $base)
            ->selectRaw("COUNT(*) as c, COALESCE(SUM(IF(dd.ddt_type='DT', dd.ddt_amt, 0)),0) as a, COALESCE(SUM(IF(dd.ddt_type='CR', dd.ddt_amt, 0)),0) as b")
            ->first();

        $total = (int) ($totals->c ?? 0);

        $rows = (clone $base)
            ->selectRaw(<<<'SQL'
                dd.ddt_deposit_detl_id,
                dm.dpm_deposit_master_id,
                dd.ddt_doc_no,
                dd.ddt_description,
                dd.fty_fund_type,
                dd.at_activity_code,
                dd.oun_code,
                dd.ccr_costcentre,
                dd.acm_acct_code,
                dd.ddt_transaction_ref,
                dd.ddt_currency_code,
                dd.ddt_ent_amt,
                IF(dd.ddt_type='DT', dd.ddt_ent_amt, 0) as debit_ent_amt,
                IF(dd.ddt_type='CR', dd.ddt_ent_amt, 0) as credit_ent_amt,
                IF(dd.ddt_type='DT', dd.ddt_amt, 0) as debit_amt,
                IF(dd.ddt_type='CR', dd.ddt_amt, 0) as credit_amt,
                dd.ddt_type
            SQL)
            ->orderBy($sortColumn, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'ddtDepositDetlId' => (int) $r->ddt_deposit_detl_id,
            'dpmDepositMasterId' => (int) $r->dpm_deposit_master_id,
            'ddtDocNo' => $r->ddt_doc_no,
            'ddtDescription' => $r->ddt_description,
            'ftyFundType' => $r->fty_fund_type,
            'atActivityCode' => $r->at_activity_code,
            'ounCode' => $r->oun_code,
            'ccrCostcentre' => $r->ccr_costcentre,
            'acmAcctCode' => $r->acm_acct_code,
            'ddtTransactionRef' => $r->ddt_transaction_ref,
            'ddtCurrencyCode' => $r->ddt_currency_code,
            'ddtEntAmt' => (float) ($r->ddt_ent_amt ?? 0),
            'debitEntAmt' => (float) ($r->debit_ent_amt ?? 0),
            'creditEntAmt' => (float) ($r->credit_ent_amt ?? 0),
            'debitAmt' => (float) ($r->debit_amt ?? 0),
            'creditAmt' => (float) ($r->credit_amt ?? 0),
            'ddtType' => $r->ddt_type,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => [
                'debitAmt' => (float) ($totals->a ?? 0),
                'creditAmt' => (float) ($totals->b ?? 0),
            ],
        ]);
    }

    /**
     * PUT /api/credit-control/deposit-form/{id} — master edit (edit_process).
     */
    public function update(UpdateDepositMasterRequest $request, int $id): JsonResponse
    {
        $master = DepositMaster::query()->where('dpm_deposit_master_id', $id)->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Deposit not found');
        }

        $data = $request->validated();

        $payload = array_filter([
            'dpm_ref_no_note' => array_key_exists('dpm_ref_no_note', $data) ? $data['dpm_ref_no_note'] : null,
            'dpm_payto_type' => $data['dpm_payto_type'] ?? null,
            'vcs_vendor_code' => $data['vcs_vendor_code'] ?? null,
            'dpm_vendor_name' => $data['dpm_vendor_name'] ?? null,
            'dpm_contract_no' => $data['dpm_contract_no'] ?? null,
            'dpm_start_date' => $this->parseLegacyDate($data['dpm_start_date'] ?? null),
            'dpm_end_date' => $this->parseLegacyDate($data['dpm_end_date'] ?? null),
        ], fn ($v) => $v !== null);

        // dpm_ref_no_note may legitimately be cleared to ""; keep the key when explicitly sent
        if ($request->has('dpm_ref_no_note')) {
            $payload['dpm_ref_no_note'] = $request->input('dpm_ref_no_note');
        }

        $payload['updateddate'] = Carbon::now();
        $payload['updatedby'] = (string) (Auth::user()->email ?? Auth::user()->name ?? 'system');

        $master->fill($payload);
        $master->save();

        return $this->sendOk([
            'dpmDepositMasterId' => (int) $master->dpm_deposit_master_id,
            'dpmRefNoNote' => $master->dpm_ref_no_note,
            'dpmPaytoType' => $master->dpm_payto_type,
            'vcsVendorCode' => $master->vcs_vendor_code,
            'dpmVendorName' => $master->dpm_vendor_name,
            'dpmContractNo' => $master->dpm_contract_no,
            'dpmStartDate' => $master->dpm_start_date ? $master->dpm_start_date->format('d/m/Y') : null,
            'dpmEndDate' => $master->dpm_end_date ? $master->dpm_end_date->format('d/m/Y') : null,
        ]);
    }

    /**
     * PUT /api/credit-control/deposit-form/{id}/detail/{detailId} (updateModal).
     */
    public function updateDetail(UpdateDepositDetailRequest $request, int $id, int $detailId): JsonResponse
    {
        $master = DepositMaster::query()->where('dpm_deposit_master_id', $id)->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Deposit not found');
        }

        $detail = DepositDetails::query()
            ->where('ddt_deposit_detl_id', $detailId)
            ->where('dpm_deposit_master_id', $id)
            ->first();
        if (! $detail) {
            return $this->sendError(404, 'NOT_FOUND', 'Deposit detail not found');
        }

        $data = $request->validated();
        $now = Carbon::now();
        $user = (string) (Auth::user()->email ?? Auth::user()->name ?? 'system');

        DB::connection('mysql_secondary')->transaction(function () use ($detail, $master, $data, $now, $user) {
            $detail->fill([
                'ddt_description' => $data['ddt_description'] ?? $detail->ddt_description,
                'ddt_currency_code' => $data['ddt_currency_code'] ?? $detail->ddt_currency_code,
                'ddt_ent_amt' => array_key_exists('ddt_ent_amt', $data)
                    ? (float) str_replace(',', '', (string) $data['ddt_ent_amt'])
                    : $detail->ddt_ent_amt,
                'ddt_transaction_ref' => $data['ddt_transaction_ref'] ?? $detail->ddt_transaction_ref,
                'updateddate' => $now,
                'updatedby' => $user,
            ]);
            $detail->save();

            if (array_key_exists('dpm_ref_no', $data) && $data['dpm_ref_no'] !== null) {
                $master->fill([
                    'dpm_ref_no' => $data['dpm_ref_no'],
                    'updateddate' => $now,
                    'updatedby' => $user,
                ]);
                $master->save();
            }
        });

        return $this->sendOk([
            'ddtDepositDetlId' => (int) $detail->ddt_deposit_detl_id,
            'dpmDepositMasterId' => (int) $detail->dpm_deposit_master_id,
            'ddtDescription' => $detail->ddt_description,
            'ddtCurrencyCode' => $detail->ddt_currency_code,
            'ddtEntAmt' => (float) ($detail->ddt_ent_amt ?? 0),
            'ddtTransactionRef' => $detail->ddt_transaction_ref,
            'dpmRefNo' => $master->dpm_ref_no,
        ]);
    }

    /**
     * Unified Customer ID / Customer Name autosuggest mirroring legacy
     * `autoSuggestCustID` / `autoSuggestCustName` branches.
     */
    public function searchCustomer(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $limit = min(50, max(1, (int) $request->input('limit', 20)));

        $base = DB::connection('mysql_secondary')->table('vend_customer_supplier')
            ->selectRaw('vcs_vendor_code as id, vcs_vendor_name as name');

        $myflite = DB::connection('mysql_secondary')->table('vend_customer_supplier_myflite')
            ->selectRaw('vcs_vendor_code as id, vcs_vendor_name as name');

        $union = $base->unionAll($myflite);

        $raw = DB::connection('mysql_secondary')
            ->query()
            ->fromSub($union, 'tbl')
            ->select(['id', 'name']);

        if ($q !== '') {
            $like = $this->likeEscape($q);
            $raw->where(function ($b) use ($like) {
                $b->orWhereRaw('LOWER(IFNULL(id, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(name, "")) LIKE ?', [$like]);
            });
        }

        $rows = $raw
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'label' => trim(($r->id ?? '') . ' - ' . ($r->name ?? '')),
                'name' => (string) ($r->name ?? ''),
            ])
            ->values();

        return $this->sendOk($rows);
    }

    // --------------------------------------------------------------------------

    private function detailsBase(int $id): Builder
    {
        return DepositDetails::query()
            ->from('deposit_details as dd')
            ->join('deposit_master as dm', 'dm.dpm_deposit_master_id', '=', 'dd.dpm_deposit_master_id')
            ->join('account_main as am', function ($j) {
                $j->on('am.acm_acct_code', '=', 'dd.acm_acct_code')
                    ->where('am.acm_flag_subsidiary', 'Y')
                    ->where('am.acm_flag_deposit', 'Y');
            })
            ->where('dm.dpm_deposit_master_id', $id);
    }

    private function parseLegacyDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $value = trim($value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d'] as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value);
                if ($dt !== false) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // continue with next format
            }
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function likeEscape(string $needle): string
    {
        $n = mb_strtolower($needle, 'UTF-8');

        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $n) . '%';
    }
}
