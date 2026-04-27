<?php

namespace App\Http\Requests;

/**
 * Form request for the "Mark investment as withdrawn" action on the
 * Investment to be Withdrawn page (PAGEID 2895 / MENUID 3485).
 *
 * The legacy BL `API_INV_WITHDRAWN` (action=edit_investment) takes a
 * single identifier (`ipf_investment_no`) and performs a fixed UPDATE
 * that sets `ipf_status_withdraw='APPROVE'` and `ipf_batch_no_wdraw='SYSTEM'`.
 * In the migrated flow the controller looks the investment up by its
 * route parameter, so there is no body payload to validate. The request
 * still exists to satisfy the "Form Request on every mutating endpoint"
 * policy in CLAUDE.md and to keep a stable extension point if future
 * iterations add fields such as `batch_no` or `remark`.
 */
class WithdrawInvestmentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
