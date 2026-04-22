<?php

namespace App\Http\Requests;

class UpdateLetterPhraseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lpm_value_desc_bm' => 'required|string|max:255',
            'lpm_value_desc' => 'nullable|string|max:255',
        ];
    }
}
