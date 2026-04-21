<?php

namespace App\Http\Requests;

class UpdateBillsSetupRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bis_status' => 'required|string|in:ACTIVE,INACTIVE',
        ];
    }
}
