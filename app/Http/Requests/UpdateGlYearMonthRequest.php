<?php

namespace App\Http\Requests;

class UpdateGlYearMonthRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // See StoreGlYearMonthRequest — regex rules with `|` must be arrays.
        return [
            'gym_year' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'gym_month' => ['required', 'string', 'size:2', 'regex:/^(0[1-9]|1[0-2])$/'],
            'gym_status' => ['required', 'string', 'in:OPEN,CLOSE'],
            'gym_remark' => ['nullable', 'string', 'max:400'],
        ];
    }
}
