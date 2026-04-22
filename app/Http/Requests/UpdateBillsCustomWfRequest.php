<?php

namespace App\Http\Requests;

class UpdateBillsCustomWfRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bis_sequence_level' => 'required',
        ];
    }
}
