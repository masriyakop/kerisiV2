<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelNoteRequest;
use App\Http\Requests\SaveAuthorizedReceiptingRequest;
use App\Http\Traits\ApiResponse;
use App\Models\AuthorizedReceipting;
use App\Models\OfflineReceiptStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AR > Authorized Receipting Form (MENUID 1953).
 *
 * Source: BL `V2_AUTHORIZED_RECEIPTING_FORM_API`. The legacy page mapped
 * several `$_GET` flags (`details_get`, `details_request`, `workflow_info`,
 * `dt_processFlow`, `dt_authorized`, `autoSuggestAuthorized`,
 * `autoSuggestProject`) against the same controller. We split the core
 * CRUD + workflow calls into REST endpoints:
 *
 *   GET  /{id}                     → show          (details_get + dt_authorized)
 *   POST /                         → saveDraft     (details_request submit=0)
 *   POST /{id}/submit              → submit        (details_request submit=1, stub)
 *   POST /{id}/cancel              → cancel        (stub; legacy form had no
 *                                                   direct cancel action but
 *                                                   the list page did — we
 *                                                   expose the same contract)
 *   GET  /{id}/process-flow        → processFlow   (dt_processFlow stub)
 *
 * Workflow notes:
 *   `submit` in the legacy system drives a two-branch `workflowSubmit`
 *   (`AUTHORIZED_RECEIPT` vs `AUTHORIZED_RCP_DP` when the event PTJ differs
 *   from the staff PTJ) plus an `APPROVE`-state side-effect that calls
 *   `getRefSpecialNo` to stamp `are_counter_no` and generates per-staff
 *   `ors_reference_no` values. None of the FIMS workflow SPs or `wf_*`
 *   tables have been migrated yet, so `submit` here only:
 *     - persists `dt_authorized` (same upsert semantics as saveDraft)
 *     - flips `are_status` from DRAFT / null → ENTRY
 *     - returns `workflow_stub=true`
 *   and `cancel` / `processFlow` follow the same stub contract used for
 *   CreditNoteFormController / DebitNoteFormController.
 */
class AuthorizedReceiptingFormController extends Controller
{
    use ApiResponse;

    /**
     * GET `/account-receivable/authorized-receipting-form/current-staff`.
     *
     * Backs the read-only "Details" card at the top of the Authorized
     * Receipting Form (MENUID 1953). The legacy page populated those fields
     * from the `$_USER` session superglobal (`STAFF_ID`, `STAFF_NAME`, `PTJ`,
     * `STAFF_POSITION_ACTUAL`, `JOB_GROUP_DESC`, `STAFF_NRIC`). Our Kerisi
     * auth model stores users in `users`, not in the legacy HR tables, so we
     * resolve a matching row in `staff` + `staff_service` via `stf_ad_username`
     * / `stf_email_addr` and gracefully fall back when no match is found.
     */
    public function currentStaff(Request $request): JsonResponse
    {
        $user = Auth::user();
        $email = (string) ($user->email ?? '');
        $name = (string) ($user->name ?? '');
        $adGuess = $email !== '' ? (str_contains($email, '@') ? explode('@', $email, 2)[0] : $email) : $name;

        $staff = DB::connection('mysql_secondary')
            ->table('staff as s')
            ->leftJoin('staff_service as ss', 's.stf_staff_id', '=', 'ss.stf_staff_id')
            ->whereIn('ss.sts_job_status', ['1', '2', '4'])
            ->whereIn('ss.sts_status', ['1', '6', 'B'])
            ->where(function ($b) use ($email, $adGuess) {
                if ($email !== '') {
                    $b->orWhere('s.stf_email_addr', $email);
                }
                if ($adGuess !== '') {
                    $b->orWhere('s.stf_ad_username', $adGuess);
                }
            })
            ->orderByDesc('ss.sts_job_start_date')
            ->selectRaw("
                s.stf_staff_id,
                s.stf_staff_name,
                s.stf_ic_no,
                s.stf_telno_work,
                s.stf_fax_no,
                s.stf_email_addr,
                s.stf_ad_username,
                ss.sts_oun_code,
                ss.sts_job_status,
                ss.sts_jobcode,
                JSON_UNQUOTE(JSON_EXTRACT(ss.sts_extended_field, '$.sts_jobstatus_desc')) AS sts_jobstatus_desc,
                JSON_UNQUOTE(JSON_EXTRACT(ss.sts_extended_field, '$.sts_job_desc'))       AS sts_job_desc,
                JSON_UNQUOTE(JSON_EXTRACT(ss.sts_extended_field, '$.sts_group_desc'))     AS sts_group_desc
            ")
            ->first();

        if (! $staff) {
            return $this->sendOk([
                'stf_staff_id' => null,
                'stf_staff_name' => $name !== '' ? $name : null,
                'stf_ic_no' => null,
                'stf_email_addr' => $email !== '' ? $email : null,
                'oun_code_ptj' => null,
                'oun_code_ptj_desc' => null,
                'stf_position' => null,
                'stf_position_desc' => null,
                'stf_employment_status' => null,
                'stf_jobcode' => null,
                'stf_job_desc' => null,
                'stf_telno_work' => null,
                'stf_fax_no' => null,
                'resolved' => false,
            ]);
        }

        $ptjDesc = null;
        if (! empty($staff->sts_oun_code)) {
            $ou = DB::connection('mysql_secondary')
                ->table('organization_unit')
                ->where('oun_code', $staff->sts_oun_code)
                ->value('oun_desc');
            $ptjDesc = $ou ? (string) $ou : null;
        }

        return $this->sendOk([
            'stf_staff_id' => (string) ($staff->stf_staff_id ?? ''),
            'stf_staff_name' => $staff->stf_staff_name ?? null,
            'stf_ic_no' => $staff->stf_ic_no ?? null,
            'stf_email_addr' => $staff->stf_email_addr ?? null,
            'oun_code_ptj' => $staff->sts_oun_code ?? null,
            'oun_code_ptj_desc' => $ptjDesc,
            'stf_position' => $staff->sts_job_status ?? null,
            'stf_position_desc' => $staff->sts_jobstatus_desc ?? null,
            'stf_employment_status' => $staff->sts_group_desc
                ?? $staff->sts_jobstatus_desc
                ?? null,
            'stf_jobcode' => $staff->sts_jobcode ?? null,
            'stf_job_desc' => $staff->sts_job_desc ?? null,
            'stf_telno_work' => $staff->stf_telno_work ?? null,
            'stf_fax_no' => $staff->stf_fax_no ?? null,
            'resolved' => true,
        ]);
    }

    /**
     * GET `/account-receivable/authorized-receipting-form/search-event`.
     *
     * Autosuggest for the "Event" combobox (visible when Collection Type =
     * EVENT). Mirrors `autoSuggestProject` in BL `V2_AUTHORIZED_RECEIPTING_FORM_API`
     * lines ~426-474: only OPEN capital projects flagged as EVENT that are still
     * within their valid window, optionally narrowed to projects the given
     * staff owns or shares a PTJ with.
     *
     * Query params:
     *   q (string)             — free-text needle (project no / desc)
     *   stf_staff_id (string)  — when set, apply the "my events" filter
     *   limit (int, default 20)
     */
    public function searchEvents(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $staffId = trim((string) $request->input('stf_staff_id', ''));
        $limit = max(1, min(50, (int) $request->input('limit', 20)));

        $builder = DB::connection('mysql_secondary')
            ->table('capital_project as icp')
            ->where('icp.cpa_source', 'EVENT')
            ->where('icp.cpa_project_status', 'OPEN')
            ->whereRaw('DATE(NOW()) <= DATE(icp.cpa_end_date)');

        if ($staffId !== '') {
            $builder->where(function ($b) use ($staffId) {
                $b->whereExists(function ($q2) use ($staffId) {
                    $q2->select(DB::raw(1))
                        ->from('capital_project_personnel as icps')
                        ->whereColumn('icps.cpa_project_id', 'icp.cpa_project_id')
                        ->where('icps.stf_staff_id', $staffId);
                })->orWhereIn('icp.oun_code', function ($q2) use ($staffId) {
                    $q2->select('stf.sts_oun_code')
                        ->from('staff_service as stf')
                        ->where('stf.stf_staff_id', $staffId);
                });
            });
        }

        if ($q !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $builder->where(function ($b) use ($needle) {
                $b->whereRaw("LOWER(IFNULL(icp.cpa_project_no, '')) LIKE ?", [$needle])
                    ->orWhereRaw("LOWER(IFNULL(icp.cpa_project_desc, '')) LIKE ?", [$needle]);
            });
        }

        $rows = $builder
            ->orderBy('icp.cpa_project_no')
            ->limit($limit)
            ->get([
                'icp.cpa_project_no',
                'icp.cpa_project_desc',
                'icp.oun_code',
                'icp.cpa_start_date',
                'icp.cpa_end_date',
            ]);

        return $this->sendOk(
            $rows->map(function ($r) {
                $no = (string) ($r->cpa_project_no ?? '');
                $desc = (string) ($r->cpa_project_desc ?? '');
                $label = $desc !== '' ? "{$no} - {$desc}" : $no;
                return [
                    'value' => $no,
                    'label' => $label,
                    'projectNo' => $no,
                    'projectDesc' => $desc !== '' ? $desc : null,
                    'ptj' => $r->oun_code !== null ? (string) $r->oun_code : null,
                    'startDate' => $r->cpa_start_date,
                    'endDate' => $r->cpa_end_date,
                ];
            })->values()->all()
        );
    }

    /**
     * GET `/account-receivable/authorized-receipting-form/search-staff`.
     *
     * Autosuggest for the "+ New" authorized-staff modal. Mirrors
     * `autoSuggestAuthorized` in BL `V2_AUTHORIZED_RECEIPTING_FORM_API` lines
     * ~526-547: active staff in a given PTJ (`staff_service.sts_oun_code`),
     * with service status in ('1','6','B') and job status in ('4','2','1').
     *
     * Query params:
     *   q (string)         — needle matched against staff id / name
     *   oun_code (string)  — PTJ filter (when blank we skip the filter and
     *                        show all active staff; legacy scoped by PTJ)
     *   limit (int, default 20)
     */
    public function searchStaff(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $oun = trim((string) $request->input('oun_code', ''));
        $limit = max(1, min(50, (int) $request->input('limit', 20)));

        $builder = DB::connection('mysql_secondary')
            ->table('staff as s')
            ->join('staff_service as ss', 's.stf_staff_id', '=', 'ss.stf_staff_id')
            ->whereIn('ss.sts_job_status', ['1', '2', '4'])
            ->whereIn('ss.sts_status', ['1', '6', 'B']);

        if ($oun !== '') {
            $builder->where('ss.sts_oun_code', $oun);
        }

        if ($q !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $builder->where(function ($b) use ($needle) {
                $b->whereRaw("LOWER(IFNULL(s.stf_staff_id, '')) LIKE ?", [$needle])
                    ->orWhereRaw("LOWER(IFNULL(s.stf_staff_name, '')) LIKE ?", [$needle]);
            });
        }

        $rows = $builder
            ->orderBy('s.stf_staff_name')
            ->limit($limit)
            ->selectRaw("
                s.stf_staff_id,
                s.stf_staff_name,
                s.stf_ic_no,
                s.stf_telno_work,
                s.stf_fax_no,
                s.stf_email_addr,
                ss.sts_oun_code,
                ss.sts_job_status,
                ss.sts_jobcode,
                JSON_UNQUOTE(JSON_EXTRACT(ss.sts_extended_field, '$.sts_jobstatus_desc')) AS sts_jobstatus_desc,
                JSON_UNQUOTE(JSON_EXTRACT(ss.sts_extended_field, '$.sts_job_desc'))       AS sts_job_desc
            ")
            ->get();

        return $this->sendOk(
            $rows->map(function ($r) {
                $id = (string) ($r->stf_staff_id ?? '');
                $name = (string) ($r->stf_staff_name ?? '');
                $jobCode = (string) ($r->sts_jobcode ?? '');
                $jobDesc = (string) ($r->sts_job_desc ?? '');
                $posCode = (string) ($r->sts_job_status ?? '');
                $posDesc = (string) ($r->sts_jobstatus_desc ?? '');
                return [
                    'value' => $id,
                    'label' => $name !== '' ? "{$id} - {$name}" : $id,
                    'staffId' => $id,
                    'staffName' => $name !== '' ? $name : null,
                    'ic' => $r->stf_ic_no ?? null,
                    'contact' => $r->stf_telno_work ?? null,
                    'fax' => $r->stf_fax_no ?? null,
                    'email' => $r->stf_email_addr ?? null,
                    'ptj' => $r->sts_oun_code ?? null,
                    'positionCode' => $posCode !== '' ? $posCode : null,
                    'positionDesc' => $posDesc !== '' ? $posDesc : null,
                    'position' => $posCode !== '' && $posDesc !== ''
                        ? "{$posCode} - {$posDesc}"
                        : ($posCode !== '' ? $posCode : ($posDesc !== '' ? $posDesc : null)),
                    'jobcodeCode' => $jobCode !== '' ? $jobCode : null,
                    'jobcodeDesc' => $jobDesc !== '' ? $jobDesc : null,
                    'jobcode' => $jobCode !== '' && $jobDesc !== ''
                        ? "{$jobCode} - {$jobDesc}"
                        : ($jobCode !== '' ? $jobCode : ($jobDesc !== '' ? $jobDesc : null)),
                ];
            })->values()->all()
        );
    }

    public function show(string $id): JsonResponse
    {
        $master = AuthorizedReceipting::query()
            ->where('are_authorized_receipting_id', $id)
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Authorized receipting not found');
        }

        $ext = $this->decodeJson($master->are_extended_field);

        $staff = OfflineReceiptStaff::query()
            ->where('are_authorized_receipting_id', $id)
            ->get();

        $dt = $staff->map(function ($r) {
            $ext = $this->decodeJson($r->ors_extended_field ?? null);
            return [
                'ors_id' => (string) $r->ors_id,
                'ors_staff_id' => $r->ors_staff_id,
                'ors_staff_name' => $ext['ors_staff_name'] ?? null,
                'ors_ic' => $ext['ors_ic'] ?? null,
                'ors_oun_code' => $ext['ors_oun_code'] ?? null,
                'ors_contact_no' => $r->ors_contact_no,
                'ors_fax_no' => $r->ors_fax_no,
                'ors_email' => $r->ors_email,
                'ors_position' => $ext['ors_position'] ?? $r->ors_position,
                'ors_position_desc' => $ext['ors_position_desc'] ?? $r->ors_position_desc,
                'sts_jobcode' => $r->sts_jobcode,
                'sts_job_desc' => $ext['sts_job_desc'] ?? $r->sts_job_desc,
                'ors_process_flag' => $r->ors_process_flag,
                'ors_reason' => $r->ors_reason,
                'ors_reference_no' => $r->ors_reference_no,
            ];
        });

        return $this->sendOk([
            'head' => [
                'are_authorized_receipting_id' => (string) $master->are_authorized_receipting_id,
                'are_application_no' => $master->are_application_no,
                'stf_staff_id' => $master->stf_staff_id,
                'stf_staff_name' => $ext['stf_staff_name'] ?? null,
                'oun_code_ptj' => $master->oun_code_ptj,
                'oun_code_ptj_desc' => $ext['oun_code_ptj_desc'] ?? null,
                'are_position_code' => $master->are_position_code,
                'are_event_code' => $master->are_event_code,
                'are_reason' => $master->are_reason,
                'are_purposed_code' => $master->are_purposed_code,
                'are_employment_code' => $master->are_employment_code,
                'are_duration_from' => $master->are_duration_from,
                'are_duration_to' => $master->are_duration_to,
                'are_status' => $master->are_status,
                'are_counter_no' => $master->are_counter_no,
                'are_receipt_type' => $master->are_receipt_type,
            ],
            'dt_authorized' => $dt,
        ]);
    }

    public function saveDraft(SaveAuthorizedReceiptingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $username = $this->currentUsername();
        $nowStr = now()->format('Y-m-d H:i:s');

        $areId = (string) ($data['are_authorized_receipting_id'] ?? '');
        $isNew = $areId === '';

        if ($isNew) {
            $areId = (string) $this->nextSeq('authorized_receipting');
        }

        // Legacy behaviour: when creating a fresh record we stamp a
        // deterministic application number from the PTJ + staff ID.
        // `getRefSpecialNo('authorizedReceiptingApp', ...)` is not ported;
        // we substitute a readable temporary code the user can see.
        $appNo = $isNew
            ? $this->generateAppNo($data['oun_code_ptj'], $data['stf_staff_id'])
            : null;

        $ext = array_merge($data['extended'] ?? [], [
            'stf_staff_name' => $data['stf_staff_id_desc'] ?? null,
        ]);

        $status = $data['are_status'] ?? 'DRAFT';

        DB::connection('mysql_secondary')->transaction(function () use ($areId, $isNew, $appNo, $data, $ext, $status, $username, $nowStr) {
            $payload = [
                'are_authorized_receipting_id' => $areId,
                'stf_staff_id' => $data['stf_staff_id'],
                'oun_code_ptj' => $data['oun_code_ptj'],
                'are_position_code' => $data['are_position_code'] ?? null,
                'are_event_code' => $data['are_event_code'] ?? null,
                'are_reason' => $data['are_reason'] ?? null,
                'are_purposed_code' => $data['are_purposed_code'] ?? null,
                'are_employment_code' => $data['are_employment_code'] ?? null,
                'are_duration_from' => $this->parseLegacyDate($data['are_duration_from'] ?? null),
                'are_duration_to' => $this->parseLegacyDate($data['are_duration_to'] ?? null),
                'are_status' => $status,
                'are_receipt_type' => 'OFFLINE',
                'are_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
            ];

            if ($isNew) {
                $payload['are_application_no'] = $appNo;
                AuthorizedReceipting::query()->create(array_merge($payload, [
                    'createdby' => $username,
                    'createddate' => $nowStr,
                ]));
            } else {
                AuthorizedReceipting::query()
                    ->where('are_authorized_receipting_id', $areId)
                    ->update(array_merge($payload, [
                        'updatedby' => $username,
                        'updateddate' => $nowStr,
                    ]));
            }

            $this->syncAuthorizedStaff($areId, $data['dt_authorized'] ?? [], $username, $nowStr);
        });

        return $this->sendOk([
            'status' => 'ok',
            'are_authorized_receipting_id' => (string) $areId,
            'are_application_no' => $appNo,
            'are_status' => $status,
        ]);
    }

    public function submit(SaveAuthorizedReceiptingRequest $request, string $id): JsonResponse
    {
        $master = AuthorizedReceipting::query()
            ->where('are_authorized_receipting_id', $id)
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Authorized receipting not found');
        }

        $data = $request->validated();
        $username = $this->currentUsername();
        $nowStr = now()->format('Y-m-d H:i:s');

        // Same persistence as saveDraft, then flip to ENTRY if currently
        // DRAFT / null. See class docblock for why this is a stub.
        DB::connection('mysql_secondary')->transaction(function () use ($id, $master, $data, $username, $nowStr) {
            $payload = [
                'stf_staff_id' => $data['stf_staff_id'],
                'oun_code_ptj' => $data['oun_code_ptj'],
                'are_position_code' => $data['are_position_code'] ?? $master->are_position_code,
                'are_event_code' => $data['are_event_code'] ?? $master->are_event_code,
                'are_reason' => $data['are_reason'] ?? $master->are_reason,
                'are_purposed_code' => $data['are_purposed_code'] ?? $master->are_purposed_code,
                'are_employment_code' => $data['are_employment_code'] ?? $master->are_employment_code,
                'are_duration_from' => $this->parseLegacyDate($data['are_duration_from'] ?? null) ?? $master->are_duration_from,
                'are_duration_to' => $this->parseLegacyDate($data['are_duration_to'] ?? null) ?? $master->are_duration_to,
                'updatedby' => $username,
                'updateddate' => $nowStr,
            ];

            $currentStatus = $master->are_status;
            if ($currentStatus === null || $currentStatus === '' || $currentStatus === 'DRAFT') {
                $payload['are_status'] = 'ENTRY';
            }

            AuthorizedReceipting::query()
                ->where('are_authorized_receipting_id', $id)
                ->update($payload);

            $this->syncAuthorizedStaff($id, $data['dt_authorized'] ?? [], $username, $nowStr);
        });

        return $this->sendOk([
            'status' => 'ok',
            'are_status' => 'ENTRY',
            'workflow_stub' => true,
            'message' => 'Authorized receipting submitted. Workflow routing (AUTHORIZED_RECEIPT / AUTHORIZED_RCP_DP) is not yet migrated; approver chain must be configured in a later release.',
        ]);
    }

    public function cancel(CancelNoteRequest $request, string $id): JsonResponse
    {
        $master = AuthorizedReceipting::query()
            ->where('are_authorized_receipting_id', $id)
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Authorized receipting not found');
        }

        $ext = $this->decodeJson($master->are_extended_field);
        $ext['are_cancel_reason'] = $request->validated()['cancel_reason'];
        $ext['are_cancelled_at'] = now()->toAtomString();
        $ext['are_cancelled_by'] = $this->currentUsername();

        AuthorizedReceipting::query()
            ->where('are_authorized_receipting_id', $id)
            ->update([
                'are_status' => 'CANCELLED',
                'are_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'updatedby' => $this->currentUsername(),
                'updateddate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->sendOk([
            'status' => 'ok',
            'are_status' => 'CANCELLED',
            'message' => 'Authorized receipting cancelled.',
        ]);
    }

    public function processFlow(string $id): JsonResponse
    {
        $master = AuthorizedReceipting::query()
            ->where('are_authorized_receipting_id', $id)
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Authorized receipting not found');
        }

        return $this->sendOk([], [
            'workflow_stub' => true,
            'note' => 'Workflow history tables (wf_application_status, wf_process, staff_service) are not yet migrated.',
        ]);
    }

    /**
     * Upsert the `offline_receipt_staff` rows attached to a given master,
     * removing any pre-existing rows whose `ors_id` was not present in the
     * new payload (mirrors the legacy DELETE-by-tokeep pattern).
     *
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function syncAuthorizedStaff(string $areId, array $rows, string $username, string $nowStr): void
    {
        $keep = [-999];
        foreach ($rows as $row) {
            if (! empty($row['ors_id'])) {
                $keep[] = (string) $row['ors_id'];
            }
        }

        OfflineReceiptStaff::query()
            ->where('are_authorized_receipting_id', $areId)
            ->where('ors_process_flag', 'Y')
            ->whereNotIn('ors_id', $keep)
            ->delete();

        foreach ($rows as $row) {
            // Legacy code splits `sts_jobcode` / `ors_position` as
            // "CODE - DESC" strings picked from autosuggest. We honour
            // both shapes: if the caller passed the split parts we use
            // them directly; otherwise we split on ' - '.
            [$jobCode, $jobDesc] = $this->splitCodeDesc((string) ($row['sts_jobcode'] ?? ''));
            [$posCode, $posDesc] = $this->splitCodeDesc((string) ($row['ors_position'] ?? ''));

            $ext = [
                'ors_staff_name' => $row['ors_staff_name'] ?? null,
                'ors_ic' => $row['ors_ic'] ?? null,
                'ors_oun_code' => $row['ors_oun_code'] ?? null,
                'ors_position' => $posCode,
                'ors_position_desc' => $posDesc,
                'sts_job_desc' => $jobDesc,
            ];

            $orsId = (string) ($row['ors_id'] ?? '');
            $payload = [
                'are_authorized_receipting_id' => $areId,
                'ors_staff_id' => $row['ors_staff_id'],
                'ors_contact_no' => $row['ors_contact_no'] ?? null,
                'ors_fax_no' => $row['ors_fax_no'] ?? null,
                'ors_email' => $row['ors_email'] ?? null,
                'sts_jobcode' => $jobCode,
                'sts_job_desc' => $jobDesc,
                'ors_position' => $posCode,
                'ors_position_desc' => $posDesc,
                'ors_process_flag' => $row['ors_process_flag'] ?? 'Y',
                'ors_reason' => $row['ors_reason'] ?? null,
                'ors_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
            ];

            if ($orsId === '') {
                OfflineReceiptStaff::query()->create(array_merge($payload, [
                    'ors_id' => (string) $this->nextSeq('offline_receipt_staff'),
                    'createdby' => $username,
                    'createddate' => $nowStr,
                ]));
            } else {
                OfflineReceiptStaff::query()
                    ->where('ors_id', $orsId)
                    ->update(array_merge($payload, [
                        'updatedby' => $username,
                        'updateddate' => $nowStr,
                    ]));
            }
        }
    }

    /** @return array{0:?string,1:?string} */
    private function splitCodeDesc(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [null, null];
        }
        if (str_contains($raw, ' - ')) {
            [$code, $desc] = explode(' - ', $raw, 2);
            return [trim($code) ?: null, trim($desc) ?: null];
        }
        return [$raw, null];
    }

    private function decodeJson(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function parseLegacyDate(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $raw, $m)) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }
        return $raw;
    }

    private function nextSeq(string $table): int
    {
        $col = match ($table) {
            'authorized_receipting' => 'are_authorized_receipting_id',
            'offline_receipt_staff' => 'ors_id',
            default => 'id',
        };
        $max = (int) DB::connection('mysql_secondary')->table($table)->max($col);
        return $max + 1;
    }

    private function generateAppNo(string $ptj, string $staffId): string
    {
        // Legacy uses `getRefSpecialNo('authorizedReceiptingApp', ...)` —
        // not ported. Emit a deterministic but temporary application
        // number that makes the origin obvious in data dumps.
        $count = AuthorizedReceipting::query()
            ->whereRaw("DATE_FORMAT(createddate, '%Y%m') = ?", [now()->format('Ym')])
            ->count();
        return sprintf('AR-%s-%s-%s-%04d', $ptj, $staffId, now()->format('Ym'), $count + 1);
    }

    private function currentUsername(): string
    {
        return (string) (Auth::user()->email ?? Auth::user()->name ?? 'system');
    }
}
