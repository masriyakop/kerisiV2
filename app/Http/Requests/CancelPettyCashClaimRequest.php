<?php

namespace App\Http\Requests;

/**
 * Validates the cancel payload for the Petty Cash Claim Form (MENUID 1872).
 *
 * Legacy `MM_API_PETTYCASH_PETTYCASHCLAIMFORM` has no explicit cancel branch
 * — cancellation happens through the FIMS workflow engine (`wf_task`). Until
 * that SP chain is ported (see `.cursor/rules/ar-note-form-pattern.mdc`
 * rule 6) the controller flips `pms_status = 'CANCELLED'` and records the
 * reason in `pms_extended_field`, mirroring the note-form stub.
 */
class CancelPettyCashClaimRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => 'required|string|min:3|max:500',
        ];
    }
}
