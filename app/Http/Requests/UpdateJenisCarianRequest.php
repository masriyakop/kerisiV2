<?php

namespace App\Http\Requests;

class UpdateJenisCarianRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sbss_status' => 'required|string|in:ACTIVE,INACTIVE',
        ];
    }
}
