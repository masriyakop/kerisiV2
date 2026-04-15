<?php

namespace App\Http\Requests;

class StoreCostCentreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ccr_costcentre' => 'required|string|min:1|max:30',
            'ccr_costcentre_desc' => 'required|string|min:1',
            'ccr_costcentre_desc_eng' => 'nullable|string',
            'oun_code' => 'required|string|min:1|max:30',
            'ccr_address' => 'nullable|string',
            'ccr_hostel_code' => 'nullable|string|max:30',
            'ccr_status' => 'required|in:ACTIVE,INACTIVE',
            'ccr_flag_salary' => 'required|in:Y,N',
        ];
    }
}
