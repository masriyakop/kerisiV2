<?php

namespace App\Http\Requests;

class UpdateAccountActivityRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lde_value' => 'required|string|min:1|max:30',
            'lde_description' => 'required|string|min:1|max:255',
            'lde_description2' => 'nullable|string|max:255',
            'lde_status' => 'required|in:ACTIVE,INACTIVE',
        ];
    }
}
