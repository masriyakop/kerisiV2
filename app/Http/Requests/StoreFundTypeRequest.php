<?php

namespace App\Http\Requests;

class StoreFundTypeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fty_fund_type' => 'required|string|min:1|max:50',
            'fty_fund_desc' => 'required|string|min:1',
            'fty_fund_desc_eng' => 'nullable|string',
            'fty_basis' => 'required|string|min:1|max:100',
            'fty_status' => 'required|boolean',
            'fty_remark' => 'nullable|string',
        ];
    }
}
