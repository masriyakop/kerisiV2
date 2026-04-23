<?php

namespace App\Http\Requests;

/**
 * Validates the save-draft payload for Petty Cash Claim Form (MENUID 1872).
 *
 * Source: BL `MM_API_PETTYCASH_PETTYCASHCLAIMFORM` action `?save=1`. Mirrors
 * the legacy `$_POST` shape (`pms_request_by`, `pms_request_date`,
 * `pms_total_amt`, `pms_request_by_desc`, `pcm_id`) plus split line item
 * arrays (`apps.new` for inserts, `apps.edit` for updates).
 *
 * Following the AR note-form pattern (see
 * `.cursor/rules/ar-note-form-pattern.mdc`), workflow routing is stubbed —
 * the controller persists rows with `pms_status = 'ENTRY'` and the UI
 * handles the submit/cancel flips via separate endpoints.
 */
class SavePettyCashClaimRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'head' => 'required|array',
            'head.pms_id' => 'nullable|integer',
            'head.pms_application_no' => 'nullable|string|max:50',
            'head.pms_request_by' => 'required|string|max:50',
            'head.pms_request_by_desc' => 'nullable|string|max:255',
            'head.pms_request_date' => 'required|string|max:20',
            'head.pms_total_amt' => 'nullable|numeric',
            // Legacy BL reads a `pcm_id` from the header to pre-populate the
            // record, but the user may also construct a line from scratch
            // using Fund Type / Activity / OU / Cost Centre without picking
            // a Petty Cash Main, so we accept it as optional.
            'head.pcm_id' => 'nullable|integer',

            'lines' => 'required|array|min:1',
            'lines.*.pcd_id' => 'nullable|integer',
            'lines.*.pcd_receipt_no' => 'required|string|max:100',
            'lines.*.pcd_trans_desc' => 'required|string|max:1000',
            'lines.*.pcd_trans_amt' => 'required|numeric|gt:0',
            'lines.*.pcm_id' => 'nullable|integer',
            'lines.*.fty_fund_type' => 'required|string|max:50',
            'lines.*.at_activity_code' => 'required|string|max:50',
            'lines.*.oun_code' => 'required|string|max:50',
            'lines.*.ccr_costcentre' => 'required|string|max:50',
            'lines.*.cpa_project_no' => 'nullable|string|max:50',
            'lines.*.so_code' => 'nullable|string|max:50',
            'lines.*.acm_acct_code' => 'nullable|string|max:50',
        ];
    }
}
