<?php

namespace App\Http\Requests;

/**
 * Update payload for the Vendor Portal > Vendor Portal master form
 * (PAGEID 1622 / MENUID 1961, components 5257 + 4737 + 4738).
 *
 * Source: legacy `?detail_process=1` in BL
 * `NF_BL_PURCHASING_PORTAL_VENDOR` (the direct-edit path that updates
 * `vend_customer_supplier` in place — see the legacy `else if
 * ($_GET['detail_process'])` branch). Mirrors the writable columns the
 * legacy form pushed via `data` and `data2` envelopes, plus the
 * `vcs_extended_field` JSON keys driven by component 5257
 * (Application Information).
 *
 * Field map (camelCase request key from CamelCaseMiddleware ->
 * snake_case validated key here -> destination column):
 *
 *   vendor_name            -> vcs_vendor_name              (required)
 *   email                  -> vcs_email_address            (required, email)
 *   tel_no                 -> vcs_tel_no                   (required)
 *   fax_no                 -> vcs_fax_no                   (nullable)
 *   bumi_status            -> vcs_bumi_status              (required, taraf)
 *   contact_person         -> vcs_contact_person           (required)
 *   is_creditor            -> vcs_iscreditor               (Y|N)
 *   is_debtor              -> vcs_isdebtor                 (Y|N)
 *   tax_reg_no             -> vcs_tax_regno                (nullable, GST)
 *   epf_no                 -> vcs_epf_no                   (nullable)
 *   socso_no               -> vcs_socso_no                 (nullable)
 *   biller_code            -> vcs_biller_code              (nullable)
 *   ic_no                  -> vcs_ic_no                    (nullable)
 *   company_category       -> vcs_company_category         (nullable)
 *   authorize_capital      -> vcs_authorize_capital        (numeric)
 *   paid_up_capital        -> vcs_paid_up_capital          (numeric)
 *   registration_no        -> vcs_registration_no  (SSM)   (nullable)
 *   reg_date               -> vcs_reg_date         (SSM)   (nullable, d/m/Y)
 *   reg_exp_date           -> vcs_reg_exp_date     (SSM)   (nullable, d/m/Y)
 *   kk_reg_no              -> vcs_kk_regno         (MOF)   (required)
 *   kk_expired_date        -> vcs_kk_expired_date  (MOF)   (nullable, d/m/Y)
 *   reg_no_kpm             -> vcs_reg_no_kpm       (MOTAC) (nullable)
 *   reg_date_kpm           -> vcs_reg_date_kpm     (MOTAC) (nullable, d/m/Y)
 *   reg_expdate_kpm        -> vcs_reg_expdate_kpm  (MOTAC) (nullable, d/m/Y)
 *   ros_no                 -> vcs_ros_no                   (required)
 *   name_application       -> vcs_extended_field.vcs_name_application
 *   tel_no_application     -> vcs_extended_field.vcs_tel_no_application
 *
 * Date fields use the legacy `d/m/Y` wire format (e.g. `27/04/2026`)
 * because both the read endpoint and the legacy frontend serialise
 * dates that way. The controller converts to `Y-m-d` before writing.
 *
 * `vcs_vendor_code`, `vcs_id`, `vcs_vendor_status`, `vcs_unv_reg_date`,
 * `vcs_unv_req_exp_date`, `vcs_temp_code` are **deliberately not
 * accepted here** — those are owned by the renewal/approval workflow
 * (Phase 2c) and the resolver, never the user-facing form.
 */
class UpdateVendorPortalProfileRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dateRule = ['nullable', 'string', 'regex:#^\d{2}/\d{2}/\d{4}$#'];

        return [
            'vendor_name' => ['required', 'string', 'max:255', 'min:1'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'tel_no' => ['required', 'string', 'max:50'],
            'fax_no' => ['nullable', 'string', 'max:50'],
            'bumi_status' => ['required', 'string', 'max:50'],
            'contact_person' => ['required', 'string', 'max:255'],

            'is_creditor' => ['nullable', 'string', 'in:Y,N'],
            'is_debtor' => ['nullable', 'string', 'in:Y,N'],

            'tax_reg_no' => ['nullable', 'string', 'max:50'],
            'epf_no' => ['nullable', 'string', 'max:50'],
            'socso_no' => ['nullable', 'string', 'max:50'],
            'biller_code' => ['nullable', 'string', 'max:50'],
            'ic_no' => ['nullable', 'string', 'max:50'],

            'company_category' => ['nullable', 'string', 'max:100'],
            'authorize_capital' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.99'],
            'paid_up_capital' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.99'],

            'registration_no' => ['nullable', 'string', 'max:100'],
            'reg_date' => $dateRule,
            'reg_exp_date' => $dateRule,
            'kk_reg_no' => ['required', 'string', 'max:100'],
            'kk_expired_date' => $dateRule,

            'reg_no_kpm' => ['nullable', 'string', 'max:100'],
            'reg_date_kpm' => $dateRule,
            'reg_expdate_kpm' => $dateRule,

            'ros_no' => ['required', 'string', 'max:100'],

            'name_application' => ['nullable', 'string', 'max:255'],
            'tel_no_application' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'reg_date.regex' => 'Registration Date (SSM) must be in d/m/Y format.',
            'reg_exp_date.regex' => 'Registration Expiry Date (SSM) must be in d/m/Y format.',
            'kk_expired_date.regex' => 'Registration Expiry Date (MOF) must be in d/m/Y format.',
            'reg_date_kpm.regex' => 'Registration Date (MOTAC) must be in d/m/Y format.',
            'reg_expdate_kpm.regex' => 'Registration Expired Date (MOTAC) must be in d/m/Y format.',
        ];
    }
}
