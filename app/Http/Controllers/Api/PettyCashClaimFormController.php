<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPettyCashClaimRequest;
use App\Http\Requests\SavePettyCashClaimRequest;
use App\Http\Traits\ApiResponse;
use App\Models\PettyCashDetail;
use App\Models\PettyCashMaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Petty Cash Claim Form (PAGEID 1544 / MENUID 1872).
 *
 * Source: FIMS BL `MM_API_PETTYCASH_PETTYCASHCLAIMFORM`. The legacy BL
 * exposes five `$_GET` branches:
 *
 *   ?pms_request_by=1         → staff autosuggest (CONCAT_WS('-', id, name))
 *   ?autoSuggest_pcm_id=1     → petty_cash_main autosuggest scoped to
 *                                the current user's PTJ, returning default
 *                                fund / activity / PTJ / cost-centre / SO
 *                                + max-per-receipt for client-side cap.
 *   ?autoSuggest_acm_acct_code=1 → account_main autosuggest at max level,
 *                                 activity 'BELANJA', optional fund filter.
 *   ?save=1                   → master + detail inserts/updates, then
 *                               `CALL workflowSubmit(...)`.
 *   ?getSeq=1                 → next petty_cash_details id (legacy uses
 *                               a DB sequence table, ported via `nextSeq`).
 *
 * We split the `?save=1` branch into a REST layout:
 *
 *   GET  /petty-cash/claim-form/request-by/suggest → staff autosuggest
 *   GET  /petty-cash/claim-form/pcm/suggest        → petty_cash_main suggest
 *   GET  /petty-cash/claim-form/account-code/suggest → account_main suggest
 *   GET  /petty-cash/claim-form/next-seq           → next detail id
 *   GET  /petty-cash/claim-form/{id}               → show head + lines
 *   POST /petty-cash/claim-form                    → saveDraft
 *   POST /petty-cash/claim-form/{id}/submit        → submit (workflow stub)
 *   POST /petty-cash/claim-form/{id}/cancel        → cancel (workflow stub)
 *   GET  /petty-cash/claim-form/{id}/process-flow  → processFlow (stub)
 *
 * Workflow caveat (follows `.cursor/rules/ar-note-form-pattern.mdc` rule 6):
 *   The legacy `?save=1` branch calls `CALL workflowSubmit(...)` and seeds
 *   `wf_task` rows. Those stored procedures are not ported. `saveDraft`
 *   persists rows with `pms_status = 'ENTRY'` (same as legacy) but does
 *   NOT create a workflow task. `submit` keeps the Entry status and marks
 *   `workflow_stub=true`; `cancel` flips to `CANCELLED` and records the
 *   reason in `pms_extended_field`. `processFlow` returns an empty list.
 */
class PettyCashClaimFormController extends Controller
{
    use ApiResponse;

    // -------- Autosuggest endpoints --------

    /** Staff search for `Request By`. Legacy: `?pms_request_by=1`. */
    public function suggestRequestBy(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $limit = max(1, min(50, (int) $request->input('limit', 20)));

        $rows = DB::connection('mysql_secondary')
            ->table('staff')
            ->select(['stf_staff_id', 'stf_staff_name'])
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS('-', IFNULL(stf_staff_id, ''), IFNULL(stf_staff_name, ''))) LIKE ?",
                    [$like]
                );
            })
            ->orderBy('stf_staff_id')
            ->limit($limit)
            ->get();

        return $this->sendOk(
            $rows->map(fn ($r) => [
                'id' => (string) ($r->stf_staff_id ?? ''),
                'text' => trim(sprintf('%s-%s', $r->stf_staff_id ?? '', $r->stf_staff_name ?? ''), '-'),
                'Name' => (string) ($r->stf_staff_name ?? ''),
            ])->values()->all()
        );
    }

    /**
     * Petty Cash Main autosuggest. Legacy: `?autoSuggest_pcm_id=1`.
     *
     * Legacy scopes rows to `oun_code = $_USER['PTJ']`. We expose the
     * same filter through an optional `ptj_code` query param so the UI
     * can pass the logged-in staff's PTJ without requiring us to resolve
     * it from the session here.
     */
    public function suggestPcm(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $ptj = trim((string) $request->input('ptj_code', ''));
        $limit = max(1, min(50, (int) $request->input('limit', 20)));

        $rows = DB::connection('mysql_secondary')
            ->table('petty_cash_main as pcm')
            ->leftJoin('fund_type as ft', 'ft.fty_fund_type', '=', 'pcm.fty_fund_type')
            ->leftJoin('activity_type as at', 'at.at_activity_code', '=', 'pcm.at_activity_code')
            ->leftJoin('organization_unit as ou', 'ou.oun_code', '=', 'pcm.oun_code')
            ->leftJoin('costcentre as cc', 'cc.ccr_costcentre', '=', 'pcm.ccr_costcentre')
            ->select([
                'pcm.pcm_id',
                'pcm.pcm_payto_id',
                'pcm.pcm_payto_name',
                'pcm.fty_fund_type',
                'pcm.at_activity_code',
                'pcm.oun_code',
                'pcm.ccr_costcentre',
                'pcm.so_code',
                'pcm.pcm_max_per_receipt',
                'ft.fty_fund_desc',
                'at.at_activity_description_bm',
                'ou.oun_desc',
                'cc.ccr_costcentre_desc',
            ])
            ->when($ptj !== '', fn ($qry) => $qry->where('pcm.oun_code', $ptj))
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS('__',
                        IFNULL(pcm.pcm_payto_id,''),
                        IFNULL(pcm.pcm_payto_name,''),
                        IFNULL(pcm.fty_fund_type,''),
                        IFNULL(pcm.at_activity_code,''),
                        IFNULL(pcm.oun_code,''),
                        IFNULL(pcm.ccr_costcentre,''),
                        IFNULL(pcm.so_code,'')
                    )) LIKE ?",
                    [$like]
                );
            })
            ->orderBy('pcm.pcm_payto_id')
            ->limit($limit)
            ->get();

        return $this->sendOk(
            $rows->map(fn ($r) => [
                'id' => (int) $r->pcm_id,
                'text' => trim(sprintf('%s - %s', $r->pcm_payto_id ?? '', $r->pcm_payto_name ?? ''), ' -'),
                'defaults' => [
                    'fty_fund_type' => (string) ($r->fty_fund_type ?? ''),
                    'fty_fund_desc' => (string) ($r->fty_fund_desc ?? ''),
                    'at_activity_code' => (string) ($r->at_activity_code ?? ''),
                    'at_activity_desc' => (string) ($r->at_activity_description_bm ?? ''),
                    'oun_code' => (string) ($r->oun_code ?? ''),
                    'oun_desc' => (string) ($r->oun_desc ?? ''),
                    'ccr_costcentre' => (string) ($r->ccr_costcentre ?? ''),
                    'ccr_costcentre_desc' => (string) ($r->ccr_costcentre_desc ?? ''),
                    'so_code' => (string) ($r->so_code ?? ''),
                ],
                'max_per_receipt' => $r->pcm_max_per_receipt !== null
                    ? (float) $r->pcm_max_per_receipt
                    : null,
            ])->values()->all()
        );
    }

    /**
     * Account code autosuggest. Legacy: `?autoSuggest_acm_acct_code=1`.
     *
     * Returns leaf-level rows where `acm_acct_activity='BELANJA'`. When
     * `fund_type` is supplied we apply the maips-style join against
     * `account_main_fund` so only codes valid for that fund are shown.
     */
    public function suggestAccountCode(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $fund = trim((string) $request->input('fund_type', ''));
        $limit = max(1, min(50, (int) $request->input('limit', 20)));

        $maxLevel = DB::connection('mysql_secondary')
            ->table('account_main')
            ->max('acm_acct_level');

        if ($maxLevel === null) {
            return $this->sendOk([]);
        }

        $query = DB::connection('mysql_secondary')
            ->table('account_main as am')
            ->where('am.acm_acct_activity', 'BELANJA')
            ->where('am.acm_acct_level', $maxLevel)
            ->when($fund !== '', function ($qry) use ($fund) {
                $qry->whereExists(function ($sub) use ($fund) {
                    $sub->select(DB::raw('1'))
                        ->from('account_main_fund as amf')
                        ->whereColumn('amf.acm_acct_code', 'am.acm_acct_code')
                        ->where('amf.fty_fund_type', $fund);
                });
            })
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS(' - ', IFNULL(am.acm_acct_code,''), IFNULL(am.acm_acct_desc,''))) LIKE ?",
                    [$like]
                );
            })
            ->orderBy('am.acm_acct_code')
            ->limit($limit);

        $rows = $query->get(['am.acm_acct_code', 'am.acm_acct_desc']);

        return $this->sendOk(
            $rows->map(fn ($r) => [
                'id' => (string) ($r->acm_acct_code ?? ''),
                'text' => trim(sprintf('%s - %s', $r->acm_acct_code ?? '', $r->acm_acct_desc ?? ''), ' -'),
            ])->values()->all()
        );
    }

    /** Legacy `?getSeq=1` — returns the next petty_cash_details id. */
    public function nextDetailSeq(): JsonResponse
    {
        return $this->sendOk(['pcd_id' => $this->nextSeq('petty_cash_details', 'pcd_id')]);
    }

    /**
     * Fund Type dropdown. The legacy modal renders Fund Type as a
     * Select2 populated from the `fund_type` master (not an explicit
     * BL branch — BL only exposes PCM/account/staff autosuggests, so we
     * serve the list here to drive the cascade Fund Type → Activity →
     * OU → Cost Centre).
     */
    public function suggestFundType(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $limit = max(1, min(100, (int) $request->input('limit', 50)));

        $rows = DB::connection('mysql_secondary')
            ->table('fund_type')
            ->select(['fty_fund_type', 'fty_fund_desc'])
            ->where(function ($b) {
                $b->whereNull('fty_status')->orWhere('fty_status', 1);
            })
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS(' - ', IFNULL(fty_fund_type,''), IFNULL(fty_fund_desc,''))) LIKE ?",
                    [$like]
                );
            })
            ->orderBy('fty_fund_type')
            ->limit($limit)
            ->get();

        return $this->sendOk(
            $rows->map(fn ($r) => [
                'id' => (string) ($r->fty_fund_type ?? ''),
                'desc' => (string) ($r->fty_fund_desc ?? ''),
                'text' => trim(sprintf('%s - %s', $r->fty_fund_type ?? '', $r->fty_fund_desc ?? ''), ' -'),
            ])->values()->all()
        );
    }

    /**
     * Activity Code dropdown. Serves the `activity_type` master. When
     * `fund_type` is supplied we narrow to activities that actually exist
     * in the `petty_cash_main` catalogue for that fund, matching the
     * legacy behaviour where selecting a Fund Type shrinks the downstream
     * lists to combinations already configured in `petty_cash_main`.
     */
    public function suggestActivityCode(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $fund = trim((string) $request->input('fund_type', ''));
        $limit = max(1, min(100, (int) $request->input('limit', 50)));

        $rows = DB::connection('mysql_secondary')
            ->table('activity_type')
            ->select(['at_activity_code', 'at_activity_description_bm'])
            ->where(function ($b) {
                $b->whereNull('at_status')->orWhere('at_status', 1);
            })
            ->when($fund !== '', function ($qry) use ($fund) {
                $qry->whereExists(function ($sub) use ($fund) {
                    $sub->select(DB::raw('1'))
                        ->from('petty_cash_main as pcm')
                        ->whereColumn('pcm.at_activity_code', 'activity_type.at_activity_code')
                        ->where('pcm.fty_fund_type', $fund);
                });
            })
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS(' - ', IFNULL(at_activity_code,''), IFNULL(at_activity_description_bm,''))) LIKE ?",
                    [$like]
                );
            })
            ->orderBy('at_activity_code')
            ->limit($limit)
            ->get();

        return $this->sendOk(
            $rows->map(fn ($r) => [
                'id' => (string) ($r->at_activity_code ?? ''),
                'desc' => (string) ($r->at_activity_description_bm ?? ''),
                'text' => trim(sprintf('%s - %s', $r->at_activity_code ?? '', $r->at_activity_description_bm ?? ''), ' -'),
            ])->values()->all()
        );
    }

    /**
     * OU (PTJ) dropdown. Serves the `organization_unit` master. Optional
     * `fund_type` + `activity_code` params narrow the list to OUs that
     * actually appear in `petty_cash_main` for that combination.
     */
    public function suggestOun(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $fund = trim((string) $request->input('fund_type', ''));
        $activity = trim((string) $request->input('activity_code', ''));
        $limit = max(1, min(100, (int) $request->input('limit', 50)));

        $rows = DB::connection('mysql_secondary')
            ->table('organization_unit')
            ->select(['oun_code', 'oun_desc'])
            ->where(function ($b) {
                $b->whereNull('oun_status')->orWhere('oun_status', 1);
            })
            ->when($fund !== '' || $activity !== '', function ($qry) use ($fund, $activity) {
                $qry->whereExists(function ($sub) use ($fund, $activity) {
                    $sub->select(DB::raw('1'))
                        ->from('petty_cash_main as pcm')
                        ->whereColumn('pcm.oun_code', 'organization_unit.oun_code')
                        ->when($fund !== '', fn ($s) => $s->where('pcm.fty_fund_type', $fund))
                        ->when($activity !== '', fn ($s) => $s->where('pcm.at_activity_code', $activity));
                });
            })
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS(' - ', IFNULL(oun_code,''), IFNULL(oun_desc,''))) LIKE ?",
                    [$like]
                );
            })
            ->orderBy('oun_code')
            ->limit($limit)
            ->get();

        return $this->sendOk(
            $rows->map(fn ($r) => [
                'id' => (string) ($r->oun_code ?? ''),
                'desc' => (string) ($r->oun_desc ?? ''),
                'text' => trim(sprintf('%s - %s', $r->oun_code ?? '', $r->oun_desc ?? ''), ' -'),
            ])->values()->all()
        );
    }

    /**
     * Cost Centre dropdown. Serves the `costcentre` master. Optional
     * `fund_type` / `activity_code` / `oun_code` params narrow the list
     * to cost centres configured in `petty_cash_main` for that cascade
     * combination.
     */
    public function suggestCostCentre(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $fund = trim((string) $request->input('fund_type', ''));
        $activity = trim((string) $request->input('activity_code', ''));
        $ou = trim((string) $request->input('oun_code', ''));
        $limit = max(1, min(100, (int) $request->input('limit', 50)));

        $rows = DB::connection('mysql_secondary')
            ->table('costcentre')
            ->select(['ccr_costcentre', 'ccr_costcentre_desc'])
            ->where(function ($b) {
                $b->whereNull('ccr_status')->orWhere('ccr_status', 1);
            })
            ->when($fund !== '' || $activity !== '' || $ou !== '', function ($qry) use ($fund, $activity, $ou) {
                $qry->whereExists(function ($sub) use ($fund, $activity, $ou) {
                    $sub->select(DB::raw('1'))
                        ->from('petty_cash_main as pcm')
                        ->whereColumn('pcm.ccr_costcentre', 'costcentre.ccr_costcentre')
                        ->when($fund !== '', fn ($s) => $s->where('pcm.fty_fund_type', $fund))
                        ->when($activity !== '', fn ($s) => $s->where('pcm.at_activity_code', $activity))
                        ->when($ou !== '', fn ($s) => $s->where('pcm.oun_code', $ou));
                });
            })
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS(' - ', IFNULL(ccr_costcentre,''), IFNULL(ccr_costcentre_desc,''))) LIKE ?",
                    [$like]
                );
            })
            ->orderBy('ccr_costcentre')
            ->limit($limit)
            ->get();

        return $this->sendOk(
            $rows->map(fn ($r) => [
                'id' => (string) ($r->ccr_costcentre ?? ''),
                'desc' => (string) ($r->ccr_costcentre_desc ?? ''),
                'text' => trim(sprintf('%s - %s', $r->ccr_costcentre ?? '', $r->ccr_costcentre_desc ?? ''), ' -'),
            ])->values()->all()
        );
    }

    // -------- Read --------

    /**
     * Return the full form payload for the given application.
     *
     * Legacy form re-populates from `petty_cash_master` + `petty_cash_details`
     * tables plus a one-off lookup on `petty_cash_main` for the current
     * balance/quota per-receipt. This endpoint mirrors that payload.
     */
    public function show(int $id): JsonResponse
    {
        $master = DB::connection('mysql_secondary')
            ->table('petty_cash_master')
            ->where('pms_id', $id)
            ->first();

        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Petty cash application not found');
        }

        $ext = $this->decodeJson($master->pms_extended_field ?? null);

        $lines = DB::connection('mysql_secondary')
            ->table('petty_cash_details as pcd')
            ->leftJoin('petty_cash_main as pcm', 'pcm.pcm_id', '=', 'pcd.pcm_id')
            ->leftJoin('account_main as am', 'am.acm_acct_code', '=', 'pcd.acm_acct_code')
            ->leftJoin('fund_type as ft', 'ft.fty_fund_type', '=', 'pcd.fty_fund_type')
            ->leftJoin('activity_type as at', 'at.at_activity_code', '=', 'pcd.at_activity_code')
            ->leftJoin('organization_unit as ou', 'ou.oun_code', '=', 'pcd.oun_code')
            ->leftJoin('costcentre as cc', 'cc.ccr_costcentre', '=', 'pcd.ccr_costcentre')
            ->where('pcd.pms_application_no', $master->pms_application_no)
            ->orderBy('pcd.pcd_id')
            ->get([
                'pcd.pcd_id',
                'pcd.pcd_receipt_no',
                'pcd.pcd_trans_desc',
                'pcd.pcd_trans_amt',
                'pcd.pcd_status',
                'pcd.pcm_id',
                'pcd.fty_fund_type',
                'pcd.at_activity_code',
                'pcd.oun_code',
                'pcd.ccr_costcentre',
                'pcd.cpa_project_no',
                'pcd.so_code',
                'pcd.acm_acct_code',
                'pcm.pcm_payto_id',
                'pcm.pcm_payto_name',
                'pcm.pcm_max_per_receipt',
                'am.acm_acct_desc',
                'ft.fty_fund_desc',
                'at.at_activity_description_bm',
                'ou.oun_desc',
                'cc.ccr_costcentre_desc',
            ]);

        $requestDate = $master->pms_request_date
            ? Carbon::parse($master->pms_request_date)
            : null;

        return $this->sendOk([
            'head' => [
                'pms_id' => (int) $master->pms_id,
                'pms_application_no' => (string) ($master->pms_application_no ?? ''),
                'pms_request_by' => (string) ($master->pms_request_by ?? ''),
                'pms_request_by_desc' => (string) (
                    $ext['pms_request_by_desc']
                        ?? $master->pms_request_by_desc
                        ?? ''
                ),
                'pms_request_date' => $requestDate ? $requestDate->format('Y-m-d') : '',
                'pms_total_amt' => $master->pms_total_amt !== null
                    ? (float) $master->pms_total_amt
                    : 0.0,
                'pms_status' => (string) ($master->pms_status ?? ''),
            ],
            'lines' => $lines->map(fn ($l) => [
                'pcd_id' => (int) $l->pcd_id,
                'pcd_receipt_no' => (string) ($l->pcd_receipt_no ?? ''),
                'pcd_trans_desc' => (string) ($l->pcd_trans_desc ?? ''),
                'pcd_trans_amt' => $l->pcd_trans_amt !== null ? (float) $l->pcd_trans_amt : 0.0,
                'pcd_status' => (string) ($l->pcd_status ?? ''),
                'pcm_id' => (int) ($l->pcm_id ?? 0),
                'pcm_payto_id' => (string) ($l->pcm_payto_id ?? ''),
                'pcm_payto_name' => (string) ($l->pcm_payto_name ?? ''),
                'pcm_max_per_receipt' => $l->pcm_max_per_receipt !== null
                    ? (float) $l->pcm_max_per_receipt
                    : null,
                'fty_fund_type' => (string) ($l->fty_fund_type ?? ''),
                'fty_fund_desc' => (string) ($l->fty_fund_desc ?? ''),
                'at_activity_code' => (string) ($l->at_activity_code ?? ''),
                'at_activity_desc' => (string) ($l->at_activity_description_bm ?? ''),
                'oun_code' => (string) ($l->oun_code ?? ''),
                'oun_desc' => (string) ($l->oun_desc ?? ''),
                'ccr_costcentre' => (string) ($l->ccr_costcentre ?? ''),
                'ccr_costcentre_desc' => (string) ($l->ccr_costcentre_desc ?? ''),
                'cpa_project_no' => (string) ($l->cpa_project_no ?? ''),
                'so_code' => (string) ($l->so_code ?? ''),
                'acm_acct_code' => (string) ($l->acm_acct_code ?? ''),
                'acm_acct_desc' => (string) ($l->acm_acct_desc ?? ''),
            ])->values()->all(),
        ]);
    }

    // -------- Write --------

    /**
     * Legacy `?save=1` branch, minus the workflowSubmit SP call.
     *
     * - Creates or updates `petty_cash_master` (legacy sets `pms_status='ENTRY'`
     *   unconditionally; we preserve that for new rows and keep whatever
     *   existing status for updates).
     * - Re-syncs `petty_cash_details`: existing ids are UPDATEd, others
     *   INSERTed. Rows removed by the UI are deleted.
     * - Recomputes `pms_total_amt` from the persisted detail sum (mirrors
     *   the legacy client-side sum + `$_POST['pms_total_amt']`).
     */
    public function saveDraft(SavePettyCashClaimRequest $request): JsonResponse
    {
        $data = $request->validated();
        $head = $data['head'];
        $lines = $data['lines'];
        $username = $this->currentUsername();
        $now = now()->format('Y-m-d H:i:s');

        $masterId = isset($head['pms_id']) ? (int) $head['pms_id'] : 0;
        $isNew = $masterId <= 0;

        $requestDate = $this->parseLegacyDate($head['pms_request_date']);

        $result = DB::connection('mysql_secondary')->transaction(
            function () use ($isNew, $masterId, $head, $lines, $username, $now, $requestDate) {
                // Resolve application number. Legacy uses the selected pcm →
                // oun_code for `PC/{oun_code}/{padded refNo}`. When the user
                // skips Petty Cash Main we fall back to the first line's
                // `oun_code` (always required on a line via validation).
                $headerPcmId = isset($head['pcm_id']) ? (int) $head['pcm_id'] : 0;
                $ounCode = 'NA';
                if ($headerPcmId > 0) {
                    $firstPcm = DB::connection('mysql_secondary')
                        ->table('petty_cash_main')
                        ->where('pcm_id', $headerPcmId)
                        ->first(['oun_code']);
                    $ounCode = (string) ($firstPcm->oun_code ?? 'NA');
                }
                if ($ounCode === 'NA' || $ounCode === '') {
                    $ounCode = (string) ($lines[0]['oun_code'] ?? 'NA');
                }

                if ($isNew) {
                    $masterId = $this->nextSeq('petty_cash_master', 'pms_id');
                    $applicationNo = $head['pms_application_no'] !== null
                        && $head['pms_application_no'] !== ''
                        ? (string) $head['pms_application_no']
                        : $this->generateApplicationNo($ounCode);
                } else {
                    $existing = DB::connection('mysql_secondary')
                        ->table('petty_cash_master')
                        ->where('pms_id', $masterId)
                        ->first(['pms_application_no', 'pms_status']);
                    if (! $existing) {
                        throw new \RuntimeException('Petty cash application not found');
                    }
                    $applicationNo = (string) $existing->pms_application_no;
                }

                $ext = [
                    'pms_request_by_desc' => (string) ($head['pms_request_by_desc'] ?? ''),
                ];

                $masterPayload = [
                    'pms_application_no' => $applicationNo,
                    'pms_request_by' => (string) $head['pms_request_by'],
                    'pms_request_by_desc' => (string) ($head['pms_request_by_desc'] ?? ''),
                    'pms_request_date' => $requestDate,
                    'pms_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                ];

                if ($isNew) {
                    DB::connection('mysql_secondary')
                        ->table('petty_cash_master')
                        ->insert(array_merge($masterPayload, [
                            'pms_id' => $masterId,
                            'pms_status' => 'ENTRY',
                            'pms_total_amt' => 0,
                            'createdby' => $username,
                            'createddate' => $now,
                        ]));
                } else {
                    DB::connection('mysql_secondary')
                        ->table('petty_cash_master')
                        ->where('pms_id', $masterId)
                        ->update(array_merge($masterPayload, [
                            'updatedby' => $username,
                            'updateddate' => $now,
                        ]));
                }

                // Re-sync line items.
                $keepIds = [];
                foreach ($lines as $line) {
                    $pcdId = isset($line['pcd_id']) ? (int) $line['pcd_id'] : 0;
                    // Lines are allowed without a Petty Cash Main reference;
                    // persist as NULL rather than 0 to avoid colliding with
                    // a real `petty_cash_main.pcm_id = 0` row.
                    $linePcmId = isset($line['pcm_id']) && (int) $line['pcm_id'] > 0
                        ? (int) $line['pcm_id']
                        : null;
                    $linePayload = [
                        'pms_application_no' => $applicationNo,
                        'pcd_receipt_no' => (string) $line['pcd_receipt_no'],
                        'pcd_trans_desc' => (string) $line['pcd_trans_desc'],
                        'pcd_trans_amt' => (float) $line['pcd_trans_amt'],
                        'pcm_id' => $linePcmId,
                        'fty_fund_type' => $line['fty_fund_type'] ?? null,
                        'at_activity_code' => $line['at_activity_code'] ?? null,
                        'oun_code' => $line['oun_code'] ?? null,
                        'ccr_costcentre' => $line['ccr_costcentre'] ?? null,
                        'cpa_project_no' => $line['cpa_project_no'] ?? null,
                        'so_code' => $line['so_code'] ?? null,
                        'acm_acct_code' => $line['acm_acct_code'] ?? null,
                    ];

                    if ($pcdId > 0 && ! $isNew) {
                        DB::connection('mysql_secondary')
                            ->table('petty_cash_details')
                            ->where('pcd_id', $pcdId)
                            ->update(array_merge($linePayload, [
                                'updatedby' => $username,
                                'updateddate' => $now,
                            ]));
                        $keepIds[] = $pcdId;
                    } else {
                        $newPcdId = $this->nextSeq('petty_cash_details', 'pcd_id');
                        DB::connection('mysql_secondary')
                            ->table('petty_cash_details')
                            ->insert(array_merge($linePayload, [
                                'pcd_id' => $newPcdId,
                                'pcd_status' => 'ENTRY',
                                'createdby' => $username,
                                'createddate' => $now,
                            ]));
                        $keepIds[] = $newPcdId;
                    }
                }

                if (! $isNew) {
                    DB::connection('mysql_secondary')
                        ->table('petty_cash_details')
                        ->where('pms_application_no', $applicationNo)
                        ->when($keepIds !== [], fn ($q) => $q->whereNotIn('pcd_id', $keepIds))
                        ->delete();
                }

                // Recompute master total from persisted line sum.
                $total = (float) DB::connection('mysql_secondary')
                    ->table('petty_cash_details')
                    ->where('pms_application_no', $applicationNo)
                    ->sum('pcd_trans_amt');

                DB::connection('mysql_secondary')
                    ->table('petty_cash_master')
                    ->where('pms_id', $masterId)
                    ->update(['pms_total_amt' => $total]);

                return [
                    'pms_id' => $masterId,
                    'pms_application_no' => $applicationNo,
                    'pms_total_amt' => $total,
                ];
            }
        );

        return $this->sendOk([
            'status' => 'ok',
            'pms_id' => (int) $result['pms_id'],
            'pms_application_no' => (string) $result['pms_application_no'],
            'pms_total_amt' => (float) $result['pms_total_amt'],
            'pms_status' => 'ENTRY',
            'workflow_stub' => true,
        ]);
    }

    public function submit(int $id): JsonResponse
    {
        $master = DB::connection('mysql_secondary')
            ->table('petty_cash_master')
            ->where('pms_id', $id)
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Petty cash application not found');
        }

        $ext = $this->decodeJson($master->pms_extended_field ?? null);
        $ext['pms_submitted_at'] = now()->toAtomString();

        DB::connection('mysql_secondary')
            ->table('petty_cash_master')
            ->where('pms_id', $id)
            ->update([
                'pms_status' => 'ENTRY',
                'pms_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'updatedby' => $this->currentUsername(),
                'updateddate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->sendOk([
            'status' => 'ok',
            'pms_status' => 'ENTRY',
            'workflow_stub' => true,
            'message' => 'Petty cash application marked as Entry. Workflow routing is not yet migrated; approver chain must be configured in a later release.',
        ]);
    }

    public function cancel(CancelPettyCashClaimRequest $request, int $id): JsonResponse
    {
        $master = DB::connection('mysql_secondary')
            ->table('petty_cash_master')
            ->where('pms_id', $id)
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Petty cash application not found');
        }

        $ext = $this->decodeJson($master->pms_extended_field ?? null);
        $ext['pms_cancel_reason'] = (string) $request->validated()['cancel_reason'];
        $ext['pms_cancelled_at'] = now()->toAtomString();
        $ext['pms_cancelled_by'] = $this->currentUsername();

        DB::connection('mysql_secondary')
            ->table('petty_cash_master')
            ->where('pms_id', $id)
            ->update([
                'pms_status' => 'CANCELLED',
                'pms_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'updatedby' => $this->currentUsername(),
                'updateddate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->sendOk([
            'status' => 'ok',
            'pms_status' => 'CANCELLED',
            'message' => 'Petty cash application cancelled.',
        ]);
    }

    public function processFlow(int $id): JsonResponse
    {
        $exists = DB::connection('mysql_secondary')
            ->table('petty_cash_master')
            ->where('pms_id', $id)
            ->exists();
        if (! $exists) {
            return $this->sendError(404, 'NOT_FOUND', 'Petty cash application not found');
        }

        return $this->sendOk([], [
            'workflow_stub' => true,
            'note' => 'Workflow history tables are not yet migrated.',
        ]);
    }

    // -------- Helpers --------

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
        // Legacy form emits d/m/Y strings; pass through ISO when supplied.
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $raw, $m)) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }
        if (preg_match('#^\d{4}-\d{2}-\d{2}#', $raw)) {
            return substr($raw, 0, 10);
        }
        return $raw;
    }

    /**
     * Emulate legacy `getSeqNo(table)` — bumps the max id in the table.
     * Used inside a transaction by `saveDraft` so concurrent saves don't
     * reuse the same id.
     */
    private function nextSeq(string $table, string $col): int
    {
        $max = (int) DB::connection('mysql_secondary')->table($table)->max($col);
        return $max + 1;
    }

    /**
     * Emulate legacy `PC/{ounCode}/{7-digit pad}` format produced by
     * `getRefNo("PC_$ounCode")`. We count existing applications whose
     * number starts with the same `PC/{ounCode}/` prefix and increment
     * by 1 — deterministic until the real FIMS ref_no table is wired in.
     */
    private function generateApplicationNo(string $ounCode): string
    {
        $prefix = sprintf('PC/%s/', $ounCode);
        $existing = DB::connection('mysql_secondary')
            ->table('petty_cash_master')
            ->where('pms_application_no', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $prefix).'%')
            ->count();
        return sprintf('%s%07d', $prefix, $existing + 1);
    }

    private function currentUsername(): string
    {
        $user = Auth::user();
        if (! $user) {
            return 'system';
        }
        return (string) ($user->email ?? $user->name ?? 'system');
    }

    private function likeEscape(string $needleLower): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needleLower).'%';
    }
}
