<?php

namespace App\Http\Requests;

/**
 * Update payload for the Portal > Staff Profile marital-status modal
 * (PAGEID 1581 / MENUID 1914, components 8722 + 8912).
 *
 * Source: legacy `?updateMaritalStatus=1` in BL
 * `API_PORTAL_SALARYPROFILEINFORMATION`. The lookup_details existence
 * check happens inside the controller so we can return a friendly
 * 422 with a per-field error rather than depending on a Laravel
 * `exists:` rule against the legacy `mysql_secondary` connection.
 */
class UpdateStaffProfileMaritalStatusRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marital_status' => 'required|string|max:10',
        ];
    }
}
