<?php

namespace App\Http\Requests;

class UpdateActivitySubsiriRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_group' => 'required|string|min:1|max:30',
            'activity_subgroup_code' => 'required|string|min:1|max:30',
            'activity_subsiri_desc' => 'required|string|min:1|max:255',
            'activity_subsiri_desc_eng' => 'nullable|string|max:255',
        ];
    }
}
