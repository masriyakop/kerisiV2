<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelNoteRequest;
use App\Http\Requests\SaveCreditNoteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CreditNoteDetails;
use App\Models\CreditNoteMaster;
use App\Models\CustInvoiceDetails;
use App\Models\CustInvoiceMaster;
use App\Models\LookupDetail;
use App\Models\VendCustomerSupplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AR > Credit Note Form (PAGEID 1474 / MENUID 1782).
 *
 * Source: BL `DT_AR_CREDIT_NOTE_FORM`. The legacy page mapped six `?action`
 * branches (temp, tempCredit, saveCrNote, submitCrNote, detailMaster,
 * detailHead, dropdown) plus two `$_GET` flags (cancelcr, dt_processFlow).
 * We split that single entry point into REST endpoints:
 *
 *   GET  /...?invoice_id=  → invoiceLines  (temp + tempCredit combined)
 *   GET  /{id}             → show          (detailMaster + detailHead)
 *   POST /                 → saveDraft     (saveCrNote, inserts/updates)
 *   POST /{id}/submit      → submit        (submitCrNote stub; see notes)
 *   POST /{id}/cancel      → cancel        (cancelcr stub; see notes)
 *   GET  /{id}/process-flow→ processFlow   (dt_processFlow audit history)
 *
 * Workflow notes (Wave B architectural caveat):
 *   - Legacy `submitCrNote` / `$_GET['cancelcr']` call FIMS stored procedures
 *     (`workflowSubmit`, `workflowUpdate`, `credit_note_cancel`) and a web
 *     of `wf_task` / `wf_subapplication_status` rows. Those procedures and
 *     their dependent tables are **not ported** to this stack yet.
 *   - To keep the page usable while the workflow engine is out of scope we:
 *       - `saveDraft` persists the master + line items as status 'Draft'.
 *       - `submit` flips the master to status 'Entry' (mirror of the legacy
 *         post-workflowSubmit update) and returns `workflow_stub=true` so
 *         the UI knows no real workflow task was created.
 *       - `cancel` flips the master to status 'CANCELLED' and records the
 *         reason in `cnm_extended_field`, no SP call.
 *       - `processFlow` returns an empty list until workflow history tables
 *         (`wf_application_status`, `wf_process`, `staff_service`) are
 *         migrated.
 *   - When the FIMS workflow engine is eventually ported, replace the
 *     stub methods below with real SP invocations — the request / response
 *     contracts have been kept intentionally minimal to allow that swap.
 */
class CreditNoteFormController extends Controller
{
    use ApiResponse;

    /**
     * GET `/account-receivable/lookup/customer-type`.
     *
     * Backs the legacy `Debtor Type` dropdown on Credit/Debit/Discount Note
     * forms. The legacy query in the page spec is:
     *
     *   SELECT concat(lde_value,'#',lde_description2) FLC_ID,
     *          lde_description2 FLC_NAME
     *   FROM fims_usr.lookup_details
     *   WHERE lma_code_name='CUSTOMER_TYPE' AND lde_status='1'
     *   ORDER BY lde_sorting
     *
     * We return `[ { value, label }, ... ]` where `value` is the same
     * `code#label` composite the master table stores in `cnm_cust_type`.
     */
    public function customerTypes(): JsonResponse
    {
        $rows = LookupDetail::query()
            ->where('lma_code_name', 'CUSTOMER_TYPE')
            ->where('lde_status', '1')
            ->orderBy('lde_sorting')
            ->get(['lde_value', 'lde_description', 'lde_description2']);

        return $this->sendOk(
            $rows->map(function ($r) {
                $label = (string) ($r->lde_description2 ?? $r->lde_description ?? $r->lde_value);
                $code = (string) $r->lde_value;
                return [
                    'value' => $code . '#' . $label,
                    'label' => $label,
                    'code' => $code,
                ];
            })->values()->all()
        );
    }

    /**
     * GET `/account-receivable/credit-note-form/search-debtor?q=&limit=&cust_type=`.
     *
     * Backs the `Customer / Debtor Name *` autosuggest dropdown on
     * Credit/Debit/Discount note forms. The legacy page POSTs
     * `sddCustomerName` as `code#label` (see BL `DT_AR_CREDIT_NOTE_FORM`:139),
     * so we return the same composite in `value` for parity with the
     * persistence layer.
     *
     * `cust_type` filter (optional) follows the legacy
     * `BL_AUTOSUGGEST_RECC_FEE` split:
     *   - `C` (Creditor) → `vcs_iscreditor='Y'`
     *   - anything else (D / B / A / F / G / U / blank) → `vcs_isdebtor='Y'`
     *
     * Data source: `vend_customer_supplier`. Non-debtor customer types
     * (staff, students, …) that live outside this table are out of scope
     * for the current migration; the dropdown will simply show no matches
     * for those types until those source tables are ported.
     */
    public function searchDebtors(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $custType = trim((string) $request->input('cust_type', ''));
        // Accept either bare code ('D') or legacy composite ('D#DEBTOR').
        if ($custType !== '' && str_contains($custType, '#')) {
            $custType = explode('#', $custType, 2)[0];
        }
        $custType = strtoupper($custType);

        $limit = (int) $request->input('limit', 20);
        if ($limit < 1 || $limit > 50) {
            $limit = 20;
        }

        $query = VendCustomerSupplier::query();
        if ($custType === 'C') {
            $query->where('vcs_iscreditor', 'Y');
        } else {
            $query->where('vcs_isdebtor', 'Y');
        }

        if ($q !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $query->where(function ($b) use ($needle) {
                $b->whereRaw("LOWER(IFNULL(vcs_vendor_code, '')) LIKE ?", [$needle])
                    ->orWhereRaw("LOWER(IFNULL(vcs_vendor_name, '')) LIKE ?", [$needle]);
            });
        }

        $rows = $query
            ->orderBy('vcs_vendor_code')
            ->limit($limit)
            ->get(['vcs_vendor_code', 'vcs_vendor_name']);

        return $this->sendOk(
            $rows->map(function ($r) {
                $code = (string) ($r->vcs_vendor_code ?? '');
                $name = (string) ($r->vcs_vendor_name ?? '');
                return [
                    'value' => $code.'#'.$name,
                    'label' => $name !== '' ? ($code.' — '.$name) : $code,
                    'code' => $code,
                    'name' => $name,
                ];
            })->values()->all()
        );
    }

    /**
     * GET `/account-receivable/credit-note-form/search-invoice?cust_id=&q=&limit=&require_balance=`.
     *
     * Backs the `Invoice No *` autosuggest on **Credit** and **Debit** note
     * forms (shared URL). Lists `APPROVE` invoices for `cust_id`. By default
     * (`require_balance` omitted or true) only rows with `cim_bal_amt > 0`
     * are returned; Credit/Debit note UIs pass `require_balance=0` so
     * fully-paid approved invoices still appear (legacy parity).
     *
     * Returned `value` is the legacy `sddInvoiceNo` composite `id#invoice_no`
     * (see BL line 22: `explode('#', sddInvoiceNo)[1] = invoice_no`).
     */
    public function searchInvoices(Request $request): JsonResponse
    {
        $custId = trim((string) $request->input('cust_id', ''));
        if ($custId === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'cust_id is required');
        }
        $q = trim((string) $request->input('q', ''));
        $limit = (int) $request->input('limit', 20);
        if ($limit < 1 || $limit > 50) {
            $limit = 20;
        }

        // `cim_cust_id` + `cim_status='APPROVE'` always apply.
        //
        // Open-balance filter: when **true** (default), only invoices with
        // `cim_bal_amt > 0` are listed. Credit and Debit note forms pass
        // `require_balance=0` so approved invoices with zero header balance
        // still appear (e.g. debtor has only paid-up APPROVE rows and CANCEL
        // history otherwise).
        //
        // Query flag: `require_balance=0` → omit the `cim_bal_amt > 0` clause.
        $requireBalance = $request->boolean('require_balance', true);

        $builder = DB::connection('mysql_secondary')
            ->table('cust_invoice_master')
            ->where('cim_cust_id', $custId)
            ->where('cim_status', 'APPROVE');

        if ($requireBalance) {
            $builder->where('cim_bal_amt', '>', 0);
        }

        if ($q !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $builder->whereRaw("LOWER(IFNULL(cim_invoice_no, '')) LIKE ?", [$needle]);
        }

        $rows = $builder
            ->orderByDesc('cim_invoice_date')
            ->limit($limit)
            ->get(['cim_cust_invoice_id', 'cim_invoice_no', 'cim_invoice_date', 'cim_bal_amt', 'cim_total_amt']);

        return $this->sendOk(
            $rows->map(function ($r) {
                $id = (string) ($r->cim_cust_invoice_id ?? '');
                $no = (string) ($r->cim_invoice_no ?? '');
                $date = $r->cim_invoice_date ? date('d/m/Y', strtotime((string) $r->cim_invoice_date)) : '';
                $bal = number_format((float) ($r->cim_bal_amt ?? 0), 2);
                $parts = array_filter([$no, $date, 'MYR '.$bal]);
                return [
                    'value' => $id.'#'.$no,
                    'label' => implode(' — ', $parts),
                    'invoiceId' => $id,
                    'invoiceNo' => $no,
                    'balance' => (float) ($r->cim_bal_amt ?? 0),
                    'total' => (float) ($r->cim_total_amt ?? 0),
                ];
            })->values()->all()
        );
    }

    public function invoiceLines(Request $request): JsonResponse
    {
        $invoiceId = (string) $request->input('invoice_id', '');
        if ($invoiceId === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'invoice_id is required');
        }

        $all = CustInvoiceDetails::query()
            ->where('cim_cust_invoice_id', $invoiceId)
            ->get([
                'cid_cust_invoice_detl_id',
                'cim_cust_invoice_id',
                'cii_item_category',
                'cii_item_code',
                'fty_fund_type',
                'at_activity_code',
                'oun_code',
                'ccr_costcentre',
                'cpa_project_no',
                'acm_acct_code',
                'cid_taxcode',
                'cid_taxamt',
                'cid_total_amt',
                'cid_crnote_amt',
                'cid_dnnote_amt',
                'cid_dcnote_amt',
                'cid_nett_amt',
                'cid_bal_amt',
                'cid_transaction_type',
                DB::raw("cid_extended_field->>'\$.cii_item_category_desc' as cii_item_category_desc"),
                DB::raw("cid_extended_field->>'\$.cii_item_code_desc' as cii_item_code_desc"),
                DB::raw("cid_extended_field->>'\$.fty_fund_type_desc' as fty_fund_type_desc"),
                DB::raw("cid_extended_field->>'\$.at_activity_code_desc' as at_activity_code_desc"),
                DB::raw("cid_extended_field->>'\$.oun_desc' as oun_desc"),
                DB::raw("cid_extended_field->>'\$.ccr_costcentre_charged_desc' as ccr_costcentre_charged_desc"),
                DB::raw("cid_extended_field->>'\$.acm_acct_desc' as acm_acct_desc"),
            ]);

        $mapLine = function ($r) {
            return [
                'ID' => (string) $r->cid_cust_invoice_detl_id,
                'invoiceId' => (string) $r->cim_cust_invoice_id,
                'feeCategoryId' => $r->cii_item_category,
                'feeCategory' => ($r->cii_item_category !== null || $r->cii_item_category_desc !== null)
                    ? trim((string) $r->cii_item_category) . ' - ' . (string) $r->cii_item_category_desc
                    : null,
                'cii_item_code' => $r->cii_item_code,
                'feeItem' => ($r->cii_item_code !== null || $r->cii_item_code_desc !== null)
                    ? trim((string) $r->cii_item_code) . ' - ' . (string) $r->cii_item_code_desc
                    : null,
                'fty_fund_type' => $r->fty_fund_type,
                'fundType' => $r->fty_fund_type_desc,
                'at_activity_code' => $r->at_activity_code,
                'activityCode' => $r->at_activity_code_desc,
                'oun_code' => $r->oun_code,
                'ptjCode' => $r->oun_desc,
                'ccr_costcentre' => $r->ccr_costcentre,
                'costcentre' => $r->ccr_costcentre_charged_desc,
                'cpa_project_no' => $r->cpa_project_no,
                'codeSO' => $r->cpa_project_no,
                'acm_acct_code' => $r->acm_acct_code,
                'acctCode' => $r->acm_acct_desc,
                'taxCode' => $r->cid_taxcode,
                'taxAmt' => (float) ($r->cid_taxamt ?? 0),
                'amt' => (float) ($r->cid_bal_amt ?? 0),
                'totalAmt' => (float) ($r->cid_nett_amt ?? 0),
                'dnAmt' => (float) ($r->cid_dnnote_amt ?? 0),
                'dcAmt' => (float) ($r->cid_dcnote_amt ?? 0),
                'transactionType' => $r->cid_transaction_type,
            ];
        };

        // Legacy quirk: the 'debit' block of the Credit Note form actually
        // displays invoice lines with transaction_type='CR', and the
        // 'credit' block the 'DT' lines. We replicate that mapping so the
        // UI and the stored `cnd_transaction_type` remain symmetric with
        // legacy data.
        $debit = $all->where('cid_transaction_type', 'CR')->values()->map($mapLine);
        $credit = $all->where('cid_transaction_type', 'DT')->values()->map($mapLine);

        return $this->sendOk([
            'debit' => $debit,
            'credit' => $credit,
            'invoiceBalance' => (float) $all->where('cid_transaction_type', 'CR')->sum('cid_bal_amt'),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $master = CreditNoteMaster::query()
            ->where('cnm_credit_note_master_id', $id)
            ->where('cnm_system_id', 'AR_CN')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Credit note not found');
        }

        $invoiceMeta = null;
        if ($master->cim_invoice_no) {
            $invoiceMeta = CustInvoiceMaster::query()
                ->where('cim_invoice_no', $master->cim_invoice_no)
                ->first(['cim_cust_invoice_id', 'cim_invoice_no', 'cim_cust_id', 'cim_cust_type', 'cim_bal_amt', 'cim_total_amt', 'cim_status']);
        }

        $details = CreditNoteDetails::query()
            ->where('cnm_credit_note_master_id', $master->cnm_credit_note_master_id)
            ->get();

        $mapDetail = function ($d) {
            $ext = $this->decodeJson($d->cnd_extended_field ?? null);

            return [
                'cnd_id' => (string) $d->cnd_id,
                'cnd_cust_invoice_detl_id' => $d->cnd_cust_invoice_detl_id,
                'ID' => (string) ($d->cnd_cust_invoice_detl_id ?? $d->cnd_id),
                'cnd_item_category' => $d->cnd_item_category,
                'feeCategoryId' => $d->cnd_item_category,
                'feeCategory' => $ext['feeCategory'] ?? $ext['cii_item_category_desc'] ?? null,
                'cii_item_code' => $d->cii_item_code,
                'feeItem' => $ext['feeItem'] ?? $ext['cii_item_code_desc'] ?? null,
                'cnd_detail_desc' => $d->cnd_detail_desc,
                'fty_fund_type' => $d->fty_fund_type,
                'fundType' => $ext['fundType'] ?? $ext['fty_fund_type_desc'] ?? null,
                'at_activity_code' => $d->at_activity_code,
                'activityCode' => $ext['activityCode'] ?? $ext['at_activity_code_desc'] ?? null,
                'oun_code' => $d->oun_code,
                'ptjCode' => $ext['ptjCode'] ?? $ext['oun_desc'] ?? null,
                'ccr_costcentre' => $d->ccr_costcentre,
                'costcentre' => $ext['costcentre'] ?? $ext['ccr_costcentre_charged_desc'] ?? null,
                'cpa_project_no' => $d->cpa_project_no,
                'codeSO' => $d->cpa_project_no,
                'acm_acct_code' => $d->acm_acct_code,
                'acctCode' => $ext['acctCode'] ?? $ext['acm_acct_desc'] ?? null,
                'cnd_taxcode' => $d->cnd_taxcode,
                'taxCode' => $d->cnd_taxcode,
                'taxAmt' => (float) ($d->cnd_cn_taxamt ?? 0),
                'cnd_invoice_amt' => (float) ($d->cnd_invoice_amt ?? 0),
                'amt' => (float) ($d->cnd_invoice_amt ?? 0),
                'cnd_crnote_amt' => (float) ($d->cnd_crnote_amt ?? 0),
                'cnAmt' => (float) ($d->cnd_crnote_amt ?? 0),
                'cnd_bal_amt' => (float) ($d->cnd_bal_amt ?? 0),
                'balance' => (float) ($d->cnd_bal_amt ?? 0),
                'cnd_transaction_type' => $d->cnd_transaction_type,
            ];
        };

        // Legacy visual mapping: debit tab = DT rows, credit tab = CR rows.
        $debit = $details->where('cnd_transaction_type', 'DT')->values()->map($mapDetail);
        $credit = $details->where('cnd_transaction_type', 'CR')->values()->map($mapDetail);

        $ext = $this->decodeJson($master->cnm_extended_field);

        return $this->sendOk([
            'head' => [
                'cnm_credit_note_master_id' => (string) $master->cnm_credit_note_master_id,
                'cnm_crnote_no' => $master->cnm_crnote_no,
                'cim_invoice_no' => $master->cim_invoice_no,
                'cim_cust_invoice_id' => $invoiceMeta?->cim_cust_invoice_id ? (string) $invoiceMeta->cim_cust_invoice_id : null,
                'cnm_cust_id' => $master->cnm_cust_id,
                'cnm_cust_type' => $master->cnm_cust_type,
                'cnm_cust_type_desc' => $ext['cnm_cust_type_desc'] ?? null,
                'cnm_cust_name' => $master->cnm_cust_name,
                'cnm_crnote_desc' => $master->cnm_crnote_desc,
                'cnm_crnote_date' => $master->cnm_crnote_date,
                'cnm_cn_total_amount' => (float) ($master->cnm_cn_total_amount ?? 0),
                'cnm_status_cd' => $master->cnm_status_cd,
                'cnm_status_cd_desc' => $ext['cnm_status_cd_desc'] ?? $master->cnm_status_cd,
                'invoiceTotalAmount' => $invoiceMeta ? (float) ($invoiceMeta->cim_total_amt ?? 0) : 0,
                'invoiceBalanceAmount' => $invoiceMeta ? (float) ($invoiceMeta->cim_bal_amt ?? 0) : 0,
            ],
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    public function saveDraft(SaveCreditNoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $head = $data['head'];
        $username = $this->currentUsername();

        $masterId = (string) ($head['cnm_credit_note_master_id'] ?? '');
        $masterCode = (string) ($head['cnm_crnote_no'] ?? '');
        $isNew = $masterId === '';

        if ($isNew) {
            $masterId = (string) $this->nextSeq('credit_note_master');
            if ($masterCode === '') {
                $masterCode = $this->generateNoteNo('CN', $username);
            }
        }

        $extended = [
            'cnm_cust_type_desc' => (string) ($head['cnm_cust_type_desc'] ?? ''),
            'cnm_status_cd_desc' => 'Draft',
        ];

        $totalAmt = isset($head['cnm_cn_total_amount']) ? (float) $head['cnm_cn_total_amount'] : null;
        $nowStr = now()->format('Y-m-d H:i:s');

        DB::connection('mysql_secondary')->transaction(function () use ($masterId, $masterCode, $head, $extended, $totalAmt, $nowStr, $username, $isNew, $data) {
            $payload = [
                'cnm_credit_note_master_id' => $masterId,
                'cnm_crnote_no' => $masterCode,
                'cim_invoice_no' => $head['cim_invoice_no'],
                'cnm_cust_id' => $head['cnm_cust_id'],
                'cnm_cust_type' => $head['cnm_cust_type'],
                'cnm_cust_name' => $head['cnm_cust_name'],
                'cnm_crnote_desc' => $head['cnm_crnote_desc'] ?? null,
                'cnm_crnote_date' => $this->parseLegacyDate($head['cnm_crnote_date'] ?? null),
                'cnm_cn_total_amount' => $totalAmt,
                'cnm_status_cd' => 'Draft',
                'cnm_system_id' => 'AR_CN',
                'cnm_extended_field' => json_encode($extended, JSON_UNESCAPED_UNICODE),
            ];

            if ($isNew) {
                CreditNoteMaster::query()->create(array_merge($payload, [
                    'createdby' => $username,
                    'createddate' => $nowStr,
                ]));
            } else {
                CreditNoteMaster::query()
                    ->where('cnm_credit_note_master_id', $masterId)
                    ->update(array_merge($payload, [
                        'updatedby' => $username,
                        'updateddate' => $nowStr,
                    ]));
            }

            CreditNoteDetails::query()
                ->where('cnm_credit_note_master_id', $masterId)
                ->delete();

            $this->persistDetails($masterId, $data['debit'] ?? [], 'DT', $username, $nowStr);
            $this->persistDetails($masterId, $data['credit'] ?? [], 'CR', $username, $nowStr);

            // Recompute master total from the persisted DT lines (legacy
            // behaviour: total CN = SUM(cnd_crnote_amt WHERE type='DT')).
            $resolvedTotal = (float) CreditNoteDetails::query()
                ->where('cnm_credit_note_master_id', $masterId)
                ->where('cnd_transaction_type', 'DT')
                ->sum('cnd_crnote_amt');
            CreditNoteMaster::query()
                ->where('cnm_credit_note_master_id', $masterId)
                ->update(['cnm_cn_total_amount' => $resolvedTotal]);
        });

        return $this->sendOk([
            'status' => 'ok',
            'cnID' => (string) $masterId,
            'creditNoteNo' => $masterCode,
            'status_cd' => 'Draft',
        ]);
    }

    public function submit(string $id): JsonResponse
    {
        $master = CreditNoteMaster::query()
            ->where('cnm_credit_note_master_id', $id)
            ->where('cnm_system_id', 'AR_CN')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Credit note not found');
        }

        $ext = $this->decodeJson($master->cnm_extended_field);
        $ext['cnm_status_cd_desc'] = 'Entry';

        CreditNoteMaster::query()
            ->where('cnm_credit_note_master_id', $id)
            ->update([
                'cnm_status_cd' => 'Entry',
                'cnm_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'updatedby' => $this->currentUsername(),
                'updateddate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->sendOk([
            'status' => 'ok',
            'status_cd' => 'Entry',
            // `workflow_stub=true` signals the UI that no `wf_task`
            // or approver chain was created — see class docblock.
            'workflow_stub' => true,
            'message' => 'Credit note marked as Entry. Workflow routing is not yet migrated; approver chain must be configured in a later release.',
        ]);
    }

    public function cancel(CancelNoteRequest $request, string $id): JsonResponse
    {
        $master = CreditNoteMaster::query()
            ->where('cnm_credit_note_master_id', $id)
            ->where('cnm_system_id', 'AR_CN')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Credit note not found');
        }

        $ext = $this->decodeJson($master->cnm_extended_field);
        $ext['cnm_status_cd_desc'] = 'Cancelled';
        $ext['cnm_cancel_reason'] = $request->validated()['cancel_reason'];
        $ext['cnm_cancelled_at'] = now()->toAtomString();
        $ext['cnm_cancelled_by'] = $this->currentUsername();

        CreditNoteMaster::query()
            ->where('cnm_credit_note_master_id', $id)
            ->update([
                'cnm_status_cd' => 'CANCELLED',
                'cnm_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'updatedby' => $this->currentUsername(),
                'updateddate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->sendOk([
            'status' => 'ok',
            'status_cd' => 'CANCELLED',
            'message' => 'Credit note cancelled.',
        ]);
    }

    public function processFlow(string $id): JsonResponse
    {
        $master = CreditNoteMaster::query()
            ->where('cnm_credit_note_master_id', $id)
            ->where('cnm_system_id', 'AR_CN')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Credit note not found');
        }

        // Workflow history (`wf_application_status`, `wf_process`,
        // `staff_service`) is not yet migrated; return an empty audit list
        // so the UI can render "No workflow history" gracefully.
        return $this->sendOk([], [
            'workflow_stub' => true,
            'note' => 'Workflow history tables are not yet migrated.',
        ]);
    }

    private function persistDetails(string $masterId, array $lines, string $type, string $username, string $nowStr): void
    {
        foreach ($lines as $line) {
            $ext = $line['extended'] ?? [
                'feeCategory' => null,
                'feeItem' => null,
                'fundType' => null,
                'activityCode' => null,
                'ptjCode' => null,
                'costcentre' => null,
                'codeSO' => null,
                'acctCode' => null,
            ];

            CreditNoteDetails::query()->create([
                'cnd_id' => (string) $this->nextSeq('credit_note_details'),
                'cnm_credit_note_master_id' => $masterId,
                'cnd_line_no' => '1',
                'cnd_item_category' => $line['cnd_item_category'] ?? null,
                'cii_item_code' => $line['cii_item_code'] ?? null,
                'cnd_detail_desc' => $line['cnd_detail_desc'] ?? null,
                'fty_fund_type' => $line['fty_fund_type'] ?? null,
                'at_activity_code' => $line['at_activity_code'] ?? null,
                'oun_code' => $line['oun_code'] ?? null,
                'ccr_costcentre' => $line['ccr_costcentre'] ?? null,
                'cpa_project_no' => $line['cpa_project_no'] ?? null,
                'acm_acct_code' => $line['acm_acct_code'] ?? null,
                'cnd_taxcode' => $line['cnd_taxcode'] ?? null,
                'cnd_invoice_amt' => $line['cnd_invoice_amt'] ?? null,
                'cnd_crnote_amt' => $line['cnd_crnote_amt'] ?? null,
                'cnd_cn_taxamt' => $line['cnd_cn_taxamt'] ?? null,
                'cnd_bal_amt' => $line['cnd_bal_amt'] ?? null,
                'cnd_status' => 'Draft',
                'cnd_cust_invoice_detl_id' => $line['cnd_cust_invoice_detl_id'],
                'cnd_transaction_type' => $type,
                'cnd_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'createdby' => $username,
                'createddate' => $nowStr,
            ]);
        }
    }

    private function decodeJson(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function parseLegacyDate(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        // Legacy forms emit d/m/Y strings; fall back to Y-m-d pass-through
        // when the value already looks ISO.
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $raw, $m)) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }
        return $raw;
    }

    private function nextSeq(string $table): int
    {
        // FIMS legacy uses CALL getTableSequenceNum(...). We emulate it by
        // bumping the largest current id by 1 inside the same transaction.
        $col = match ($table) {
            'credit_note_master' => 'cnm_credit_note_master_id',
            'credit_note_details' => 'cnd_id',
            default => 'id',
        };
        $max = (int) DB::connection('mysql_secondary')->table($table)->max($col);
        return $max + 1;
    }

    private function generateNoteNo(string $prefix, string $username): string
    {
        // Simple running number: {PREFIX}-{YYYYMM}-{seq}. Legacy uses
        // `getRefNo()` with fund-type prefix; until that helper is ported
        // we emit a temporary but deterministic code and surface it to the
        // UI so the user can see what was persisted.
        $count = CreditNoteMaster::query()
            ->whereRaw("DATE_FORMAT(createddate, '%Y%m') = ?", [now()->format('Ym')])
            ->count();
        return sprintf('%s-%s-%05d', $prefix, now()->format('Ym'), $count + 1);
    }

    private function currentUsername(): string
    {
        return (string) (Auth::user()->email ?? Auth::user()->name ?? 'system');
    }
}
