<?php

namespace App\Http\Requests;

/**
 * PUT /api/credit-control/deposit-form/{id}/detail/{detailId}.
 *
 * Mirrors the legacy `updateModal` branch in NAD_API_CC_DEPOSIT_DETAILS: the
 * popup modal only updates `ddt_description`, `ddt_currency_code`,
 * `ddt_ent_amt` (entered foreign amount), `ddt_transaction_ref` and the
 * master's `dpm_ref_no`. Everything else on a detail row is derived at
 * posting time by downstream modules and MUST NOT be changed here.
 */
class UpdateDepositDetailRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ddt_description' => 'nullable|string|max:2000',
            'ddt_currency_code' => 'nullable|string|max:10',
            'ddt_ent_amt' => 'nullable|numeric',
            'ddt_transaction_ref' => 'nullable|string|max:100',
            'dpm_ref_no' => 'nullable|string|max:250',
        ];
    }
}
