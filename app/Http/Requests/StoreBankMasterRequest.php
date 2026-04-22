<?php

namespace App\Http\Requests;

class StoreBankMasterRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bnm_bank_code' => 'required|string|min:1|max:50',
            'bnm_bank_code_main' => 'nullable|string|max:20',
            'bnm_bank_desc' => 'required|string|min:1|max:255',
            'bnm_shortname' => 'nullable|string|max:100',
            'bnm_bank_address' => 'required|string',
            'bnm_address_country' => 'nullable|string|max:100',
            'bnm_address_postcode' => 'nullable|string|max:20',
            'bnm_address_city' => 'nullable|string|max:100',
            'bnm_contact_person' => 'required|string|max:255',
            'bnm_branch_name' => 'nullable|string|max:255',
            'bnm_office_telno' => 'nullable|string|max:50',
            'bnm_office_faxno' => 'nullable|string|max:50',
            'bnm_url_address' => 'required|string|max:255',
            'bnm_swift_code' => 'required|string|max:50',
            'bnm_business_nature' => 'nullable|string|max:255',
        ];
    }
}
