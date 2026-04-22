<?php

namespace App\Http\Requests;

/**
 * Validates the save-draft payload for AR Debit Note Form (MENUID 1783).
 *
 * Source: BL `DT_AR_DEBIT_NOTE_FORM` action `saveDnNote`. Symmetric to
 * `SaveCreditNoteRequest` but keyed to `dnm_*` columns. Line item primary
 * keys are `dnd_id` (master side) and debit/credit lists both write into
 * the same `debit_note_details` table, separated by `dnd_transaction_type`.
 */
class SaveDebitNoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'head' => 'required|array',
            'head.dnm_debit_note_master_id' => 'nullable|string|max:50',
            'head.dnm_dnnote_no' => 'nullable|string|max:50',
            'head.cim_invoice_no' => 'required|string|max:50',
            'head.cim_cust_invoice_id' => 'required|string|max:50',
            'head.dnm_cust_id' => 'required|string|max:50',
            'head.dnm_cust_type' => 'required|string|max:50',
            'head.dnm_cust_type_desc' => 'nullable|string|max:255',
            'head.dnm_cust_name' => 'required|string|max:255',
            'head.dnm_dnnote_desc' => 'nullable|string',
            'head.dnm_dnnote_date' => 'required|string|max:20',
            'head.dnm_dn_total_amount' => 'nullable|numeric',

            'debit' => 'present|array',
            'debit.*.dnd_cust_invoice_detl_id' => 'required|string|max:50',
            'debit.*.dnd_item_category' => 'nullable|string|max:50',
            'debit.*.cii_item_code' => 'nullable|string|max:50',
            'debit.*.cnd_detail_desc' => 'nullable|string',
            'debit.*.fty_fund_type' => 'nullable|string|max:50',
            'debit.*.at_activity_code' => 'nullable|string|max:50',
            'debit.*.oun_code' => 'nullable|string|max:50',
            'debit.*.ccr_costcentre' => 'nullable|string|max:50',
            'debit.*.cpa_project_no' => 'nullable|string|max:50',
            'debit.*.acm_acct_code' => 'nullable|string|max:50',
            'debit.*.dnd_taxcode' => 'nullable|string|max:50',
            'debit.*.dnd_invoice_amt' => 'nullable|numeric',
            'debit.*.dnd_dnnote_amt' => 'nullable|numeric',
            'debit.*.dnd_dn_taxamt' => 'nullable|numeric',
            'debit.*.dnd_bal_amt' => 'nullable|numeric',
            'debit.*.extended' => 'nullable|array',

            'credit' => 'present|array',
            'credit.*.dnd_cust_invoice_detl_id' => 'required|string|max:50',
            'credit.*.dnd_item_category' => 'nullable|string|max:50',
            'credit.*.cii_item_code' => 'nullable|string|max:50',
            'credit.*.cnd_detail_desc' => 'nullable|string',
            'credit.*.fty_fund_type' => 'nullable|string|max:50',
            'credit.*.at_activity_code' => 'nullable|string|max:50',
            'credit.*.oun_code' => 'nullable|string|max:50',
            'credit.*.ccr_costcentre' => 'nullable|string|max:50',
            'credit.*.cpa_project_no' => 'nullable|string|max:50',
            'credit.*.acm_acct_code' => 'nullable|string|max:50',
            'credit.*.dnd_taxcode' => 'nullable|string|max:50',
            'credit.*.dnd_invoice_amt' => 'nullable|numeric',
            'credit.*.dnd_dnnote_amt' => 'nullable|numeric',
            'credit.*.dnd_dn_taxamt' => 'nullable|numeric',
            'credit.*.dnd_bal_amt' => 'nullable|numeric',
            'credit.*.extended' => 'nullable|array',
        ];
    }
}
