<?php

namespace App\Http\Requests;

/**
 * Validates bulk-update payload for Account Payable > Account Bank Updated
 * (PAGEID 1719 / MENUID 2078). Mirrors legacy BL SNA_API_AP_ACCOUNTBANKUPDATED
 * actions `processkemaskini` (bills) and `processkemaskinivoucher` (vouchers):
 * both expect a payee-type + a non-empty list of master IDs to re-sync against
 * the current payee bank on file.
 */
class ProcessAccountBankUpdatedRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payee_type' => 'required|string|in:A,B,C,D,E,G',
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string|max:64',
        ];
    }
}
