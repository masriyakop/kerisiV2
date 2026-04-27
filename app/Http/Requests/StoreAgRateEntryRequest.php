<?php

namespace App\Http\Requests;

/**
 * Manual-entry / paste-from-JANM save flow for the AG Rate page (PAGEID 2647
 * / MENUID 3199). Mirrors the legacy `entry_save` branch in BL
 * `QLA_API_GLOBAL_UPLOADCURRENCY` — bulk insert one `currency_details` row
 * per (currency, day) for the chosen year+month.
 */
class StoreAgRateEntryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cyd_year' => 'required|integer|min:1900|max:2999',
            'cyd_month' => 'required|integer|min:1|max:12',
            'rates' => 'required|array|min:1',
            'rates.*.cym_currency_code' => 'required|string|max:10',
            'rates.*.cyd_unit' => 'nullable|numeric|min:0',
            'rates.*.cyd_conversation_rate' => 'required|numeric|min:0',
        ];
    }
}
