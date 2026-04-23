<?php

namespace App\Http\Requests;

/**
 * Form request for "Post to TB" on the Investment > Accrual page
 * (PAGEID 1175 / MENUID 1446).
 *
 * Source: legacy BL `INSERT_UPDATE_INVESTMENT_ACCRUAL` (default /
 * `else` branch — the Investment > Accrual action, not the
 * `generateScheduleAccrual` mode). The legacy payload is
 * `$_POST['ipf_investment']` keyed by a composite
 * `{ipf_investment_no}_{iac_id}` string; here we accept a flat
 * integer array under `accrual_ids` since the iac_id alone is
 * sufficient to resolve the full row + its investment on the
 * server.
 *
 * The controller re-validates each iac_id against the page scope
 * (iac_start_date < current_date() AND pmt_posting_no IS NULL AND a
 * matching `investment_acct_setup` row) before invoking the
 * stored procedures + posting inserts, so these rules only cover
 * payload shape / bounds.
 */
class PostAccrualToTbRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // At least one accrual must be selected. The legacy UI lets
            // the user tick every row on the page; we cap at 500 to
            // keep the stored-procedure fan-out bounded (5 page
            // lengths of 100 — same cap as the sibling Generate
            // Schedule endpoint). Adjust only with a reason.
            'accrual_ids' => 'required|array|min:1|max:500',
            'accrual_ids.*' => 'required|integer|min:1',
        ];
    }
}
