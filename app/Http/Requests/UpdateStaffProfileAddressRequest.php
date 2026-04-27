<?php

namespace App\Http\Requests;

/**
 * Update payload for the Portal > Staff Profile address form
 * (PAGEID 1581 / MENUID 1914, component 8721 + 8912).
 *
 * Source: legacy `?saveAddress=1` in BL `API_PORTAL_SALARYPROFILEINFORMATION`.
 * The legacy BL silently accepted blank values for every column except
 * `sa_address1`; we keep that contract but enforce a minimal address1
 * + a digits-only handphone (the legacy frontend filtered handphone
 * via a regex too).
 */
class UpdateStaffProfileAddressRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sa_address_type' => 'nullable|integer|min:1',
            'sa_address1' => 'required|string|max:255|min:1',
            'sa_address2' => 'nullable|string|max:255',
            'sa_pcode' => 'nullable|string|max:20',
            'sa_city' => 'nullable|string|max:100',
            'sa_state' => 'nullable|string|max:100',
            'sa_country' => 'nullable|string|max:100',
            'stf_handphone_no' => 'nullable|string|max:30|regex:/^\d*$/',
        ];
    }

    public function messages(): array
    {
        return [
            'stf_handphone_no.regex' => 'Handphone number must contain digits only.',
        ];
    }
}
