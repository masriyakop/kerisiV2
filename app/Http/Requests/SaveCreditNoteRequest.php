<?php

namespace App\Http\Requests;

/**
 * Validates the save-draft payload for AR Credit Note Form (MENUID 1782).
 *
 * Source: BL `DT_AR_CREDIT_NOTE_FORM` action `saveCrNote`. Mirrors the legacy
 * structure of `$_POST['head']` (master header), `$_POST['debit']` (debit
 * side lines selected from the invoice) and `$_POST['credit']` (credit side).
 *
 * Both line arrays must be real arrays (may be empty). Each line carries the
 * raw invoice-detail ID plus description mirrors so the controller can
 * persist the same JSON fingerprints the legacy BL did.
 */
class SaveCreditNoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'head' => 'required|array',
            'head.cnm_credit_note_master_id' => 'nullable|string|max:50',
            'head.cnm_crnote_no' => 'nullable|string|max:50',
            'head.cim_invoice_no' => 'required|string|max:50',
            'head.cim_cust_invoice_id' => 'required|string|max:50',
            'head.cnm_cust_id' => 'required|string|max:50',
            'head.cnm_cust_type' => 'required|string|max:50',
            'head.cnm_cust_type_desc' => 'nullable|string|max:255',
            'head.cnm_cust_name' => 'required|string|max:255',
            'head.cnm_crnote_desc' => 'nullable|string',
            'head.cnm_crnote_date' => 'required|string|max:20',
            'head.cnm_cn_total_amount' => 'nullable|numeric',

            'debit' => 'present|array',
            'debit.*.cnd_cust_invoice_detl_id' => 'required|string|max:50',
            'debit.*.cnd_item_category' => 'nullable|string|max:50',
            'debit.*.cii_item_code' => 'nullable|string|max:50',
            'debit.*.cnd_detail_desc' => 'nullable|string',
            'debit.*.fty_fund_type' => 'nullable|string|max:50',
            'debit.*.at_activity_code' => 'nullable|string|max:50',
            'debit.*.oun_code' => 'nullable|string|max:50',
            'debit.*.ccr_costcentre' => 'nullable|string|max:50',
            'debit.*.cpa_project_no' => 'nullable|string|max:50',
            'debit.*.acm_acct_code' => 'nullable|string|max:50',
            'debit.*.cnd_taxcode' => 'nullable|string|max:50',
            'debit.*.cnd_invoice_amt' => 'nullable|numeric',
            'debit.*.cnd_crnote_amt' => 'nullable|numeric',
            'debit.*.cnd_cn_taxamt' => 'nullable|numeric',
            'debit.*.cnd_bal_amt' => 'nullable|numeric',
            'debit.*.extended' => 'nullable|array',

            'credit' => 'present|array',
            'credit.*.cnd_cust_invoice_detl_id' => 'required|string|max:50',
            'credit.*.cnd_item_category' => 'nullable|string|max:50',
            'credit.*.cii_item_code' => 'nullable|string|max:50',
            'credit.*.cnd_detail_desc' => 'nullable|string',
            'credit.*.fty_fund_type' => 'nullable|string|max:50',
            'credit.*.at_activity_code' => 'nullable|string|max:50',
            'credit.*.oun_code' => 'nullable|string|max:50',
            'credit.*.ccr_costcentre' => 'nullable|string|max:50',
            'credit.*.cpa_project_no' => 'nullable|string|max:50',
            'credit.*.acm_acct_code' => 'nullable|string|max:50',
            'credit.*.cnd_taxcode' => 'nullable|string|max:50',
            'credit.*.cnd_invoice_amt' => 'nullable|numeric',
            'credit.*.cnd_crnote_amt' => 'nullable|numeric',
            'credit.*.cnd_cn_taxamt' => 'nullable|numeric',
            'credit.*.cnd_bal_amt' => 'nullable|numeric',
            'credit.*.extended' => 'nullable|array',
        ];
    }
}
