<?php

namespace App\Http\Requests;

class StoreActivityTypeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_group_code' => 'required|string|min:1|max:30',
            'activity_subgroup_code' => 'required|string|min:1|max:30',
            'activity_subsiri_code' => 'required|string|min:1|max:30',
            'at_activity_code' => 'required|string|min:1|max:30',
            'at_activity_description_bm' => 'required|string|min:1|max:255',
            'at_activity_description_en' => 'nullable|string|max:255',
            'at_status' => 'required|in:ACTIVE,INACTIVE',
        ];
    }
}
