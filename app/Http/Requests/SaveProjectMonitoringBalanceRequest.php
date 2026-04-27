<?php

namespace App\Http\Requests;

/**
 * Validation for POST /project-monitoring/updated-balance.
 *
 * Mirrors the legacy `SNA_API_UPDATEDBALANCE_PM?updateAmount=1` body
 * shape:
 *
 *   info.projectID_UB  (camelCase: info.cpaProjectNo)  — required
 *   bal.currBalCash_bal (camelCase: bal.currBalCashBal) — numeric, required;
 *                                                         saved into both
 *                                                         `capital_project.cpa_ytd_balance_amt`
 *                                                         and
 *                                                         `budget.bdg_topup_amt`
 *   bal.seqBudget_bal  (camelCase: bal.seqBudgetBal)   — required, the
 *                                                         `budget.bdg_budget_id`
 *                                                         key the second
 *                                                         UPDATE targets
 *   bal.currBudget_bal (camelCase: bal.currBudgetBal)  — accepted for
 *                                                         legacy fidelity
 *                                                         but the legacy
 *                                                         BL never persists
 *                                                         it; we accept it
 *                                                         and ignore it.
 *
 * The CamelCaseMiddleware converts the incoming JSON keys, so rules are
 * declared in snake_case here.
 */
class SaveProjectMonitoringBalanceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'info' => 'required|array',
            'info.cpa_project_no' => 'required|string|max:191',

            'bal' => 'required|array',
            'bal.curr_bal_cash_bal' => 'required|string',
            'bal.seq_budget_bal' => 'required|string|max:191',
            'bal.curr_budget_bal' => 'nullable|string',
        ];
    }

    /**
     * Normalize legacy-formatted currency strings (e.g. "1,234.56") into a
     * plain numeric string so the controller can cast safely.
     */
    protected function prepareForValidation(): void
    {
        $bal = $this->input('bal', []);
        if (is_array($bal)) {
            foreach (['curr_bal_cash_bal', 'curr_budget_bal'] as $k) {
                if (isset($bal[$k]) && is_string($bal[$k])) {
                    $bal[$k] = trim(str_replace([','], '', $bal[$k]));
                }
            }
            $this->merge(['bal' => $bal]);
        }
    }
}
