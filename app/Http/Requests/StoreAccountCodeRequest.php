<?php

namespace App\Http\Requests;

class StoreAccountCodeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acm_acct_level' => 'required|integer|between:1,5',
            'acm_acct_code' => 'required|string|min:1|max:30',
            'acm_acct_desc' => 'required|string|min:1|max:255',
            'acm_acct_desc_eng' => 'nullable|string|max:255',
            'acm_acct_status' => 'required|in:ACTIVE,INACTIVE',
            'acm_acct_group' => 'nullable|string|max:30',
            'acm_acct_activity' => 'nullable|string|max:30',
            'acm_acct_parent' => 'nullable|string|max:30',
        ];
    }
}
