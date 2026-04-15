<?php

namespace App\Http\Requests;

class UpdateActivityGroupRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_group_desc' => 'required|string|min:1|max:255',
        ];
    }
}
