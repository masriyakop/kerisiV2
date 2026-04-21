<?php

namespace App\Http\Requests;

class UpdateSemiStrictRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sbss_column_selection' => 'required|string|in:ACCOUNT,ACTIVITY',
            'sbss_level_selection' => 'required|string|in:1,2,3,4,5,6',
        ];
    }
}
