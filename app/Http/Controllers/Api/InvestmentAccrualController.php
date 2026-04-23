<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostAccrualToTbRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\InvestmentAccrual;
use App\Models\InvestmentInstitution;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Investment > Accrual (PAGEID 1175 / MENUID 1446).
 *
 * Source: FIMS BL `API_INVESTMENT_ACCRUAL` (default listing action).
 *
 * Read-only datatable joining `investment_accrual`,
 * `investment_institution`, and `investment_profile` on
 * `mysql_secondary`. Scope matches the legacy `$common` block:
 *   - `iac_start_date <= current_date()`
 *   - `pmt_posting_no IS NULL`
 *
 * Global search mirrors the legacy CONCAT_WS needle over:
 *   ipf_investment_no, iit_inst_code, iit_inst_name, iit_bank_branch,
 *   iac_start_date, iac_end_date, iac_amount, pmt_posteddate, ipf_rate,
 *   ipf_amt_per_day, pmt_posting_no, ipf_no_of_days.
 *
 * Smart filter (6 fields actively wired by legacy SQL):
 *   - Investment No      (ipf_investment_no LIKE)
 *   - Institution Code   (ii.iit_inst_code LIKE)
 *   - Institution Name   (ii.iit_inst_name LIKE)
 *   - Branch             (ii.iit_bank_branch LIKE)
 *   - No of Days         (ipf_no_of_days LIKE)
 *   - Rate (%)           (ia.ipf_rate LIKE)
 * (Amount and Amount-per-day smart fields were commented out in the
 * legacy BL so we omit them here — if the legacy behaviour is revived
 * just uncomment the corresponding block below.)
 *
 * Meta mirrors the legacy footer:
 *   - `grandTotalAmount`     = SUM(iac_amount)
 *   - `grandTotalAmtPerDay`  = SUM(ipf_amt_per_day)
 *
 * Action column (legacy header checkbox + "Post to TB" button)
 * mirrors `INSERT_UPDATE_INVESTMENT_ACCRUAL` default branch —
 * inserts into `posting_master` / `posting_details` and calls the
 * `getTableSequenceNum` / `getRefNoByCurrentYear` stored procs on
 * `mysql_secondary`. See {@see postToTb()}.
 */
class InvestmentAccrualController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    public function __construct(
        protected AuditService $auditService,
    ) {}

    /** Allowed sort keys, mapped to concrete SQL expressions in index(). */
    private const SORTABLE = [
        'dt_invest_no',
        'dt_inst_code',
        'dt_inst_name',
        'dt_branch',
        'dt_amount',
        'dt_no_of_days',
        'dt_amt_per_day',
        'dt_rate',
        'dt_posting_no',
    ];

    public function options(): JsonResponse
    {
        // Institutions that actually appear in investment_accrual rows
        // kept the scope (iac_start_date <= current_date AND
        // pmt_posting_no IS NULL) so the dropdown reflects the listing.
        $institutions = InvestmentInstitution::query()
            ->from('investment_institution as iit')
            ->select(['iit.iit_inst_code', 'iit.iit_inst_name', 'iit.iit_bank_branch'])
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('investment_accrual as ia')
                    ->join('investment_profile as ipf', function ($join) {
                        $join->on(
                            DB::raw($this->cs('ia.ipf_investment_no')),
                            '=',
                            DB::raw($this->cs('ipf.ipf_investment_no')),
                        );
                    })
                    ->whereRaw(
                        $this->cs('ipf.iit_inst_code').' = '.$this->cs('iit.iit_inst_code')
                    )
                    ->whereRaw('ia.iac_start_date <= current_date()')
                    ->whereNull('ia.pmt_posting_no');
            })
            ->whereNotNull('iit.iit_inst_code')
            ->where('iit.iit_inst_code', '!=', '')
            ->orderBy('iit.iit_inst_code')
            ->get()
            ->map(fn ($i) => [
                'id' => (string) $i->iit_inst_code,
                'label' => '['.$i->iit_inst_code.'] '
                    .($i->iit_inst_name ?? '')
                    .($i->iit_bank_branch ? ' - '.$i->iit_bank_branch : ''),
            ])
            ->values();

        return $this->sendOk([
            'institution' => $institutions,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dt_invest_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'dt_invest_no';
        }

        $filterInvestNo = trim((string) $request->input('filter_invest_no', ''));
        $filterInstCode = trim((string) $request->input('filter_inst_code', ''));
        $filterInstName = trim((string) $request->input('filter_inst_name', ''));
        $filterBranch = trim((string) $request->input('filter_branch', ''));
        $filterNoOfDays = trim((string) $request->input('filter_no_of_days', ''));
        $filterRate = trim((string) $request->input('filter_rate', ''));

        // Same collation-safe treatment used by ListOfAccrualController —
        // mysql_secondary mixes utf8mb3 and utf8mb4 tables, so every
        // text expression is wrapped with
        // CONVERT(<expr> USING utf8mb4) COLLATE utf8mb4_unicode_ci
        // via the CollationSafeSql trait.
        $base = InvestmentAccrual::query()
            ->from('investment_accrual as ia')
            ->join('investment_profile as ipf', function ($join) {
                $join->on(
                    DB::raw($this->cs('ia.ipf_investment_no')),
                    '=',
                    DB::raw($this->cs('ipf.ipf_investment_no')),
                );
            })
            ->join('investment_institution as iit', function ($join) {
                $join->on(
                    DB::raw($this->cs('ipf.iit_inst_code')),
                    '=',
                    DB::raw($this->cs('iit.iit_inst_code')),
                );
            })
            ->whereRaw('ia.iac_start_date <= current_date()')
            ->whereNull('ia.pmt_posting_no');

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $concatParts = [
                $this->cs("IFNULL(ia.ipf_investment_no, '')"),
                $this->cs("IFNULL(iit.iit_inst_code, '')"),
                $this->cs("IFNULL(iit.iit_inst_name, '')"),
                $this->cs("IFNULL(iit.iit_bank_branch, '')"),
                $this->cs("IFNULL(DATE_FORMAT(ia.iac_start_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(DATE_FORMAT(ia.iac_end_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(CAST(ia.iac_amount AS CHAR), '')"),
                $this->cs("IFNULL(DATE_FORMAT(ia.pmt_posteddate, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(CAST(ia.ipf_rate AS CHAR), '')"),
                $this->cs("IFNULL(CAST(ia.ipf_amt_per_day AS CHAR), '')"),
                $this->cs("IFNULL(ia.pmt_posting_no, '')"),
                $this->cs("IFNULL(CAST(ia.ipf_no_of_days AS CHAR), '')"),
            ];
            $concat = "CONCAT_WS('__', ".implode(', ', $concatParts).')';
            $base->whereRaw("LOWER($concat) LIKE ?", [$like]);
        }

        if ($filterInvestNo !== '') {
            $like = $this->likeEscape(mb_strtolower($filterInvestNo, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(ia.ipf_investment_no, '')").') LIKE ?',
                [$like]
            );
        }
        if ($filterInstCode !== '') {
            $like = $this->likeEscape(mb_strtolower($filterInstCode, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(iit.iit_inst_code, '')").') LIKE ?',
                [$like]
            );
        }
        if ($filterInstName !== '') {
            $like = $this->likeEscape(mb_strtolower($filterInstName, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(iit.iit_inst_name, '')").') LIKE ?',
                [$like]
            );
        }
        if ($filterBranch !== '') {
            $like = $this->likeEscape(mb_strtolower($filterBranch, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(iit.iit_bank_branch, '')").') LIKE ?',
                [$like]
            );
        }
        if ($filterNoOfDays !== '') {
            $like = $this->likeEscape(mb_strtolower($filterNoOfDays, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(CAST(ia.ipf_no_of_days AS CHAR), '')").') LIKE ?',
                [$like]
            );
        }
        if ($filterRate !== '') {
            $like = $this->likeEscape(mb_strtolower($filterRate, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(CAST(ia.ipf_rate AS CHAR), '')").') LIKE ?',
                [$like]
            );
        }

        $total = (clone $base)->count();

        // Footer aggregates mirror the legacy $rs[0]['G'] / $rs[0]['A'].
        $aggregates = (clone $base)
            ->selectRaw('COALESCE(SUM(ia.iac_amount), 0) as grand_amount, COALESCE(SUM(ia.ipf_amt_per_day), 0) as grand_amt_per_day')
            ->first();
        $grandAmount = $aggregates ? (float) $aggregates->grand_amount : 0.0;
        $grandAmtPerDay = $aggregates ? (float) $aggregates->grand_amt_per_day : 0.0;

        $orderColumn = match ($sortBy) {
            'dt_invest_no' => 'ia.ipf_investment_no',
            'dt_inst_code' => 'iit.iit_inst_code',
            'dt_inst_name' => 'iit.iit_inst_name',
            'dt_branch' => 'iit.iit_bank_branch',
            'dt_amount' => 'ia.iac_amount',
            'dt_no_of_days' => 'ia.ipf_no_of_days',
            'dt_amt_per_day' => 'ia.ipf_amt_per_day',
            'dt_rate' => 'ia.ipf_rate',
            'dt_posting_no' => 'ia.pmt_posting_no',
            default => 'ia.ipf_investment_no',
        };

        $rows = (clone $base)
            ->select([
                'ia.iac_id',
                'ia.ipf_investment_no',
                'iit.iit_inst_code',
                'iit.iit_inst_name',
                'iit.iit_bank_branch',
                DB::raw("DATE_FORMAT(ia.iac_start_date, '%d/%m/%Y') as start_date_fmt"),
                DB::raw("DATE_FORMAT(ia.iac_end_date, '%d/%m/%Y') as end_date_fmt"),
                DB::raw("DATE_FORMAT(ia.createddate, '%d/%m/%Y') as created_date_fmt"),
                'ia.iac_amount',
                'ia.ipf_no_of_days',
                'ia.ipf_amt_per_day',
                'ia.ipf_rate',
                'ia.pmt_posting_no',
            ])
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('ia.iac_id', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            // row.ID in legacy was CONCAT(ipf_investment_no,'_',iac_id).
            // We surface both parts for clients that need to build the
            // same identifier (Post-to-TB payload, etc.).
            'accrualId' => $r->iac_id !== null ? (int) $r->iac_id : null,
            'rowId' => $r->iac_id !== null
                ? ($r->ipf_investment_no ?? '').'_'.(int) $r->iac_id
                : null,
            'investmentNo' => $r->ipf_investment_no,
            'institutionCode' => $r->iit_inst_code,
            'institutionName' => $r->iit_inst_name,
            'institutionBranch' => $r->iit_bank_branch,
            'startDate' => $r->start_date_fmt,
            'endDate' => $r->end_date_fmt,
            'createdDate' => $r->created_date_fmt,
            'amount' => $r->iac_amount !== null ? (float) $r->iac_amount : null,
            'noOfDays' => $r->ipf_no_of_days !== null ? (int) $r->ipf_no_of_days : null,
            'amtPerDay' => $r->ipf_amt_per_day !== null ? (float) $r->ipf_amt_per_day : null,
            'rate' => $r->ipf_rate !== null ? (float) $r->ipf_rate : null,
            // Legacy displays pmt_posting_no with d-none; we keep the
            // field available to the client but the view hides it.
            'postingNo' => $r->pmt_posting_no,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'grandTotalAmount' => $grandAmount,
            'grandTotalAmtPerDay' => $grandAmtPerDay,
        ]);
    }

    /**
     * Post one or more accrual rows to Trial Balance.
     *
     * Mirrors legacy `INSERT_UPDATE_INVESTMENT_ACCRUAL` default /
     * `else` branch (Investment > Accrual action):
     *
     *   foreach ($_POST['ipf_investment'] as "{$no}_{$iac_id}" => _) {
     *       // 1. Verify investment_acct_setup row exists for the
     *       //    investment's ivt_type_code.
     *       // 2. CALL getTableSequenceNum('posting_master', @SEQ).
     *       // 3. CALL getRefNoByCurrentYear('POSTING_TO_TB', @SEQ).
     *       // 4. INSERT INTO posting_master(...pmt_status='APPROVE'...).
     *       // 5. UPDATE investment_accrual SET pmt_posting_no=...,
     *       //          pmt_posteddate=now().
     *       // 6. For each of ['DT','CR']:
     *       //       CALL getTableSequenceNum('posting_details', @SEQ),
     *       //       INSERT INTO posting_details(...).
     *   }
     *
     * The stored procedures live on `mysql_secondary` and own their
     * own sequence tables; we do not emulate them in-app so every
     * posting gets a cluster-wide unique id / ref no.
     *
     * Defensive re-validation per accrual (not in the legacy code —
     * legacy silently no-ops when the row's start date is today
     * because the INSERT's WHERE uses strict `<` current_date()):
     *   - Accrual exists and has `pmt_posting_no IS NULL`.
     *   - `iac_start_date < current_date()` (matches legacy WHERE).
     *   - Matching `investment_profile` row exists.
     *   - `investment_acct_setup` has a row for the profile's
     *     `ivt_type_code` (legacy guard that returns
     *     `status=ko, mode=checking` in the BL).
     *
     * Each id is processed independently in try/catch so a single
     * stored-procedure failure does not abort the batch. Response
     * enumerates both successes (with the generated pmt_posting_no)
     * and failures (with an explanatory reason).
     *
     * Audit: one entry per successful post, action
     * `investment.accrual.posted_to_tb`, keyed to the accrual record
     * (InvestmentAccrual model, iac_id) with the new pmt_posting_no /
     * pmt_posting_id captured in newValues for reconciliation.
     */
    public function postToTb(PostAccrualToTbRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $ids = array_values(array_unique(array_map('intval', $validated['accrual_ids'])));
        $ids = array_values(array_filter($ids, fn (int $n) => $n > 0));

        if (empty($ids)) {
            return $this->sendError(
                422,
                'VALIDATION_ERROR',
                'No accrual ids provided.',
                ['accrual_ids' => ['At least one accrual must be selected.']],
            );
        }

        $conn = DB::connection('mysql_secondary');

        // Legacy: `SELECT org_code FROM organization WHERE org_status = 1`.
        // Fetched once per request — the active org code doesn't change
        // mid-post and we don't want to read it per-row.
        $orgCode = (string) ($conn->table('organization')
            ->where('org_status', 1)
            ->value('org_code') ?? '');

        if ($orgCode === '') {
            return $this->sendError(
                500,
                'INTERNAL_ERROR',
                'Active organization (org_status=1) not configured.',
            );
        }

        $username = $this->currentUsername();
        $processed = [];
        $failed = [];

        foreach ($ids as $iacId) {
            // 1. Resolve the accrual + page-scope guards. Use raw SELECT
            //    (not Eloquent) so we can pull the related profile /
            //    acct-setup columns in a single round-trip and mirror
            //    the legacy's direct-SQL style while still benefitting
            //    from parameter binding.
            $accrual = $conn->table('investment_accrual as ia')
                ->leftJoin('investment_profile as ip', 'ip.ipf_investment_no', '=', 'ia.ipf_investment_no')
                ->leftJoin('investment_acct_setup as ias', 'ias.ivt_type_code', '=', 'ip.ivt_type_code')
                ->where('ia.iac_id', $iacId)
                ->select([
                    'ia.iac_id',
                    'ia.ipf_investment_no',
                    'ia.iac_start_date',
                    'ia.iac_amount',
                    'ia.pmt_posting_no as existing_posting_no',
                    'ip.ivt_type_code',
                    'ias.ivt_type_code as has_acct_setup',
                    'ias.oun_code',
                    'ias.fty_fund_type',
                    'ias.at_activity_code',
                    'ias.ccr_costcentre',
                    'ias.acm_acct_code_acrual',
                    'ias.acm_acct_code_interest',
                    'ias.cpa_project_no',
                ])
                ->first();

            if (! $accrual) {
                $failed[] = [
                    'accrualId' => $iacId,
                    'investmentNo' => null,
                    'reason' => 'Accrual not found.',
                ];
                continue;
            }

            $investmentNo = (string) ($accrual->ipf_investment_no ?? '');

            if (! empty($accrual->existing_posting_no)) {
                $failed[] = [
                    'accrualId' => $iacId,
                    'investmentNo' => $investmentNo,
                    'reason' => 'Accrual already posted (posting no '.$accrual->existing_posting_no.').',
                ];
                continue;
            }

            // Legacy WHERE clause uses strict `<` on the insert —
            // mirror it so same-day accruals cannot be posted until
            // tomorrow (matches the posting calendar convention).
            $startDate = $accrual->iac_start_date !== null
                ? substr((string) $accrual->iac_start_date, 0, 10)
                : null;
            if ($startDate === null || $startDate >= now()->format('Y-m-d')) {
                $failed[] = [
                    'accrualId' => $iacId,
                    'investmentNo' => $investmentNo,
                    'reason' => 'Accrual start date must be before today to post to TB.',
                ];
                continue;
            }

            if (empty($accrual->ivt_type_code)) {
                $failed[] = [
                    'accrualId' => $iacId,
                    'investmentNo' => $investmentNo,
                    'reason' => 'Investment profile or type not found.',
                ];
                continue;
            }

            if (empty($accrual->has_acct_setup)) {
                // Mirror legacy `mode=checking` reason message so
                // operators see the same text they're used to.
                $failed[] = [
                    'accrualId' => $iacId,
                    'investmentNo' => $investmentNo,
                    'reason' => 'There are no investment type code ('.$accrual->ivt_type_code.') for investment number '.$investmentNo,
                ];
                continue;
            }

            try {
                // 2. pmt_posting_id from sequence proc.
                $pmtPostingId = $this->nextTableSequence('posting_master');
                // 3. pmt_posting_no from ref-no proc.
                $pmtPostingNo = $this->nextRefNoByCurrentYear('POSTING_TO_TB');

                // 4. INSERT INTO posting_master. Legacy uses SELECT ...
                //    FROM investment_accrual to carry iac_amount — we
                //    already have the value in $accrual so INSERT
                //    directly.
                $conn->table('posting_master')->insert([
                    'pmt_posting_id' => $pmtPostingId,
                    'pmt_posting_no' => $pmtPostingNo,
                    'pmt_system_id' => 'INVEST',
                    'org_code' => $orgCode,
                    'pmt_status' => 'APPROVE',
                    'pmt_total_amt' => $accrual->iac_amount,
                    'createddate' => now(),
                    'createdby' => $username,
                ]);

                // 5. UPDATE investment_accrual. Same WHERE/keys as
                //    legacy.
                $conn->table('investment_accrual')
                    ->where('iac_id', $iacId)
                    ->update([
                        'pmt_posting_no' => $pmtPostingNo,
                        'pmt_posteddate' => now(),
                        'updatedby' => $username,
                    ]);

                // 6. posting_details rows — one DT, one CR.
                foreach (['DT', 'CR'] as $transType) {
                    $pdePostingDetlId = $this->nextTableSequence('posting_details');
                    $acctCode = $transType === 'DT'
                        ? $accrual->acm_acct_code_acrual
                        : $accrual->acm_acct_code_interest;

                    $conn->table('posting_details')->insert([
                        'pde_posting_detl_id' => $pdePostingDetlId,
                        'pmt_posting_id' => $pmtPostingId,
                        'oun_code' => $accrual->oun_code,
                        'fty_fund_type' => $accrual->fty_fund_type,
                        'at_activity_code' => $accrual->at_activity_code,
                        'ccr_costcentre' => $accrual->ccr_costcentre,
                        'acm_acct_code' => $acctCode,
                        'cpa_project_no' => $accrual->cpa_project_no,
                        'pde_document_no' => $investmentNo,
                        'pde_trans_type' => $transType,
                        'pde_trans_amt' => $accrual->iac_amount,
                        'pde_status' => 'APPROVE',
                        'pde_trans_date' => now(),
                        'createddate' => now(),
                        'createdby' => $username,
                    ]);
                }

                $this->auditService->log(
                    'investment.accrual.posted_to_tb',
                    $request->user(),
                    'InvestmentAccrual',
                    $iacId,
                    null,
                    [
                        'ipf_investment_no' => $investmentNo,
                        'pmt_posting_id' => $pmtPostingId,
                        'pmt_posting_no' => $pmtPostingNo,
                        'pmt_total_amt' => $accrual->iac_amount,
                    ],
                );

                $processed[] = [
                    'accrualId' => $iacId,
                    'investmentNo' => $investmentNo,
                    'postingNo' => (string) $pmtPostingNo,
                    'amount' => $accrual->iac_amount !== null ? (float) $accrual->iac_amount : null,
                ];
            } catch (Throwable $e) {
                // Keep going — partial success still useful. Surface
                // the SQLSTATE first line only; callers don't need the
                // full PDO stack.
                $reason = strtok($e->getMessage(), "\n") ?: 'Post-to-TB failed.';
                $failed[] = [
                    'accrualId' => $iacId,
                    'investmentNo' => $investmentNo,
                    'reason' => $reason,
                ];
            }
        }

        return $this->sendOk([
            'processed' => $processed,
            'failed' => $failed,
            'successCount' => count($processed),
            'failureCount' => count($failed),
        ]);
    }

    /**
     * Invoke the legacy `getTableSequenceNum(<table>, @SEQ)` proc and
     * return the integer sequence. The proc lives on
     * `mysql_secondary`; we use `unprepared()` + a session-var read
     * on the same connection because CALL statements with OUT params
     * do not play nicely with PDO prepared statements.
     */
    private function nextTableSequence(string $table): int
    {
        $conn = DB::connection('mysql_secondary');
        // Use bindings via statement() — parameter-bind the table name
        // through the proc's own arg to avoid literal concatenation.
        $conn->statement('CALL getTableSequenceNum(?, @SEQ)', [$table]);
        $row = $conn->selectOne('SELECT @SEQ AS seq');
        $seq = is_object($row) ? ($row->seq ?? null) : null;
        if ($seq === null) {
            throw new \RuntimeException(
                "getTableSequenceNum returned no value for table '$table'."
            );
        }
        return (int) $seq;
    }

    /**
     * Invoke the legacy `getRefNoByCurrentYear(<module>, @SEQ)` proc
     * and return the generated reference number (string, formatted by
     * the proc — e.g. `POSTING_TO_TB/2026/0001`).
     */
    private function nextRefNoByCurrentYear(string $module): string
    {
        $conn = DB::connection('mysql_secondary');
        $conn->statement('CALL getRefNoByCurrentYear(?, @SEQ)', [$module]);
        $row = $conn->selectOne('SELECT @SEQ AS seq');
        $seq = is_object($row) ? ($row->seq ?? null) : null;
        if ($seq === null || $seq === '') {
            throw new \RuntimeException(
                "getRefNoByCurrentYear returned no value for module '$module'."
            );
        }
        return (string) $seq;
    }

    /**
     * Username used for `createdby`/`updatedby` legacy audit columns.
     * Matches the pattern from CreditNoteFormController and keeps the
     * column readable when the user's email isn't available (seeded
     * accounts, CLI contexts, etc.).
     */
    private function currentUsername(): string
    {
        $user = Auth::user();
        if (! $user) {
            return 'system';
        }
        return (string) ($user->email ?? $user->name ?? 'system');
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
