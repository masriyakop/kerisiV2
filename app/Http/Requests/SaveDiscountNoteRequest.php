<?php

namespace App\Http\Requests;

/**
 * Validates the save-draft payload for AR Discount Note Form (MENUID 1784).
 *
 * Source: BL `DT_AR_DISCOUNT_NOTE_FORM` action `saveDcNote`. Mirrors the
 * legacy `$_POST['head']` / `$_POST['debit']` / `$_POST['credit']` shape
 * used by the Credit + Debit Note forms, with the extra
 * `dcp_dc_policy_id` the legacy form carries on `head` (the selected
 * discount-note policy drives which invoice lines are discountable).
 */
class SaveDiscountNoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'head' => 'required|array',
            'head.dcm_discount_note_master_id' => 'nullable|string|max:50',
            'head.dcm_dcnote_no' => 'nullable|string|max:50',
            'head.cim_invoice_no' => 'required|string|max:50',
            'head.cim_cust_invoice_id' => 'required|string|max:50',
            'head.dcm_cust_id' => 'required|string|max:50',
            'head.dcm_cust_type' => 'required|string|max:50',
            'head.dcm_cust_type_desc' => 'nullable|string|max:255',
            'head.dcm_cust_name' => 'required|string|max:255',
            'head.dcm_dcnote_desc' => 'nullable|string',
            'head.dcm_dcnote_date' => 'required|string|max:20',
            'head.dcm_dc_total_amount' => 'nullable|numeric',
            'head.dcp_dc_policy_id' => 'required|string|max:50',

            'debit' => 'present|array',
            'debit.*.dcd_cust_invoice_detl_id' => 'required|string|max:50',
            'debit.*.dcd_item_category' => 'nullable|string|max:50',
            'debit.*.cii_item_code' => 'nullable|string|max:50',
            'debit.*.dcd_detail_desc' => 'nullable|string',
            'debit.*.fty_fund_type' => 'nullable|string|max:50',
            'debit.*.at_activity_code' => 'nullable|string|max:50',
            'debit.*.oun_code' => 'nullable|string|max:50',
            'debit.*.ccr_costcentre' => 'nullable|string|max:50',
            'debit.*.cpa_project_no' => 'nullable|string|max:50',
            'debit.*.acm_acct_code' => 'nullable|string|max:50',
            'debit.*.dcd_taxcode' => 'nullable|string|max:50',
            'debit.*.dcd_invoice_amt' => 'nullable|numeric',
            'debit.*.dcd_dcnote_amt' => 'nullable|numeric',
            'debit.*.dcd_taxamt' => 'nullable|numeric',
            'debit.*.dcd_dc_taxamt' => 'nullable|numeric',
            'debit.*.dcd_bal_amt' => 'nullable|numeric',
            'debit.*.extended' => 'nullable|array',

            'credit' => 'present|array',
            'credit.*.dcd_cust_invoice_detl_id' => 'required|string|max:50',
            'credit.*.dcd_item_category' => 'nullable|string|max:50',
            'credit.*.cii_item_code' => 'nullable|string|max:50',
            'credit.*.dcd_detail_desc' => 'nullable|string',
            'credit.*.fty_fund_type' => 'nullable|string|max:50',
            'credit.*.at_activity_code' => 'nullable|string|max:50',
            'credit.*.oun_code' => 'nullable|string|max:50',
            'credit.*.ccr_costcentre' => 'nullable|string|max:50',
            'credit.*.cpa_project_no' => 'nullable|string|max:50',
            'credit.*.acm_acct_code' => 'nullable|string|max:50',
            'credit.*.dcd_taxcode' => 'nullable|string|max:50',
            'credit.*.dcd_invoice_amt' => 'nullable|numeric',
            'credit.*.dcd_dcnote_amt' => 'nullable|numeric',
            'credit.*.dcd_taxamt' => 'nullable|numeric',
            'credit.*.dcd_dc_taxamt' => 'nullable|numeric',
            'credit.*.dcd_bal_amt' => 'nullable|numeric',
            'credit.*.extended' => 'nullable|array',
        ];
    }
}
