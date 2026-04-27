<?php

namespace App\Http\Requests;

/**
 * Update an existing currency. The legacy `updateModal` branch only mutates
 * `cyd_unit` and `cym_enabled`; the country/currency code remain immutable.
 */
class UpdateCurrencyMasterRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cyd_unit' => 'required|numeric|min:0',
            'cym_enabled' => 'required|in:Active,Inactive,1,0',
        ];
    }
}
