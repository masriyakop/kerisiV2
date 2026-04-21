<?php

namespace App\Http\Requests;

class UpdateBankAccountRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bnd_bank_acctno' => 'required|string|max:50',
            'oun_code' => 'nullable|string|max:50',
            'bnd_status' => 'required|in:0,1',
            'bnd_is_bank_main' => 'nullable|in:Y,N',
        ];
    }
}
