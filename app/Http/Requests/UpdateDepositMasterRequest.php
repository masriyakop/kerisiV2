<?php

namespace App\Http\Requests;

/**
 * PUT /api/credit-control/deposit-form/{id}.
 *
 * Mirrors the legacy `edit_process` branch in NAD_API_CC_DEPOSIT_DETAILS: the
 * only editable fields on the Deposit master from MENUID 3397 are the notes /
 * payer metadata + contract window. All fields are nullable on the wire and
 * persisted as-is; amounts and GL codes are never touched from this screen.
 */
class UpdateDepositMasterRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dpm_ref_no_note' => 'nullable|string|max:400',
            'dpm_payto_type' => 'nullable|string|max:4',
            'vcs_vendor_code' => 'nullable|string|max:20',
            'dpm_vendor_name' => 'nullable|string|max:200',
            'dpm_contract_no' => 'nullable|string|max:50',
            'dpm_start_date' => 'nullable|string',
            'dpm_end_date' => 'nullable|string',
        ];
    }
}
