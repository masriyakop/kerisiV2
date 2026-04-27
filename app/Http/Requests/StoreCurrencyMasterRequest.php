<?php

namespace App\Http\Requests;

/**
 * Create a new currency under `currency_master`. Mirrors the legacy
 * `saveModal` branch in BL `QLA_API_GLOBAL_LISTOFCURRENCY` (PAGEID 2636 /
 * MENUID 3198).
 */
class StoreCurrencyMasterRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cym_currency_code' => 'required|string|max:10',
            'cym_currency_desc' => 'required|string|max:255',
            'cny_country_code' => 'required|string|max:10',
            'cyd_unit' => 'required|numeric|min:0',
            'cym_enabled' => 'nullable|in:Active,Inactive,1,0',
        ];
    }
}
