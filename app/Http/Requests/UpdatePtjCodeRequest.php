<?php

namespace App\Http\Requests;

class UpdatePtjCodeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'oun_desc' => 'required|string|min:1|max:255',
            'oun_status' => 'required|in:ACTIVE,INACTIVE',
            'org_code' => 'nullable|string|max:30',
            'oun_desc_bi' => 'nullable|string|max:255',
            'org_desc' => 'nullable|string|max:255',
            'oun_address' => 'nullable|string|max:255',
            'oun_state' => 'nullable|string|max:30',
            'st_staff_id_head' => 'nullable|string|max:30',
            'st_staff_id_superior' => 'nullable|string|max:30',
            'oun_tel_no' => 'nullable|string|max:30',
            'oun_fax_no' => 'nullable|string|max:30',
            'oun_code_parent' => 'nullable|string|max:30',
            'oun_level' => 'nullable|integer|between:1,4',
            'tanggung_start_date' => 'nullable|date',
            'tanggung_end_date' => 'nullable|date',
            'oun_shortname' => 'nullable|string|max:100',
            'oun_region' => 'nullable|string|max:30',
            'cny_country_code' => 'nullable|string|max:10',
        ];
    }
}
