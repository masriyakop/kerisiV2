<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStaffProfileAddressRequest;
use App\Http\Requests\UpdateStaffProfileMaritalStatusRequest;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Portal > Staff Profile (PAGEID 1581 / MENUID 1914).
 *
 * Source: legacy FIMS BL `API_PORTAL_SALARYPROFILEINFORMATION`. The
 * legacy page is a self-service portal that mounts 9 components against
 * a single PHP endpoint with `$_GET` flag dispatch. We split the 1914-
 * scoped operations into REST endpoints (the spouse / child detail
 * forms, MENUIDs 3301 / 3305, are out of scope for this migration —
 * see "Out of scope" below):
 *
 *   GET  /portal/staff-profile                                  → master
 *   GET  /portal/staff-profile/options                          → dropdown lookups
 *   GET  /portal/staff-profile/address                          → detailAddress
 *   PUT  /portal/staff-profile/address                          → saveAddress
 *   PUT  /portal/staff-profile/marital-status                   → updateMaritalStatus
 *   GET  /portal/staff-profile/children                         → all_children
 *   GET  /portal/staff-profile/spouses                          → family_spouse
 *   GET  /portal/staff-profile/spouses/{seq}/children           → family_children
 *
 * Scoping: the authenticated user resolves to a `staff` row via
 * `stf_ad_username` / `stf_email_addr` (same convention as
 * AuthorizedReceiptingFormController::currentStaff). All queries are
 * forced to that resolved `stf_staff_id`; the legacy `staff_id` /
 * `stf_staff_id` query parameters are ignored on purpose so a logged-in
 * user cannot read or mutate another staff's records.
 *
 * Out of scope (not migrated here):
 *   - MENUID 3301 (spouse detail form: ?save=1, ?update=1,
 *     ?detailSpouse=1, ?autoSuggest=1)
 *   - MENUID 3305 (children detail form: ?saveChildren=1,
 *     ?updateChildren=1, ?detailChildren=1)
 *
 * The 1914 list datatables expose those records read-only; the inline
 * edit / "New" buttons are not wired in this migration. When MENUIDs
 * 3301 / 3305 are migrated they should add their own routes (the
 * sub-page contract is already documented in /tmp/menu_1914_bl.php).
 */
class StaffProfileController extends Controller
{
    use ApiResponse;

    private const CONN = 'mysql_secondary';

    private const CHILDREN_SORTABLE = [
        'stc_child_seq',
        'stc_name',
        'stc_ic_ref_no',
        'stc_bod',
        'stc_relation',
        'age',
        'stc_level_study',
        'stc_disability_status',
        'stc_pcb_status',
        'stc_death_date',
    ];

    private const SPOUSES_SORTABLE = [
        'spo_spouse_seq',
        'spo_name',
        'spo_ic_no',
        'spo_tax_no',
        'spo_marriage_date',
        'spo_divorce_date',
        'spo_death_date',
    ];

    /**
     * GET /portal/staff-profile
     *
     * Mirrors legacy `?master=1`. Returns the read-only profile card
     * (name, IC, position, PTJ, marital, salary, bank, tax, EPF/SOCSO,
     * zakat). Zakat resolution follows the legacy 3-step fallback:
     * monthly_payroll_detl_his (current month) → staff_allowance_deduction
     * (active window) → staff_allowance_deduction (open-ended).
     */
    public function master(Request $request): JsonResponse
    {
        $resolved = $this->resolveStaffWithDebug();
        $staff = $resolved['staff'];
        if ($staff === null) {
            return $this->staffNotResolvedResponse($resolved['debug']);
        }

        $row = DB::connection(self::CONN)
            ->table('staff as stf')
            ->leftJoin('staff_allowance_deduction as sad', function ($j) {
                $j->on('sad.stf_staff_id', '=', 'stf.stf_staff_id')
                    ->where('sad.ity_income_code', 'A010101')
                    ->where(function ($w) {
                        $w->whereNull('sad.spa_end_date')
                            ->orWhereRaw('sad.spa_end_date > sysdate()');
                    });
            })
            ->leftJoin('staff_salary as sal', 'stf.stf_staff_id', '=', 'sal.stf_staff_id')
            ->join('staff_service as sts', function ($j) {
                $j->on('stf.stf_staff_id', '=', 'sts.stf_staff_id')
                    ->where('sts.sts_job_flag', 1);
            })
            ->join('staff_account as sta', function ($j) {
                $j->on('stf.stf_staff_id', '=', 'sta.stf_staff_id')
                    ->where('sta.sta_salary_bank', 'Y');
            })
            ->join('service_scheme as ss', 'sts.sts_jobcode', '=', 'ss.ssc_service_code')
            ->join('lookup_details as ld', function ($j) {
                $j->on('ld.lde_value', '=', 'sts.sts_job_status')
                    ->where('ld.lma_code_name', 'STAFFJOBSTATUS');
            })
            ->where('stf.stf_staff_id', $staff->stf_staff_id)
            ->selectRaw("
                stf.stf_staff_id,
                stf.stf_ic_no,
                stf.stf_unit,
                stf.stf_telno_work,
                stf.stf_email_addr,
                stf.stf_marital_status,
                stf.stf_handphone_no,
                sal.sal_tax_group,
                ld.lde_description AS status,
                stf.stf_citizen,
                stf.stf_sal_incr_date AS stf_sal_incr_date_raw,
                sts.sts_salary_grade,
                sta.sta_acct_no                                     AS sta_acct_no_profile,
                sta.sta_acct_name                                   AS sta_acct_name_profile,
                ss.ssc_service_desc                                 AS ssc_service_desc_profile,
                stf.stf_staff_name,
                DATE_FORMAT(sts.sts_join_date, '%d/%m/%Y')          AS sts_join_date,
                CONCAT_WS(', ', stf.stf_current_address1, stf.stf_current_address2) AS stf_current_address,
                CONCAT_WS(', ', stf.stf_permanent_address1, stf.stf_permanent_address2) AS stf_permanent_address,
                FORMAT(sad.spa_amount, 2)                            AS sal_basic_salary,
                JSON_UNQUOTE(JSON_EXTRACT(stf.stf_extended_field, '$.stf_title_desc'))         AS title_desc,
                JSON_UNQUOTE(JSON_EXTRACT(stf.stf_extended_field, '$.stf_gender_desc'))        AS gender_desc,
                JSON_UNQUOTE(JSON_EXTRACT(stf.stf_extended_field, '$.stf_maritalstatus_desc')) AS maritalstatus_desc,
                JSON_UNQUOTE(JSON_EXTRACT(sal.sal_extended_field, '$.sal_taxcategory_desc'))   AS sal_tax_category,
                JSON_UNQUOTE(JSON_EXTRACT(sal.sal_extended_field, '$.sal_zakat_desc'))         AS sal_zakat_desc,
                UPPER(JSON_UNQUOTE(JSON_EXTRACT(sts.sts_extended_field, '$.sts_pensionstatus_desc'))) AS pensionstatus_desc,
                ld.lde_description AS job_status,
                (SELECT UPPER(ou.oun_desc) FROM organization_unit ou WHERE ou.oun_code = sts.sts_oun_code LIMIT 1) AS oun_desc,
                CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(sts.sts_extended_field, '$.sts_costcentre_desc')) = 'null'
                     THEN ''
                     ELSE JSON_UNQUOTE(JSON_EXTRACT(sts.sts_extended_field, '$.sts_costcentre_desc'))
                END AS ccr_costcentre_desc,
                IF(sal.sal_socso_status = 'Y', 'Yes', 'No') AS sal_socso_status,
                IF(sal.sal_epf_status   = 'Y', 'Yes', 'No') AS sal_epf_status,
                stf.isAcknowledgeMarital
            ")
            ->first();

        if (! $row) {
            return $this->sendError(404, 'STAFF_NOT_RESOLVED', 'Staff profile not found.');
        }

        $zakat = $this->resolveZakat($staff->stf_staff_id);

        $payload = (array) $row;
        $payload['stf_sal_incr_date'] = $this->formatSalIncrMonth($payload['stf_sal_incr_date_raw'] ?? null);
        unset($payload['stf_sal_incr_date_raw']);

        return $this->sendOk([
            'staff_details' => $payload,
            'zakat_amount' => $zakat['amount'],
            'zakat_period' => $zakat['period'],
        ]);
    }

    /**
     * GET /portal/staff-profile/options
     *
     * Returns the dropdown lookups consumed by the address +
     * marital-status forms. The legacy page builder injected these via
     * its own lookup_query mechanism — here we centralise them so the
     * Vue view can populate `<select>` options without re-implementing
     * the legacy lookup pipeline.
     */
    public function options(): JsonResponse
    {
        $resolved = $this->resolveStaffWithDebug();
        if ($resolved['staff'] === null) {
            return $this->staffNotResolvedResponse($resolved['debug']);
        }

        return $this->sendOk([
            'marital_status' => $this->lookupOptions('MARITALSTATUS'),
            'state' => $this->lookupOptions('STATE'),
            'country' => $this->lookupOptions('COUNTRY'),
            'address_type' => $this->lookupOptions('ADDRESSTYPE'),
        ]);
    }

    /**
     * GET /portal/staff-profile/address
     *
     * Mirrors legacy `?detailAddress=1`. Returns the most-recent
     * `staff_address` row of type 1 (current address) plus the staff's
     * handphone number. Returns null fields when no address exists yet.
     */
    public function addressShow(): JsonResponse
    {
        $resolved = $this->resolveStaffWithDebug();
        $staff = $resolved['staff'];
        if ($staff === null) {
            return $this->staffNotResolvedResponse($resolved['debug']);
        }

        $addr = DB::connection(self::CONN)
            ->table('staff_address')
            ->where('stf_staff_id', $staff->stf_staff_id)
            ->where('sa_address_type', 1)
            ->orderByDesc('createddate')
            ->select([
                'stf_staff_id',
                'sa_address_type',
                'sa_address1',
                'sa_address2',
                'sa_pcode',
                'sa_city',
                'sa_state',
                'sa_country',
                'isAcknowledgement',
            ])
            ->first();

        $handphone = DB::connection(self::CONN)
            ->table('staff')
            ->where('stf_staff_id', $staff->stf_staff_id)
            ->value('stf_handphone_no');

        return $this->sendOk([
            'stf_staff_id' => $staff->stf_staff_id,
            'sa_address_type' => $addr->sa_address_type ?? 1,
            'sa_address1' => $addr->sa_address1 ?? null,
            'sa_address2' => $addr->sa_address2 ?? null,
            'sa_pcode' => $addr->sa_pcode ?? null,
            'sa_city' => $addr->sa_city ?? null,
            'sa_state' => $addr->sa_state ?? null,
            'sa_country' => $addr->sa_country ?? null,
            'is_acknowledgement' => isset($addr->isAcknowledgement) ? (int) $addr->isAcknowledgement : 0,
            'stf_handphone_no' => $handphone !== null ? (string) $handphone : null,
            'has_address' => $addr !== null,
        ]);
    }

    /**
     * PUT /portal/staff-profile/address
     *
     * Mirrors legacy `?saveAddress=1`. Updates `staff` (current address
     * mirror + handphone + audit cols) and either updates or inserts a
     * `staff_address` row of type 1. The legacy BL also generated a
     * `sa_address_id` from `getSeqNo('STAFF_ADDRESS')`; we replicate
     * that with `MAX(sa_address_id) + 1` (FIMS-wide convention used by
     * other migrated controllers).
     */
    public function addressUpdate(UpdateStaffProfileAddressRequest $request): JsonResponse
    {
        $resolved = $this->resolveStaffWithDebug();
        $staff = $resolved['staff'];
        if ($staff === null) {
            return $this->staffNotResolvedResponse($resolved['debug']);
        }

        $data = $request->validated();
        $username = (string) (Auth::user()->name ?? Auth::user()->email ?? 'system');

        DB::connection(self::CONN)->transaction(function () use ($staff, $data, $username) {
            $existing = DB::connection(self::CONN)
                ->table('staff_address')
                ->where('stf_staff_id', $staff->stf_staff_id)
                ->orderByDesc('createddate')
                ->first();

            if ($existing) {
                DB::connection(self::CONN)
                    ->table('staff_address')
                    ->where('stf_staff_id', $staff->stf_staff_id)
                    ->where('sa_address_type', 1)
                    ->update([
                        'sa_address1' => $data['sa_address1'],
                        'sa_address2' => $data['sa_address2'] ?? null,
                        'sa_pcode' => $data['sa_pcode'] ?? null,
                        'sa_city' => $data['sa_city'] ?? null,
                        'sa_state' => $data['sa_state'] ?? null,
                        'sa_country' => $data['sa_country'] ?? null,
                        'isAcknowledgement' => 1,
                    ]);

                DB::connection(self::CONN)
                    ->table('staff')
                    ->where('stf_staff_id', $staff->stf_staff_id)
                    ->update([
                        'stf_current_address1' => $data['sa_address1'],
                        'stf_current_address2' => $data['sa_address2'] ?: null,
                        'stf_current_pcode' => $data['sa_pcode'] ?? null,
                        'stf_current_city' => $data['sa_city'] ?? null,
                        'stf_current_state' => $data['sa_state'] ?? null,
                        'stf_current_country' => $data['sa_country'] ?? null,
                        'stf_handphone_no' => $data['stf_handphone_no'] ?? null,
                        'updateddate' => DB::raw('NOW()'),
                        'updatedby' => $username,
                    ]);
            } else {
                $nextId = (int) (DB::connection(self::CONN)
                    ->table('staff_address')
                    ->max('sa_address_id') ?? 0) + 1;

                DB::connection(self::CONN)
                    ->table('staff_address')
                    ->insert([
                        'sa_address_id' => $nextId,
                        'stf_staff_id' => $staff->stf_staff_id,
                        'sa_address_type' => $data['sa_address_type'] ?? 1,
                        'sa_address1' => $data['sa_address1'],
                        'sa_address2' => $data['sa_address2'] ?? null,
                        'sa_pcode' => $data['sa_pcode'] ?? null,
                        'sa_city' => $data['sa_city'] ?? null,
                        'sa_state' => $data['sa_state'] ?? null,
                        'sa_country' => $data['sa_country'] ?? null,
                        'isAcknowledgement' => 1,
                    ]);

                DB::connection(self::CONN)
                    ->table('staff')
                    ->where('stf_staff_id', $staff->stf_staff_id)
                    ->update([
                        'stf_handphone_no' => $data['stf_handphone_no'] ?? null,
                        'updateddate' => DB::raw('NOW()'),
                        'updatedby' => $username,
                    ]);
            }
        });

        return $this->sendOk(['success' => true]);
    }

    /**
     * PUT /portal/staff-profile/marital-status
     *
     * Mirrors legacy `?updateMaritalStatus=1`. Updates `stf_marital_status`,
     * `stf_extended_field.stf_maritalstatus_desc` (JSON_SET), and flips
     * `isAcknowledgeMarital` to 1.
     */
    public function maritalUpdate(UpdateStaffProfileMaritalStatusRequest $request): JsonResponse
    {
        $resolved = $this->resolveStaffWithDebug();
        $staff = $resolved['staff'];
        if ($staff === null) {
            return $this->staffNotResolvedResponse($resolved['debug']);
        }

        $value = (string) $request->validated('marital_status');
        $desc = DB::connection(self::CONN)
            ->table('lookup_details')
            ->where('lma_code_name', 'MARITALSTATUS')
            ->where('lde_value', $value)
            ->value('lde_description');

        if ($desc === null) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'Validation failed', [
                'marital_status' => ['Selected marital status does not exist.'],
            ]);
        }

        DB::connection(self::CONN)->update(
            "UPDATE staff
             SET stf_marital_status = ?,
                 stf_extended_field = JSON_SET(IFNULL(stf_extended_field, '{}'), '$.stf_maritalstatus_desc', ?),
                 isAcknowledgeMarital = 1
             WHERE stf_staff_id = ?",
            [$value, (string) $desc, $staff->stf_staff_id]
        );

        return $this->sendOk([
            'success' => true,
            'marital_status' => $value,
            'maritalstatus_desc' => (string) $desc,
        ]);
    }

    /**
     * GET /portal/staff-profile/children
     *
     * Mirrors legacy `?all_children=1`. Paginated children list scoped
     * to the authenticated staff.
     */
    public function children(Request $request): JsonResponse
    {
        $resolved = $this->resolveStaffWithDebug();
        $staff = $resolved['staff'];
        if ($staff === null) {
            return $this->staffNotResolvedResponse($resolved['debug']);
        }

        return $this->sendOk(...$this->paginatedChildren($request, $staff->stf_staff_id, null));
    }

    /**
     * GET /portal/staff-profile/spouses
     *
     * Mirrors legacy `?family_spouse=1`. Paginated spouse list scoped
     * to the authenticated staff.
     */
    public function spouses(Request $request): JsonResponse
    {
        $resolved = $this->resolveStaffWithDebug();
        $staff = $resolved['staff'];
        if ($staff === null) {
            return $this->staffNotResolvedResponse($resolved['debug']);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'spo_spouse_seq');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::SPOUSES_SORTABLE, true)) {
            $sortBy = 'spo_spouse_seq';
        }

        $base = DB::connection(self::CONN)
            ->table('staff_spouse')
            ->where('stf_staff_id', $staff->stf_staff_id);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(spo_name, ''),
                    IFNULL(spo_ic_no, ''),
                    IFNULL(spo_tax_no, ''),
                    IFNULL(spo_spouse_seq, ''),
                    IFNULL(DATE_FORMAT(spo_marriage_date, '%d/%m/%Y'), ''),
                    IFNULL(DATE_FORMAT(spo_divorce_date, '%d/%m/%Y'), ''),
                    IFNULL(spo_death_date, '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->selectRaw("
                spo_name,
                stf_staff_id,
                spo_ic_no,
                spo_tax_no,
                spo_spouse_seq,
                DATE_FORMAT(spo_marriage_date, '%d/%m/%Y') AS spo_marriage_date,
                DATE_FORMAT(spo_divorce_date, '%d/%m/%Y') AS spo_divorce_date,
                spo_death_date
            ")
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'spo_spouse_seq' => (string) ($r->spo_spouse_seq ?? ''),
                'spo_name' => $r->spo_name ?? null,
                'spo_ic_no' => $r->spo_ic_no ?? null,
                'spo_tax_no' => $r->spo_tax_no ?? null,
                'spo_marriage_date' => $r->spo_marriage_date ?: null,
                'spo_divorce_date' => $r->spo_divorce_date ?: null,
                'spo_death_date' => $r->spo_death_date ?: null,
            ];
        })->all();

        return $this->sendOk($data, [
            'page' => $page, 'limit' => $limit, 'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    /**
     * GET /portal/staff-profile/spouses/{seq}/children
     *
     * Mirrors legacy `?family_children=1&spo_spouse_seq=X`. Paginated
     * children list scoped to the authenticated staff AND the supplied
     * spouse sequence.
     */
    public function spouseChildren(Request $request, string $seq): JsonResponse
    {
        $resolved = $this->resolveStaffWithDebug();
        $staff = $resolved['staff'];
        if ($staff === null) {
            return $this->staffNotResolvedResponse($resolved['debug']);
        }

        return $this->sendOk(...$this->paginatedChildren($request, $staff->stf_staff_id, $seq));
    }

    /**
     * Resolve the authenticated user to a row in the legacy `staff`
     * table. The legacy page worked from `$_USER['STAFF_ID']` set at
     * login, so for the migration we map Kerisi `users` → legacy
     * `staff` via the available identifiers (`stf_email_addr`,
     * `stf_ad_username`, `stf_staff_id`). Returns null on miss; the
     * caller composes a 404 / `STAFF_NOT_RESOLVED` payload that
     * includes the identifiers we attempted (see currentStaffOrError).
     *
     * Note: we deliberately do NOT filter on `staff_service.sts_job_status`
     * / `sts_status` here. The legacy BL doesn't enforce active
     * employment for the self-service profile, and applying the filter
     * blocked retired / inactive staff from viewing their own record.
     * The `staff_service` join is left only to break ties on the most
     * recent service row.
     */
    private function currentStaff(): ?object
    {
        return $this->resolveStaffWithDebug()['staff'] ?? null;
    }

    /**
     * Composite return of {staff?, debug} for use by both `currentStaff`
     * and the 404 helper. The debug array is small, never includes
     * sensitive auth data, and is safe to surface to the authenticated
     * user (it is gated by auth:sanctum middleware).
     *
     * @return array{staff: ?object, debug: array<string, mixed>}
     */
    private function resolveStaffWithDebug(): array
    {
        $user = Auth::user();
        if (! $user) {
            return ['staff' => null, 'debug' => []];
        }
        $email = trim((string) ($user->email ?? ''));
        $name = trim((string) ($user->name ?? ''));
        $adGuess = $email !== '' && str_contains($email, '@')
            ? explode('@', $email, 2)[0]
            : ($email !== '' ? $email : $name);

        $candidates = [];
        $row = DB::connection(self::CONN)
            ->table('staff as s')
            ->leftJoin('staff_service as ss', 's.stf_staff_id', '=', 'ss.stf_staff_id')
            ->where(function ($b) use ($email, $adGuess, $name, &$candidates) {
                if ($email !== '') {
                    $b->orWhere('s.stf_email_addr', $email);
                    $candidates['stf_email_addr'] = $email;
                }
                if ($adGuess !== '') {
                    $b->orWhere('s.stf_ad_username', $adGuess);
                    $candidates['stf_ad_username'] = $adGuess;
                }
                if ($name !== '' && $name !== $adGuess) {
                    $b->orWhere('s.stf_ad_username', $name);
                    $b->orWhere('s.stf_staff_id', $name);
                    $candidates['stf_ad_username_alt'] = $name;
                    $candidates['stf_staff_id'] = $name;
                }
            })
            ->orderByDesc('ss.sts_job_start_date')
            ->select(['s.stf_staff_id', 's.stf_staff_name', 's.stf_email_addr'])
            ->first();

        return [
            'staff' => $row ?: null,
            'debug' => [
                'auth_email' => $email !== '' ? $email : null,
                'auth_name' => $name !== '' ? $name : null,
                'tried' => $candidates,
            ],
        ];
    }

    /**
     * Build the 404 envelope for endpoints that require a resolved
     * staff. Wraps `sendError` and folds `debug` into `details` so
     * operators can see which identifiers were tried.
     */
    private function staffNotResolvedResponse(array $debug): JsonResponse
    {
        return $this->sendError(
            404,
            'STAFF_NOT_RESOLVED',
            'Authenticated user could not be matched to a staff record.',
            [
                'message' => 'No row in `staff` matched the authenticated user. Ensure the logged-in account has an `email` matching `staff.stf_email_addr`, or a `name` matching `staff.stf_ad_username` / `staff.stf_staff_id`.',
                'auth' => [
                    'email' => $debug['auth_email'] ?? null,
                    'name' => $debug['auth_name'] ?? null,
                ],
                'tried' => $debug['tried'] ?? [],
            ]
        );
    }

    /**
     * Run the paginated "children" query — used by both `?all_children=1`
     * and `?family_children=1`. When `$spouseSeq` is non-null the result
     * is further filtered by `stc_spouse_seq`.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, int>}
     */
    private function paginatedChildren(Request $request, string $staffId, ?string $spouseSeq): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'stc_child_seq');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::CHILDREN_SORTABLE, true)) {
            $sortBy = 'stc_child_seq';
        }
        if ($sortBy === 'stc_level_study') {
            $sortBy = 'sc.stc_level_study';
        } elseif ($sortBy !== 'age') {
            $sortBy = 'sc.'.$sortBy;
        }

        $base = DB::connection(self::CONN)
            ->table('staff_children as sc')
            ->leftJoin('lookup_details as ld', function ($j) {
                $j->on('ld.lde_value', '=', 'sc.stc_level_study')
                    ->where('ld.lma_code_name', 'CHILDLEVELSTUDY');
            })
            ->where('sc.stf_staff_id', $staffId);

        if ($spouseSeq !== null) {
            $base->where('sc.stc_spouse_seq', $spouseSeq);
        }

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(sc.stc_child_seq,''),
                    IFNULL(sc.stc_name,''),
                    IFNULL(sc.stc_spouse_seq,''),
                    IFNULL(sc.stc_ic_ref_no,''),
                    IFNULL(sc.stc_bod,''),
                    IFNULL(sc.stc_relation,''),
                    IF(sc.stc_pcb_status='Y','Yes','No'),
                    IFNULL(FLOOR(DATEDIFF(NOW(), sc.stc_bod)/365),''),
                    IFNULL(sc.stc_study_start_date,''),
                    IFNULL(sc.stc_study_end_date,''),
                    IFNULL(ld.lde_description,''),
                    IF(sc.stc_disability_status='Y','Yes','No'),
                    IFNULL(sc.stc_death_date,'')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->selectRaw("
                sc.stc_child_seq,
                sc.stc_name,
                sc.stf_staff_id,
                sc.stc_spouse_seq,
                sc.stc_ic_ref_no,
                sc.stc_bod,
                CASE sc.stc_relation
                    WHEN '05' THEN 'ANAK KANDUNG'
                    WHEN '07' THEN 'ANAK ANGKAT'
                    WHEN '15' THEN 'ANAK TIRI TANGGUNGAN'
                    ELSE sc.stc_relation
                END AS stc_relation,
                IF(sc.stc_pcb_status='Y','Yes','No') AS stc_pcb_status,
                FLOOR(DATEDIFF(NOW(), sc.stc_bod)/365) AS age,
                sc.stc_study_start_date,
                sc.stc_study_end_date,
                ld.lde_description AS stc_level_study,
                IF(sc.stc_disability_status='Y','Yes','No') AS stc_disability_status,
                sc.stc_death_date
            ")
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'stc_child_seq' => (string) ($r->stc_child_seq ?? ''),
                'stc_name' => $r->stc_name ?? null,
                'stc_spouse_seq' => $r->stc_spouse_seq !== null ? (string) $r->stc_spouse_seq : null,
                'stc_ic_ref_no' => $r->stc_ic_ref_no ?? null,
                'stc_bod' => $r->stc_bod ?? null,
                'stc_relation' => $r->stc_relation ?? null,
                'stc_pcb_status' => $r->stc_pcb_status ?? null,
                'age' => $r->age !== null ? (int) $r->age : null,
                'stc_study_start_date' => $r->stc_study_start_date ?? null,
                'stc_study_end_date' => $r->stc_study_end_date ?? null,
                'stc_level_study' => $r->stc_level_study ?? null,
                'stc_disability_status' => $r->stc_disability_status ?? null,
                'stc_death_date' => $r->stc_death_date ?? null,
            ];
        })->all();

        return [$data, [
            'page' => $page, 'limit' => $limit, 'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]];
    }

    /**
     * Resolve zakat amount + period using the legacy 3-step fallback.
     *
     * @return array{amount: string|null, period: string|null}
     */
    private function resolveZakat(string $staffId): array
    {
        $row = DB::connection(self::CONN)
            ->table('monthly_payroll_detl_his as h')
            ->whereIn('h.ity_income_code', function ($q) {
                $q->select('ity_income_code')
                    ->from('income_type')
                    ->where('ity_group_type', 'ZAKAT');
            })
            ->where('h.stf_staff_id', $staffId)
            ->whereRaw("h.mpr_pay_month = DATE_FORMAT(NOW(),'%Y%m')")
            ->selectRaw("
                IFNULL(h.mdh_paid_amt, 0) AS amount,
                CONCAT_WS('/', SUBSTR(h.mpr_pay_month, 5, 2), SUBSTR(h.mpr_pay_month, 1, 4)) AS period
            ")
            ->first();

        if ($row && (float) $row->amount > 0) {
            return ['amount' => (string) $row->amount, 'period' => $row->period];
        }

        $row = DB::connection(self::CONN)
            ->table('staff_allowance_deduction as a')
            ->whereIn('a.ity_income_code', function ($q) {
                $q->select('ity_income_code')
                    ->from('income_type')
                    ->where('ity_group_type', 'ZAKAT');
            })
            ->where('a.stf_staff_id', $staffId)
            ->whereRaw('DATE(NOW()) BETWEEN DATE(a.spa_start_date) AND DATE(a.spa_end_date)')
            ->selectRaw("
                IFNULL(a.spa_amount, 0) AS amount,
                DATE_FORMAT(a.spa_start_date, '%m/%Y') AS period
            ")
            ->first();

        if ($row && (float) $row->amount > 0) {
            return ['amount' => (string) $row->amount, 'period' => $row->period];
        }

        $row = DB::connection(self::CONN)
            ->table('staff_allowance_deduction as a')
            ->whereIn('a.ity_income_code', function ($q) {
                $q->select('ity_income_code')
                    ->from('income_type')
                    ->where('ity_group_type', 'ZAKAT');
            })
            ->where('a.stf_staff_id', $staffId)
            ->whereNotNull('a.spa_start_date')
            ->whereNull('a.spa_end_date')
            ->selectRaw("
                IFNULL(a.spa_amount, 0) AS amount,
                DATE_FORMAT(a.spa_start_date, '%m/%Y') AS period
            ")
            ->first();

        if ($row && (float) $row->amount > 0) {
            return ['amount' => (string) $row->amount, 'period' => $row->period];
        }

        return ['amount' => null, 'period' => null];
    }

    /**
     * Translate legacy stf_sal_incr_date numeric month into its label
     * (1 → JAN, 4 → APRIL, 7 → JULY, 10 → OCTOBER). Falls through for
     * unknown values, matching the legacy CASE expression.
     */
    private function formatSalIncrMonth(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $key = (string) $value;

        return match ($key) {
            '1' => 'JAN',
            '4' => 'APRIL',
            '7' => 'JULY',
            '10' => 'OCTOBER',
            default => $key,
        };
    }

    /**
     * Fetch lookup_details rows for a code list, sorted by display
     * description. Returns an empty list when no rows exist (graceful
     * degrade when the code name isn't provisioned on this DB).
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function lookupOptions(string $codeName): array
    {
        $rows = DB::connection(self::CONN)
            ->table('lookup_details')
            ->where('lma_code_name', $codeName)
            ->where('lde_status', 1)
            ->orderBy('lde_description')
            ->select(['lde_value', 'lde_description'])
            ->get();

        return $rows->map(function ($r) {
            return [
                'value' => (string) ($r->lde_value ?? ''),
                'label' => (string) ($r->lde_description ?? ''),
            ];
        })->all();
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
