<?php

namespace App\Http\Requests;

class StoreActivitySubgroupRequest extends BaseFormRequest
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
            'activity_subgroup_desc' => 'required|string|min:1|max:255',
        ];
    }
}
