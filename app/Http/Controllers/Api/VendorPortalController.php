<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVendorPortalProfileRequest;
use App\Http\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vendor Portal > Vendor Portal (PAGEID 1622 / MENUID 1961).
 *
 * Source: legacy FIMS BL `NF_BL_PURCHASING_PORTAL_VENDOR` plus the onload
 * trigger `NF_JS_PURCHASING_PORTAL_VENDOR`. The legacy page is a
 * vendor-renewal application with a master form, seven sub-table
 * datatables (Category / Account / Address / Jobscope / SSM / MOF /
 * Other), a Payment datatable (tourism only), an Upload Document
 * dropzone and a final submit. Renewal flow stages every change in
 * `temp_vend_*` tables until approval.
 *
 * # Scope of this controller
 *
 * Phase 2a (this commit) ships the editable master form: a vendor (or
 * audit-logged admin override) may save the legacy `Application
 * Information` + `Vendor Portal` + `Vendor Registration Detail`
 * sections via `PUT /profile`. Writes hit the live
 * `vend_customer_supplier` row in place — mirroring the legacy
 * `?detail_process=1` direct-edit branch — and merge the application
 * header fields into `vcs_extended_field` JSON so the renewal-flow JSON
 * keys (`vcs_vendor_code_ori`) survive untouched.
 *
 * Reads:
 *   - Master record from `vend_customer_supplier`
 *   - Seven sub-tables from the live `vend_*` / `vendor_*` tables
 *
 * Writes (Phase 2a):
 *   - `PUT /profile`  — direct edit of the live row
 *   - `GET /lookups`  — taraf, company category, vendor status, Y/N
 *
 * The full renewal/staging workflow (legacy `addDetail`,
 * `category_process`, `account_process`, `address_process`,
 * `jobscope_process`, `ssm_process`, `mof_process`, `other_process`,
 * delete actions, document upload, sequence number generation,
 * `temp_vend_*` staging, final `submit`) — and the sub-table modal CRUD
 * — are Phase 2b/2c and not implemented here yet.
 *
 * # Scoping / authentication
 *
 * Resolution mirrors `StaffProfileController::resolveStaffWithDebug()`:
 * the authenticated user is matched against `vend_customer_supplier`
 * via a sequence of identifiers (first match wins):
 *
 *   1. `?vendor_code=ABC` query-param override — only honoured for
 *      callers holding `audit.read` (operator/support gate). Lets
 *      admins preview the portal as a specific vendor without
 *      rebinding `users.name`. Mirrors the legacy `$_USER['ROLE_ID']
 *      = 'ADMIN'` shortcut.
 *   2. `users.name` -> `vcs_vendor_code` (legacy FIMS convention,
 *      same as `VendorPoStatusController` /
 *      `DebtorProfileUpdateController` / `TenderQuotationController`).
 *   3. Email local-part (`admin@example.com` -> `admin`) ->
 *      `vcs_vendor_code` (AD-style logins).
 *   4. `users.email` -> `vcs_email_address` (vendors registered with
 *      their contact email).
 *
 * If nothing matches, every endpoint returns a structured 404
 * `VENDOR_NOT_RESOLVED` payload that mirrors the `STAFF_NOT_RESOLVED`
 * diagnostic envelope used by `StaffProfileController` so the frontend
 * can display a clear mismatch panel with all attempted identifiers.
 */
class VendorPortalController extends Controller
{
    use ApiResponse;

    private const CONN = 'mysql_secondary';

    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * GET /api/portal/vendor/profile
     *
     * Live vendor master record. Mirrors the legacy renew-mode SELECT in
     * `NF_BL_PURCHASING_PORTAL_VENDOR` (?editDetail_actual=1) when
     * `mode=renew` — i.e. reading from `vend_customer_supplier`.
     */
    public function profile(Request $request): JsonResponse
    {
        $resolved = $this->resolveVendorWithDebug($request);
        if ($resolved['vendorCode'] === null) {
            return $this->vendorNotResolved($resolved['debug']);
        }
        $vendorCode = $resolved['vendorCode'];

        $payload = $this->buildProfilePayload($vendorCode);
        if ($payload === null) {
            // Race: row deleted between resolve + select. Re-emit the
            // diagnostic envelope but also record the code we resolved
            // to so the operator sees it disappeared mid-request.
            $debug = $resolved['debug'];
            $debug['tried'] = ($debug['tried'] ?? []) + ['resolvedVendorCode' => $vendorCode];

            return $this->vendorNotResolved($debug);
        }

        return $this->sendOk($payload);
    }

    /**
     * PUT /api/portal/vendor/profile
     *
     * Save the master Vendor Portal form (components 5257 + 4737 + 4738
     * on the legacy page). Mirrors the legacy direct-edit branch
     * `?detail_process=1` in `NF_BL_PURCHASING_PORTAL_VENDOR` (UPDATE on
     * `vend_customer_supplier` keyed by `vcs_id`). The renewal path that
     * stages into `temp_vend_customer_supplier` is Phase 2c — this
     * endpoint always writes to the live row.
     *
     * Auth model:
     *  - The resolved vendor (matched by `users.name` /
     *    email-local-part / `users.email`) may save their own row.
     *  - Operators with `audit.read` may pass `?vendor_code=ABC` to save
     *    on behalf of another vendor. Such writes are recorded in the
     *    `audit_logs` table with `action = 'vendor_portal.profile.updated'`,
     *    `auditable_type = vend_customer_supplier`, `auditable_id =
     *    vcs_id`, plus the before/after diff so the override is
     *    auditable end-to-end.
     */
    public function update(UpdateVendorPortalProfileRequest $request): JsonResponse
    {
        $resolved = $this->resolveVendorWithDebug($request);
        if ($resolved['vendorCode'] === null) {
            return $this->vendorNotResolved($resolved['debug']);
        }
        $vendorCode = $resolved['vendorCode'];
        $isOverride = (bool) ($resolved['debug']['override'] ?? false);

        $existing = DB::connection(self::CONN)
            ->table('vend_customer_supplier')
            ->where('vcs_vendor_code', $vendorCode)
            ->first();

        if ($existing === null) {
            $debug = $resolved['debug'];
            $debug['tried'] = ($debug['tried'] ?? []) + ['resolvedVendorCode' => $vendorCode];

            return $this->vendorNotResolved($debug);
        }

        $data = $request->validated();
        $username = $request->user()?->name ?? $vendorCode;

        // Build the writable column map. Date fields convert d/m/Y to
        // Y-m-d; nullable string fields normalise '' -> null. The
        // CamelCaseMiddleware has already snake-cased the inbound keys.
        $update = [
            'vcs_vendor_name' => $this->trimOrNull($data['vendor_name'] ?? null),
            'vcs_email_address' => $this->trimOrNull($data['email'] ?? null),
            'vcs_tel_no' => $this->trimOrNull($data['tel_no'] ?? null),
            'vcs_fax_no' => $this->trimOrNull($data['fax_no'] ?? null),
            'vcs_bumi_status' => $this->trimOrNull($data['bumi_status'] ?? null),
            'vcs_contact_person' => $this->trimOrNull($data['contact_person'] ?? null),
            'vcs_iscreditor' => $this->trimOrNull($data['is_creditor'] ?? null),
            'vcs_isdebtor' => $this->trimOrNull($data['is_debtor'] ?? null),
            'vcs_tax_regno' => $this->trimOrNull($data['tax_reg_no'] ?? null),
            'vcs_epf_no' => $this->trimOrNull($data['epf_no'] ?? null),
            'vcs_socso_no' => $this->trimOrNull($data['socso_no'] ?? null),
            'vcs_biller_code' => $this->trimOrNull($data['biller_code'] ?? null),
            'vcs_ic_no' => $this->trimOrNull($data['ic_no'] ?? null),
            'vcs_company_category' => $this->trimOrNull($data['company_category'] ?? null),
            'vcs_authorize_capital' => isset($data['authorize_capital']) && $data['authorize_capital'] !== '' ? (float) $data['authorize_capital'] : null,
            'vcs_paid_up_capital' => isset($data['paid_up_capital']) && $data['paid_up_capital'] !== '' ? (float) $data['paid_up_capital'] : null,
            'vcs_registration_no' => $this->trimOrNull($data['registration_no'] ?? null),
            'vcs_reg_date' => $this->dmyToIso($data['reg_date'] ?? null),
            'vcs_reg_exp_date' => $this->dmyToIso($data['reg_exp_date'] ?? null),
            'vcs_kk_regno' => $this->trimOrNull($data['kk_reg_no'] ?? null),
            'vcs_kk_expired_date' => $this->dmyToIso($data['kk_expired_date'] ?? null),
            'vcs_reg_no_kpm' => $this->trimOrNull($data['reg_no_kpm'] ?? null),
            'vcs_reg_date_kpm' => $this->dmyToIso($data['reg_date_kpm'] ?? null),
            'vcs_reg_expdate_kpm' => $this->dmyToIso($data['reg_expdate_kpm'] ?? null),
            'vcs_ros_no' => $this->trimOrNull($data['ros_no'] ?? null),
            'updateddate' => DB::raw('NOW()'),
            'updatedby' => $username,
        ];

        // Merge the application-info JSON fields into vcs_extended_field
        // using JSON_SET so other JSON keys (e.g. vcs_vendor_code_ori
        // set by the renewal flow) survive the update. Pass values via
        // bindings to stay safe from SQL injection.
        $jsonBindings = [];
        $jsonExpr = 'COALESCE(vcs_extended_field, JSON_OBJECT())';
        if (array_key_exists('name_application', $data)) {
            $jsonExpr = "JSON_SET($jsonExpr, '$.vcs_name_application', ?)";
            $jsonBindings[] = $this->trimOrNull($data['name_application']);
        }
        if (array_key_exists('tel_no_application', $data)) {
            $jsonExpr = "JSON_SET($jsonExpr, '$.vcs_tel_no_application', ?)";
            $jsonBindings[] = $this->trimOrNull($data['tel_no_application']);
        }
        $update['vcs_extended_field'] = DB::raw('('.$jsonExpr.')');

        DB::connection(self::CONN)->transaction(function () use ($vendorCode, $update, $jsonBindings): void {
            // Manual UPDATE so we can interleave the JSON_SET bindings
            // for vcs_extended_field with the rest of the column
            // bindings in the right order. Eloquent / fluent update does
            // not preserve ordering of DB::raw bindings reliably here.
            $sets = [];
            $bindings = [];
            foreach ($update as $col => $val) {
                if ($col === 'vcs_extended_field') {
                    $sets[] = "vcs_extended_field = $jsonExpr";
                    foreach ($jsonBindings as $b) {
                        $bindings[] = $b;
                    }

                    continue;
                }
                if ($val instanceof Expression) {
                    $sets[] = "$col = ".$val->getValue(DB::connection(self::CONN)->getQueryGrammar());

                    continue;
                }
                $sets[] = "$col = ?";
                $bindings[] = $val;
            }
            $bindings[] = $vendorCode;
            DB::connection(self::CONN)->update(
                'UPDATE vend_customer_supplier SET '.implode(', ', $sets).' WHERE vcs_vendor_code = ?',
                $bindings
            );
        });

        // Audit-log admin override writes so we can trace who edited
        // whom. The Auditable trait does not fire for raw DB::update
        // calls (no model event), so log explicitly.
        if ($isOverride) {
            $vcsId = $existing->vcs_id ?? null;
            $this->audit->log(
                'vendor_portal.profile.updated',
                $request->user(),
                'vend_customer_supplier',
                $vcsId !== null ? (string) $vcsId : null,
                (array) $existing,
                array_merge(
                    array_filter(
                        $update,
                        static fn ($v) => ! ($v instanceof Expression)
                    ),
                    ['vcs_vendor_code' => $vendorCode, 'override' => true]
                )
            );
        }

        $payload = $this->buildProfilePayload($vendorCode);
        if ($payload === null) {
            $debug = $resolved['debug'];
            $debug['tried'] = ($debug['tried'] ?? []) + ['resolvedVendorCode' => $vendorCode];

            return $this->vendorNotResolved($debug);
        }

        return $this->sendOk($payload);
    }

    /**
     * GET /api/portal/vendor/lookups
     *
     * Returns the dropdown option sets the Vendor Portal master form
     * needs (Taraf, Company Category, Vendor Status, Y/N for
     * creditor/debtor). Mirrors the inline `Form_Item_lookup_query`
     * fragments embedded on PAGEID 1622 components 4737 + 4738.
     *
     * Static option sets (Y/N, vendor status, company category) come
     * from the legacy hard-coded `UNION` queries on the page metadata
     * and are returned as constants so the UI does not pay a DB round
     * trip for them. The Taraf list is sourced from
     * `fims_usr.lookup_details` keyed by `lma_code_name = 'TARAF_VENDOR'`.
     */
    public function lookups(Request $request): JsonResponse
    {
        $resolved = $this->resolveVendorWithDebug($request);
        if ($resolved['vendorCode'] === null) {
            return $this->vendorNotResolved($resolved['debug']);
        }

        $taraf = DB::connection(self::CONN)
            ->table('fims_usr.lookup_details')
            ->where('lma_code_name', 'TARAF_VENDOR')
            ->orderBy('lde_value')
            ->select(['lde_value as value', 'lde_description as label'])
            ->get()
            ->map(fn ($r) => [
                'value' => (string) $r->value,
                'label' => mb_strtoupper((string) ($r->label ?? $r->value)),
            ])
            ->all();

        return $this->sendOk([
            'taraf' => $taraf,
            'companyCategory' => [
                ['value' => 'INDIVIDUAL', 'label' => 'INDIVIDUAL'],
                ['value' => 'REGISTERED', 'label' => 'REGISTERED'],
                ['value' => 'UNQUALIFIED TO REGISTERED', 'label' => 'UNQUALIFIED TO REGISTERED'],
            ],
            'vendorStatus' => [
                ['value' => '1', 'label' => 'ACTIVE'],
                ['value' => '0', 'label' => 'INACTIVE'],
            ],
            'creditorDebtor' => [
                ['value' => 'Y', 'label' => 'YES'],
                ['value' => 'N', 'label' => 'NO'],
            ],
        ]);
    }

    /**
     * GET /api/portal/vendor/categories
     *
     * Mirrors legacy ?Category_dt=1 SELECT but against the live
     * `vend_category` table (legacy renew flow drains this into
     * `temp_vend_category` for staging).
     */
    public function categories(Request $request): JsonResponse
    {
        $resolved = $this->resolveVendorWithDebug($request);
        if ($resolved['vendorCode'] === null) {
            return $this->vendorNotResolved($resolved['debug']);
        }
        $vendorCode = $resolved['vendorCode'];

        [$page, $limit] = $this->paging($request);
        $q = trim((string) $request->input('q', ''));

        $base = DB::connection(self::CONN)
            ->table('vend_category as vc')
            ->where('vc.vcs_vendor_code', $vendorCode);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(vc.vc_id, ''),
                    IFNULL(vc.vcs_vendor_code, ''),
                    IFNULL(vc.vc_category_code, ''),
                    IFNULL(DATE_FORMAT(vc.createddate, '%d/%m/%Y'), '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->leftJoin('fims_usr.lookup_details as ld', function ($j) {
                $j->on('ld.lde_value', '=', 'vc.vc_category_code')
                    ->where('ld.lma_code_name', '=', 'VENDORCATEGORY');
            })
            ->select([
                'vc.vc_id',
                'vc.vcs_vendor_code',
                'vc.vc_category_code',
                'ld.lde_description as category_desc',
                'vc.createddate',
            ])
            ->orderBy('vc.createddate', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'id' => $r->vc_id !== null ? (string) $r->vc_id : null,
            'vendorCode' => $r->vcs_vendor_code,
            'categoryCode' => $r->vc_category_code,
            'categoryLabel' => $r->category_desc !== null
                ? trim((string) $r->vc_category_code.' - '.(string) $r->category_desc)
                : $r->vc_category_code,
            'createdDate' => $this->formatDmy($r->createddate),
        ])->all();

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    /**
     * GET /api/portal/vendor/accounts
     *
     * Mirrors ?Account_dt=1 / ?account=1 against live
     * `vend_supplier_account`.
     */
    public function accounts(Request $request): JsonResponse
    {
        $resolved = $this->resolveVendorWithDebug($request);
        if ($resolved['vendorCode'] === null) {
            return $this->vendorNotResolved($resolved['debug']);
        }
        $vendorCode = $resolved['vendorCode'];

        [$page, $limit] = $this->paging($request);
        $q = trim((string) $request->input('q', ''));

        $base = DB::connection(self::CONN)
            ->table('vend_supplier_account as vsa')
            ->where('vsa.vcs_vendor_code', $vendorCode);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(vsa.vsa_id, ''),
                    IFNULL(vsa.vcs_vendor_code, ''),
                    IFNULL(vsa.vsa_vendor_bank, ''),
                    IFNULL(vsa.vsa_bank_accno, ''),
                    IFNULL(vsa.vsa_status, ''),
                    IFNULL(DATE_FORMAT(vsa.createddate, '%d/%m/%Y'), '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->leftJoin('bank_master as bnm', 'bnm.bnm_bank_code', '=', 'vsa.vsa_vendor_bank')
            ->select([
                'vsa.vsa_id',
                'vsa.vcs_vendor_code',
                'vsa.vsa_vendor_bank',
                'bnm.bnm_bank_desc',
                'vsa.vsa_bank_accno',
                'vsa.vsa_status',
                'vsa.createddate',
            ])
            ->orderBy('vsa.createddate', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'id' => $r->vsa_id !== null ? (string) $r->vsa_id : null,
            'vendorCode' => $r->vcs_vendor_code,
            'bankCode' => $r->vsa_vendor_bank,
            'bankName' => $r->bnm_bank_desc ?? $r->vsa_vendor_bank,
            'bankAccountNo' => $r->vsa_bank_accno,
            'status' => $r->vsa_status === '1' || $r->vsa_status === 1 ? 'ACTIVE' : 'INACTIVE',
            'createdDate' => $this->formatDmy($r->createddate),
        ])->all();

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    /**
     * GET /api/portal/vendor/addresses
     *
     * Mirrors ?Address_dt=1 / ?address=1 against live `vendor_address`.
     */
    public function addresses(Request $request): JsonResponse
    {
        $resolved = $this->resolveVendorWithDebug($request);
        if ($resolved['vendorCode'] === null) {
            return $this->vendorNotResolved($resolved['debug']);
        }
        $vendorCode = $resolved['vendorCode'];

        [$page, $limit] = $this->paging($request);
        $q = trim((string) $request->input('q', ''));

        $base = DB::connection(self::CONN)
            ->table('vendor_address as va')
            ->where('va.vcs_vendor_code', $vendorCode);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(va.vdd_address_id, ''),
                    IFNULL(va.vdd_address_type, ''),
                    IFNULL(va.vdd_address1, ''),
                    IFNULL(va.vdd_address2, ''),
                    IFNULL(va.vdd_address3, ''),
                    IFNULL(va.vdd_pcode, ''),
                    IFNULL(va.vdd_city, ''),
                    IFNULL(va.vdd_state, ''),
                    IFNULL(va.vdd_country, '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->leftJoin('fims_usr.lookup_details as ld', function ($j) {
                $j->on('ld.lde_value', '=', 'va.vdd_address_type')
                    ->where('ld.lma_code_name', '=', 'ADDRESS_TYPE');
            })
            ->select([
                'va.vdd_address_id',
                'va.vcs_vendor_code',
                'va.vdd_address_type',
                'ld.lde_description2 as address_type_label',
                'va.vdd_address1',
                'va.vdd_address2',
                'va.vdd_address3',
                'va.vdd_pcode',
                'va.vdd_city',
                'va.vdd_state',
                'va.vdd_country',
                'va.createddate',
            ])
            ->orderBy('va.createddate', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'id' => $r->vdd_address_id !== null ? (string) $r->vdd_address_id : null,
            'vendorCode' => $r->vcs_vendor_code,
            'addressType' => $r->vdd_address_type,
            'addressTypeLabel' => $r->address_type_label ?? $r->vdd_address_type,
            'address1' => $r->vdd_address1,
            'address2' => $r->vdd_address2,
            'address3' => $r->vdd_address3,
            'postcode' => $r->vdd_pcode,
            'city' => $r->vdd_city,
            'state' => $r->vdd_state,
            'country' => $r->vdd_country,
            'createdDate' => $this->formatDmy($r->createddate),
        ])->all();

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    /**
     * GET /api/portal/vendor/jobscopes
     *
     * Mirrors ?jobscope=1 / ?Jobscope_dt=1 — joins `vendor_jobscope` to
     * `jobscope` to render `code - name` plus the category column.
     */
    public function jobscopes(Request $request): JsonResponse
    {
        $resolved = $this->resolveVendorWithDebug($request);
        if ($resolved['vendorCode'] === null) {
            return $this->vendorNotResolved($resolved['debug']);
        }
        $vendorCode = $resolved['vendorCode'];

        [$page, $limit] = $this->paging($request);
        $q = trim((string) $request->input('q', ''));

        $base = DB::connection(self::CONN)
            ->table('vendor_jobscope as vj')
            ->leftJoin('jobscope as j', function ($jn) {
                $jn->on('j.jbs_jobscope_code', '=', 'vj.jbs_jobscope_code')
                    ->on('j.jbc_category', '=', 'vj.jbc_category');
            })
            ->where('vj.vcs_vendor_code', $vendorCode);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(vj.vjb_id, ''),
                    IFNULL(vj.vcs_vendor_code, ''),
                    IFNULL(vj.jbs_jobscope_code, ''),
                    IFNULL(vj.jbc_category, ''),
                    IFNULL(j.jbs_job_name, '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->select([
                'vj.vjb_id',
                'vj.vcs_vendor_code',
                'vj.jbs_jobscope_code',
                'j.jbs_job_name',
                'vj.jbc_category',
                'vj.createddate',
            ])
            ->orderBy('vj.createddate', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'id' => $r->vjb_id !== null ? (string) $r->vjb_id : null,
            'vendorCode' => $r->vcs_vendor_code,
            'jobscopeCode' => $r->jbs_jobscope_code,
            'jobscopeLabel' => $r->jbs_job_name !== null
                ? trim((string) $r->jbs_jobscope_code.' - '.(string) $r->jbs_job_name)
                : $r->jbs_jobscope_code,
            'category' => $r->jbc_category,
            'createdDate' => $this->formatDmy($r->createddate),
        ])->all();

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    /**
     * GET /api/portal/vendor/ssm-licences
     *
     * Mirrors ?SSM_dt=1 / ?SSM=1 against live `vend_licence_ssm`.
     */
    public function ssmLicences(Request $request): JsonResponse
    {
        return $this->licenceList(
            $request,
            'vend_licence_ssm',
            'vls_id',
            'vls_licence_code',
            'vls_extended_field',
            'LICENCE_SSM',
            'vls_licence_desc',
        );
    }

    /**
     * GET /api/portal/vendor/mof-licences
     *
     * Mirrors ?MOF_dt=1 / ?MOF=1 against live `vend_licence_mof`.
     */
    public function mofLicences(Request $request): JsonResponse
    {
        return $this->licenceList(
            $request,
            'vend_licence_mof',
            'vlm_id',
            'vlm_licence_code',
            'vlm_extended_field',
            'LICENCE_MOF',
            'vlm_licence_desc',
        );
    }

    /**
     * GET /api/portal/vendor/other-licences
     *
     * Mirrors ?OTHER_dt=1 / ?OTHER=1 against live `vend_licence_others`
     * (no lookup_details lookup — description is stored inline).
     */
    public function otherLicences(Request $request): JsonResponse
    {
        $resolved = $this->resolveVendorWithDebug($request);
        if ($resolved['vendorCode'] === null) {
            return $this->vendorNotResolved($resolved['debug']);
        }
        $vendorCode = $resolved['vendorCode'];

        [$page, $limit] = $this->paging($request);
        $q = trim((string) $request->input('q', ''));

        $base = DB::connection(self::CONN)
            ->table('vend_licence_others as vlo')
            ->where('vlo.vcs_vendor_code', $vendorCode);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(vlo.vlo_id, ''),
                    IFNULL(vlo.vcs_vendor_code, ''),
                    IFNULL(vlo.vlo_licence_code, ''),
                    IFNULL(vlo.vlo_licence_desc, '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->select([
                'vlo.vlo_id',
                'vlo.vcs_vendor_code',
                'vlo.vlo_licence_code',
                'vlo.vlo_licence_desc',
                'vlo.createddate',
            ])
            ->orderBy('vlo.createddate', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'id' => $r->vlo_id !== null ? (string) $r->vlo_id : null,
            'vendorCode' => $r->vcs_vendor_code,
            'licenceCode' => $r->vlo_licence_code,
            'licenceDesc' => $r->vlo_licence_desc,
            'createdDate' => $this->formatDmy($r->createddate),
        ])->all();

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    /**
     * Shared SSM/MOF licence list builder. Both tables share the same
     * shape — id column, licence code column, json extended field with
     * the inline description, and a lookup_details lookup keyed by
     * lma_code_name.
     */
    private function licenceList(
        Request $request,
        string $table,
        string $idCol,
        string $codeCol,
        string $extCol,
        string $lookupCode,
        string $descKey,
    ): JsonResponse {
        $resolved = $this->resolveVendorWithDebug($request);
        if ($resolved['vendorCode'] === null) {
            return $this->vendorNotResolved($resolved['debug']);
        }
        $vendorCode = $resolved['vendorCode'];

        [$page, $limit] = $this->paging($request);
        $q = trim((string) $request->input('q', ''));

        $base = DB::connection(self::CONN)
            ->table("$table as t")
            ->where('t.vcs_vendor_code', $vendorCode);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(t.$idCol, ''),
                    IFNULL(t.vcs_vendor_code, ''),
                    IFNULL(t.$codeCol, '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->leftJoin('fims_usr.lookup_details as ld', function ($j) use ($codeCol, $lookupCode) {
                $j->on('ld.lde_value', '=', "t.$codeCol")
                    ->where('ld.lma_code_name', '=', $lookupCode);
            })
            ->select([
                "t.$idCol as id_col",
                't.vcs_vendor_code',
                "t.$codeCol as code_col",
                'ld.lde_description as code_desc',
                DB::raw("t.$extCol->>'$.\"$descKey\"' AS inline_desc"),
                't.createddate',
            ])
            ->orderBy('t.createddate', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'id' => $r->id_col !== null ? (string) $r->id_col : null,
            'vendorCode' => $r->vcs_vendor_code,
            'licenceCode' => $r->code_col,
            'licenceLabel' => $r->code_desc !== null
                ? trim((string) $r->code_col.' - '.(string) $r->code_desc)
                : $r->code_col,
            'licenceDesc' => $r->inline_desc,
            'createdDate' => $this->formatDmy($r->createddate),
        ])->all();

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    /**
     * Read the live `vend_customer_supplier` row for the given vendor
     * and shape it into the camelCase payload the frontend consumes.
     * Returns `null` when no row exists (lets the caller emit a
     * resolution-error envelope with the resolved code threaded in).
     *
     * Centralising the SELECT + map keeps `profile()` and `update()`
     * (which both need to return the same shape after their respective
     * read / write) consistent.
     *
     * @return array<string, mixed>|null
     */
    private function buildProfilePayload(string $vendorCode): ?array
    {
        $row = DB::connection(self::CONN)
            ->table('vend_customer_supplier')
            ->where('vcs_vendor_code', $vendorCode)
            ->select([
                'vcs_id',
                'vcs_vendor_code',
                'vcs_vendor_name',
                'vcs_address',
                'vcs_addr1',
                'vcs_addr2',
                'vcs_addr3',
                'vcs_postcode',
                'vcs_state',
                'cny_country_code',
                'vcs_registration_no',
                'vcs_reg_date',
                'vcs_reg_exp_date',
                'vcs_kk_regno',
                'vcs_kk_expired_date',
                'vcs_tax_regno',
                'vcs_tel_no',
                'vcs_fax_no',
                'vcs_contact_person',
                'vcs_vendor_status',
                'vcs_iscreditor',
                'vcs_isdebtor',
                'vcs_bumi_status',
                'vcs_company_category',
                'vcs_authorize_capital',
                'vcs_paid_up_capital',
                'vcs_email_address',
                'vcs_ic_no',
                'vcs_unv_reg_date',
                'vcs_unv_req_exp_date',
                'vcs_epf_no',
                'vcs_socso_no',
                'vcs_reg_no_kpm',
                'vcs_reg_date_kpm',
                'vcs_reg_expdate_kpm',
                'vcs_ros_no',
                'vcs_vendor_bank',
                'vcs_bank_accno',
                'vcs_biller_code',
                'vcs_temp_code',
                DB::raw("vcs_extended_field->>'$.vcs_name_application' AS name_application"),
                DB::raw("vcs_extended_field->>'$.vcs_tel_no_application' AS tel_no_application"),
                DB::raw("vcs_extended_field->>'$.vcs_vendor_code_ori' AS vcs_vendor_code_ori"),
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'vendorId' => $row->vcs_id !== null ? (string) $row->vcs_id : null,
            'vendorCode' => $row->vcs_vendor_code,
            'vendorName' => $row->vcs_vendor_name,
            'address' => $row->vcs_address,
            'address1' => $row->vcs_addr1,
            'address2' => $row->vcs_addr2,
            'address3' => $row->vcs_addr3,
            'postcode' => $row->vcs_postcode,
            'state' => $row->vcs_state,
            'countryCode' => $row->cny_country_code,
            'registrationNo' => $row->vcs_registration_no,
            'registrationDate' => $this->formatDmy($row->vcs_reg_date),
            'registrationExpiryDate' => $this->formatDmy($row->vcs_reg_exp_date),
            'kkRegNo' => $row->vcs_kk_regno,
            'kkExpiredDate' => $this->formatDmy($row->vcs_kk_expired_date),
            'taxRegNo' => $row->vcs_tax_regno,
            'telNo' => $row->vcs_tel_no,
            'faxNo' => $row->vcs_fax_no,
            'contactPerson' => $row->vcs_contact_person,
            'vendorStatus' => $row->vcs_vendor_status,
            'isCreditor' => $row->vcs_iscreditor,
            'isDebtor' => $row->vcs_isdebtor,
            'bumiStatus' => $row->vcs_bumi_status,
            'companyCategory' => $row->vcs_company_category,
            'authorizeCapital' => $this->floatOrNull($row->vcs_authorize_capital),
            'paidUpCapital' => $this->floatOrNull($row->vcs_paid_up_capital),
            'emailAddress' => $row->vcs_email_address,
            'icNo' => $row->vcs_ic_no,
            'unvRegDate' => $this->formatDmy($row->vcs_unv_reg_date),
            'unvReqExpDate' => $this->formatDmy($row->vcs_unv_req_exp_date),
            'epfNo' => $row->vcs_epf_no,
            'socsoNo' => $row->vcs_socso_no,
            'regNoKpm' => $row->vcs_reg_no_kpm,
            'regDateKpm' => $this->formatDmy($row->vcs_reg_date_kpm),
            'regExpDateKpm' => $this->formatDmy($row->vcs_reg_expdate_kpm),
            'rosNo' => $row->vcs_ros_no,
            'vendorBank' => $row->vcs_vendor_bank,
            'bankAccountNo' => $row->vcs_bank_accno,
            'billerCode' => $row->vcs_biller_code,
            'tempCode' => $row->vcs_temp_code,
            'nameApplication' => $row->name_application,
            'telNoApplication' => $row->tel_no_application,
            'vendorCodeOri' => $row->vcs_vendor_code_ori,
        ];
    }

    /**
     * Convert a `d/m/Y` legacy wire date string to ISO `Y-m-d`. Returns
     * null for empty / null / unparseable inputs (the column is then
     * left as NULL on the row).
     */
    private function dmyToIso(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }

    private function trimOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function paging(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));

        return [$page, $limit];
    }

    /**
     * @return array{page:int, limit:int, total:int, totalPages:int}
     */
    private function meta(int $page, int $limit, int $total): array
    {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ];
    }

    private function formatDmy(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Already formatted as dd/mm/yyyy?
        if (preg_match('#^\\d{2}/\\d{2}/\\d{4}#', $value)) {
            return $value;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }

        return date('d/m/Y', $ts);
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Resolve the authenticated user to a `vcs_vendor_code` and produce
     * a diagnostic payload describing every identifier attempted. Mirrors
     * `StaffProfileController::resolveStaffWithDebug()`.
     *
     * Resolution order (first match wins):
     *  1. `?vendor_code=ABC` query-param override — only honoured when
     *     the caller holds the `audit.read` permission. Lets operators /
     *     support staff preview the portal as a specific vendor without
     *     re-binding `users.name`. Mirrors the legacy `$_USER['ROLE_ID']
     *     = 'ADMIN'` shortcut.
     *  2. `users.name` against `vcs_vendor_code` (legacy FIMS convention:
     *     username == vendor code). This is the primary path used by
     *     real vendor logins.
     *  3. Email local-part (`admin@example.com` -> `admin`) against
     *     `vcs_vendor_code`. Catches AD-style logins where the username
     *     is the email prefix.
     *  4. `users.email` against `vcs_email_address`. Catches vendors
     *     whose Kerisi account was created with their contact email
     *     instead of the legacy code.
     *
     * Every attempted identifier is recorded in `debug.tried` so
     * `vendorNotResolved()` can render the full audit trail. Returns
     * `['vendorCode' => null, 'debug' => [...]]` on miss.
     *
     * @return array{vendorCode: ?string, debug: array<string, mixed>}
     */
    private function resolveVendorWithDebug(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return ['vendorCode' => null, 'debug' => []];
        }

        $name = trim((string) ($user->name ?? ''));
        $email = trim((string) ($user->email ?? ''));
        $emailLocal = $email !== '' && str_contains($email, '@')
            ? explode('@', $email, 2)[0]
            : '';

        $tried = [];

        // 1. Operator/admin "view-as" override.
        $override = trim((string) $request->input('vendor_code', ''));
        if ($override !== '' && $this->canImpersonate($user)) {
            $tried['overrideVendorCode'] = $override;
            $exists = DB::connection(self::CONN)
                ->table('vend_customer_supplier')
                ->where('vcs_vendor_code', $override)
                ->exists();
            if ($exists) {
                return [
                    'vendorCode' => $override,
                    'debug' => [
                        'auth_email' => $email !== '' ? $email : null,
                        'auth_name' => $name !== '' ? $name : null,
                        'tried' => $tried,
                        'override' => true,
                    ],
                ];
            }
        }

        $resolved = null;

        // 2. users.name == vcs_vendor_code.
        if ($name !== '') {
            $tried['nameAsVendorCode'] = $name;
            $resolved = DB::connection(self::CONN)
                ->table('vend_customer_supplier')
                ->where('vcs_vendor_code', $name)
                ->value('vcs_vendor_code');
        }

        // 3. Email local-part as vendor code.
        if ($resolved === null && $emailLocal !== '' && $emailLocal !== $name) {
            $tried['emailLocalPartAsVendorCode'] = $emailLocal;
            $resolved = DB::connection(self::CONN)
                ->table('vend_customer_supplier')
                ->where('vcs_vendor_code', $emailLocal)
                ->value('vcs_vendor_code');
        }

        // 4. users.email == vcs_email_address.
        if ($resolved === null && $email !== '') {
            $tried['emailAsVendorEmailAddress'] = $email;
            $resolved = DB::connection(self::CONN)
                ->table('vend_customer_supplier')
                ->where('vcs_email_address', $email)
                ->value('vcs_vendor_code');
        }

        return [
            'vendorCode' => $resolved !== null ? (string) $resolved : null,
            'debug' => [
                'auth_email' => $email !== '' ? $email : null,
                'auth_name' => $name !== '' ? $name : null,
                'tried' => $tried,
            ],
        ];
    }

    /**
     * Whether the authenticated user is allowed to use the `?vendor_code=`
     * impersonation override. We reuse the existing `audit.read`
     * permission as the operator/support gate — it's already granted to
     * the admin role and is the closest existing analogue to "can read
     * data scoped to another principal". A dedicated permission can be
     * introduced later if finer granularity is needed.
     */
    private function canImpersonate(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission('audit.read');
    }

    /**
     * Standard 404 envelope for the "no vendor matched" case. Mirrors the
     * `STAFF_NOT_RESOLVED` diagnostic envelope used by
     * `StaffProfileController` (shape: `{ message, auth, tried }`) so
     * the frontend can render an inline resolution panel rather than a
     * generic toast.
     *
     * @param  array<string, mixed>  $debug
     */
    private function vendorNotResolved(array $debug): JsonResponse
    {
        return $this->sendError(
            404,
            'VENDOR_NOT_RESOLVED',
            'Authenticated user could not be matched to a vendor record.',
            [
                'message' => 'No row in `vend_customer_supplier` matched the authenticated user. Ensure the logged-in account has a `name` matching `vend_customer_supplier.vcs_vendor_code` (legacy FIMS convention: username == vendor code), or an `email` matching `vcs_email_address`. Operators with `audit.read` may pass `?vendor_code=ABC` to preview a specific vendor.',
                'auth' => [
                    'email' => $debug['auth_email'] ?? null,
                    'name' => $debug['auth_name'] ?? null,
                ],
                'tried' => $debug['tried'] ?? [],
            ]
        );
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
