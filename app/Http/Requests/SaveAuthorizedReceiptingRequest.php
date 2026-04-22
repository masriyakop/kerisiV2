<?php

namespace App\Http\Requests;

/**
 * Validates the save payload for AR Authorized Receipting Form (MENUID 1953).
 *
 * Source: BL `V2_AUTHORIZED_RECEIPTING_FORM_API` (`details_request` branch
 * with `$_GET['submit']=0`). The legacy shape was a single master row
 * (`authorized_receipting`) plus a `dt_authorized` array of staff who are
 * authorized to issue offline receipts. We keep the same shape.
 *
 * `are_authorized_receipting_id` is optional on create (auto-generated from
 * the `authorized_receipting` sequence when absent).
 */
class SaveAuthorizedReceiptingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'are_authorized_receipting_id' => 'nullable|string|max:50',
            'stf_staff_id' => 'required|string|max:50',
            'stf_staff_id_desc' => 'nullable|string|max:255',
            'oun_code_ptj' => 'required|string|max:50',
            'are_position_code' => 'nullable|string|max:50',
            'are_event_code' => 'nullable|string|max:50',
            'are_reason' => 'nullable|string',
            'are_purposed_code' => 'nullable|string|max:50',
            'are_employment_code' => 'nullable|string|max:50',
            'are_duration_from' => 'nullable|string|max:20',
            'are_duration_to' => 'nullable|string|max:20',
            'are_status' => 'nullable|string|max:20',
            'cpa_project_no' => 'nullable|string|max:50',
            'ptj' => 'nullable|string|max:50',
            'extended' => 'nullable|array',

            'dt_authorized' => 'present|array',
            'dt_authorized.*.ors_id' => 'nullable|string|max:50',
            'dt_authorized.*.ors_staff_id' => 'required|string|max:50',
            'dt_authorized.*.ors_staff_name' => 'nullable|string|max:255',
            'dt_authorized.*.ors_ic' => 'nullable|string|max:50',
            'dt_authorized.*.ors_oun_code' => 'nullable|string|max:50',
            'dt_authorized.*.ors_contact_no' => 'nullable|string|max:50',
            'dt_authorized.*.ors_fax_no' => 'nullable|string|max:50',
            'dt_authorized.*.ors_email' => 'nullable|string|max:255',
            'dt_authorized.*.ors_position' => 'nullable|string|max:255',
            'dt_authorized.*.sts_jobcode' => 'nullable|string|max:255',
            'dt_authorized.*.ors_reason' => 'nullable|string',
            'dt_authorized.*.ors_process_flag' => 'nullable|string|max:5',
        ];
    }
}
