<?php

namespace App\Http\Requests;

/**
 * Form request for "Generate Schedule" on the Investment > Generate
 * Schedule page (PAGEID 1206 / MENUID 1475).
 *
 * Source: legacy BL `INSERT_UPDATE_INVESTMENT_ACCRUAL` with
 * `mode=generateScheduleAccrual`. The legacy payload is
 * `$_POST['ipf_investment']` keyed by investment_no; here we accept a
 * flat string array under `investment_numbers` for clarity.
 *
 * The controller re-validates each number against the page scope
 * (status = APPROVE|MATURED AND no existing accrual row) before
 * invoking the stored procedure, so these rules only cover payload
 * shape / bounds.
 */
class GenerateScheduleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // At least one investment must be selected. The legacy UI
            // allows the user to tick up to every row on the page; we
            // cap at 500 to keep the stored-procedure fan-out bounded
            // (5 page lengths of 100). Adjust only with a reason.
            'investment_numbers' => 'required|array|min:1|max:500',
            'investment_numbers.*' => 'required|string|max:100',
        ];
    }
}
