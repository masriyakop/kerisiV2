<?php

namespace App\Http\Requests;

/**
 * Validates create payload for Account Payable > Utility Registration
 * (PAGEID 2881 / MENUID 3466). Mirrors legacy BL
 * SNA_API_AP_UTILITYREGISTRATION action `process_register_utility` (cm_mode = new).
 */
class StoreUtilityRegistrationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vcs_vendor_name' => 'required|string|min:1|max:255',
            'vcs_biller_code' => 'required|string|max:50',
            'vcs_vendor_status' => 'required|in:0,1',
        ];
    }
}
