<?php

namespace App\Http\Requests;

class UpdateVcTncRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'st_staff_id_superior' => 'required|string|max:50',
        ];
    }
}
