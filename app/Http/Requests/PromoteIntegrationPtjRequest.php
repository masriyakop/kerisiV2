<?php

namespace App\Http\Requests;

/**
 * Promote a staged `int_organization_unit` row into the production
 * `organization_unit` table. Mirrors the legacy `process_insert` branch in
 * BL `AS_BL_SM_INTEGRATIONPTJ` (PAGEID 1860 / MENUID 2277).
 */
class PromoteIntegrationPtjRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'iou_code' => 'required|string|max:50',
            'iou_desc' => 'required|string|max:255',
            'iou_code_persis' => 'nullable|string|max:50',
            'iou_bursar_flag' => 'nullable|string|max:50',
            'org_code' => 'nullable|string|max:50',
            'org_desc' => 'nullable|string|max:255',
            'iou_address' => 'nullable|string',
            'iou_tel_no' => 'nullable|string|max:50',
            'iou_fax_no' => 'nullable|string|max:50',
            'oun_level' => 'nullable|string|max:50',
            'oun_code_parent' => 'nullable|string|max:50',
        ];
    }
}
