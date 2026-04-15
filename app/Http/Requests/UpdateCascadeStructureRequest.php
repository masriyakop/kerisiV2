<?php

namespace App\Http\Requests;

class UpdateCascadeStructureRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fty_fund_type' => 'required|string|min:1|max:30',
            'at_activity_code' => 'required|string|min:1|max:30',
            'oun_code' => 'required|string|min:1|max:30',
            'ccr_costcentre' => 'required|string|min:1|max:30',
            'ouc_status' => 'required|in:ACTIVE,INACTIVE',
        ];
    }
}
