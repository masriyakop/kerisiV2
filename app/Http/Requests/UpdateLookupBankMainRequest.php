<?php

namespace App\Http\Requests;

class UpdateLookupBankMainRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lbm_bank_name' => 'required|string|min:1|max:255',
            'is_bank_main' => 'nullable|in:Y,N',
            'lbm_status' => 'required|in:0,1',
        ];
    }
}
