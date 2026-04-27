<?php

namespace App\Http\Requests;

/**
 * Promote a staged `int_costcentre` row into the production `costcentre`
 * table. Mirrors the legacy `process_insert` branch in BL
 * `AS_BL_SM_INTEGRATIONCOSTCENTRE` (PAGEID 1861 / MENUID 2278).
 */
class PromoteIntegrationCostCentreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ics_costcentre' => 'required|string|max:50',
            'ics_costcentre_desc' => 'required|string|max:255',
            'ics_hostel_code' => 'nullable|string|max:50',
            'ics_status' => 'nullable|in:Active,Unactive,1,2',
        ];
    }
}
