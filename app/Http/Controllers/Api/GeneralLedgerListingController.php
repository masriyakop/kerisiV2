<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger > General Ledger Listing (PAGEID 2068 / MENUID 2519).
 *
 * Legacy BL: `NAD_API_GL_LISTINGPOSTINGTOGL` (+ `ZR_GL_LISTINGPOSTINGTOGL_BL`
 * sibling) — a line-level datatable over posting_master / posting_details
 * joined with five self-joined levels of account_main for the l1..l4 account
 * hierarchy columns, plus lookup joins to fund_type, activity_type,
 * organization_unit, costcentre, capital_project, and lookup_details for
 * CUSTOMER_TYPE descriptions.
 *
 * Legacy page ships two separate filter forms — `Form (Top Filter)` (17
 * fields) and `Form (Smart Filter)` (16 fields). Following the precedent of
 * every other migrated Kerisi list (Journal Listing, Status PO & PR,
 * Posting to GL (TB)) both forms are consolidated into a single smart filter
 * modal on the client. Every legacy filter key is still accepted here to
 * preserve parity with `NAD_API_GL_LISTINGPOSTINGTOGL`.
 *
 * Read-only: legacy BL has NO insert/update/delete branches. Only
 * `index` and `options` are exposed.
 */
class GeneralLedgerListingController extends Controller
{
    use ApiResponse;

    /** Columns clients may sort by; mapped to aliased SQL columns in sortColumn(). */
    private const SORTABLE = [
        'pmt_posting_no',
        'pde_document_no',
        'pde_trans_date',
        'pmt_posteddate',
        'pde_trans_amt',
        'acm_acct_code',
        'pmt_system_id',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'acm_acct_code');
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'acm_acct_code';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $builder = $this->buildFilteredJoin($request, $q);

        $total = (int) (clone $builder)->count('pd.pde_posting_detl_id');

        // Footer: signed total (DT positive, CR negative) across the full filter.
        $footerAmount = (clone $builder)
            ->selectRaw("SUM(CASE WHEN pd.pde_trans_type = 'DT' THEN pd.pde_trans_amt
                                  WHEN pd.pde_trans_type = 'CR' THEN -pd.pde_trans_amt
                                  ELSE 0 END) AS total_amt")
            ->value('total_amt');

        $rows = (clone $builder)
            ->select([
                'pd.pde_posting_detl_id',
                'pm.pmt_posting_id',
                'pm.pmt_posting_no',
                'pm.pmt_posteddate',
                'pm.pmt_system_id',
                'pm.createdby AS created_by',
                'pd.pde_document_no',
                'pd.pde_doc_description',
                'pd.fty_fund_type',
                'ft.fty_fund_desc',
                'pd.at_activity_code',
                'ats.at_activity_description_bm',
                'pd.oun_code',
                'ou.oun_desc',
                'pd.ccr_costcentre',
                'cc.ccr_costcentre_desc',
                DB::raw('SUBSTRING(pd.cpa_project_no, 17, 21) AS so_code'),
                'cp.cpa_project_desc',
                'pd.acm_acct_code',
                'am.acm_acct_desc',
                'am.acm_acct_activity',
                'am.acm_behavior',
                'l1.acm_acct_code AS acm_account_class',
                'l2.acm_acct_code AS acm_account_subclass',
                'l3.acm_acct_code AS acm_account_series',
                'l4.acm_acct_code AS acm_account_subseries',
                DB::raw("CASE WHEN pd.pde_trans_type = 'DT' THEN pd.pde_trans_amt
                              WHEN pd.pde_trans_type = 'CR' THEN -pd.pde_trans_amt END AS pde_trans_amt_signed"),
                'pd.pde_trans_type',
                'pd.pde_reference',
                'pd.pde_reference1',
                'pd.pde_trans_date',
                'pd.pde_payto_type',
                'pd.pde_payto_id',
                'pd.pde_payto_name',
                'ld.lde_description2 AS payto_type_desc',
            ])
            ->orderBy($this->sortColumn($sortBy), $sortDir)
            ->orderBy('pd.pde_posting_detl_id')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = [];
        foreach ($rows as $i => $r) {
            $data[] = [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pdePostingDetlId' => (int) $r->pde_posting_detl_id,
                'pmtPostingId' => (int) $r->pmt_posting_id,
                'postingNo' => $r->pmt_posting_no,
                'documentNo' => $r->pde_document_no !== null ? trim((string) $r->pde_document_no) : null,
                'docDescription' => $r->pde_doc_description !== null ? trim((string) $r->pde_doc_description) : null,
                'fundType' => $r->fty_fund_type,
                'fundDesc' => $r->fty_fund_desc,
                'activityCode' => $r->at_activity_code,
                'activityDesc' => $r->at_activity_description_bm,
                'ounCode' => $r->oun_code,
                'ounDesc' => $r->oun_desc,
                'costCentre' => $r->ccr_costcentre,
                'costCentreDesc' => $r->ccr_costcentre_desc,
                'soCode' => $r->so_code !== null && trim((string) $r->so_code) !== '' ? trim((string) $r->so_code) : null,
                'projectDesc' => $r->cpa_project_desc,
                'acctCode' => $r->acm_acct_code,
                'acctDesc' => $r->acm_acct_desc,
                'acctActivity' => $r->acm_acct_activity,
                'acctBehavior' => $r->acm_behavior,
                'accountClass' => $r->acm_account_class,
                'accountSubclass' => $r->acm_account_subclass,
                'accountSeries' => $r->acm_account_series,
                'accountSubseries' => $r->acm_account_subseries,
                'transAmt' => $r->pde_trans_amt_signed !== null ? (float) $r->pde_trans_amt_signed : 0.0,
                'transType' => $r->pde_trans_type,
                'reference' => $r->pde_reference !== null ? trim((string) $r->pde_reference) : null,
                'reference1' => $r->pde_reference1 !== null ? trim((string) $r->pde_reference1) : null,
                // Legacy dt_key uses `pmt_posteddate` for the Transaction Date / Period columns.
                'postedDate' => $r->pmt_posteddate
                    ? Carbon::parse($r->pmt_posteddate)->format('d/m/Y')
                    : null,
                'postedPeriod' => $r->pmt_posteddate
                    ? Carbon::parse($r->pmt_posteddate)->format('m/Y')
                    : null,
                'transDate' => $r->pde_trans_date
                    ? Carbon::parse($r->pde_trans_date)->format('d/m/Y')
                    : null,
                'payToType' => $this->joinWithSpaces($r->pde_payto_type, $r->payto_type_desc),
                'payToId' => $this->joinWithSpaces($r->pde_payto_id, $r->pde_payto_name),
                'createdBy' => $r->created_by,
                'systemId' => $r->pmt_system_id,
            ];
        }

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => [
                'transAmt' => $footerAmount !== null ? (float) $footerAmount : 0.0,
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        $conn = DB::connection('mysql_secondary');

        $systemIds = $conn->table('posting_master')
            ->whereNotNull('pmt_system_id')
            ->where('pmt_system_id', '!=', '')
            ->distinct()
            ->orderBy('pmt_system_id')
            ->pluck('pmt_system_id')
            ->values()
            ->all();

        $fundTypes = $conn->table('fund_type')
            ->whereNotNull('fty_fund_type')
            ->orderBy('fty_fund_type')
            ->get(['fty_fund_type AS code', 'fty_fund_desc AS description'])
            ->map(fn ($r) => ['code' => (string) $r->code, 'description' => (string) ($r->description ?? '')])
            ->values()
            ->all();

        $activityCodes = $conn->table('activity_type')
            ->whereNotNull('at_activity_code')
            ->orderBy('at_activity_code')
            ->get(['at_activity_code AS code', 'at_activity_description_bm AS description'])
            ->map(fn ($r) => ['code' => (string) $r->code, 'description' => (string) ($r->description ?? '')])
            ->values()
            ->all();

        $ptjL3 = $conn->table('organization_unit')
            ->where('oun_level', 3)
            ->where('oun_status', '1')
            ->orderBy('oun_code')
            ->get(['oun_code AS code', 'oun_desc AS description', 'oun_code_parent AS parent'])
            ->map(fn ($r) => [
                'code' => (string) $r->code,
                'description' => (string) ($r->description ?? ''),
                'parent' => $r->parent ? (string) $r->parent : null,
            ])
            ->values()
            ->all();

        $ptj = $conn->table('organization_unit')
            ->where('oun_status', '1')
            ->orderBy('oun_code')
            ->get(['oun_code AS code', 'oun_desc AS description', 'oun_code_parent AS parent', 'oun_level AS level'])
            ->map(fn ($r) => [
                'code' => (string) $r->code,
                'description' => (string) ($r->description ?? ''),
                'parent' => $r->parent ? (string) $r->parent : null,
                'level' => $r->level !== null ? (int) $r->level : null,
            ])
            ->values()
            ->all();

        $costCentres = $conn->table('costcentre')
            ->where(function ($q) {
                $q->whereNull('ccr_status')->orWhere('ccr_status', '1');
            })
            ->orderBy('ccr_costcentre')
            ->get(['ccr_costcentre AS code', 'ccr_costcentre_desc AS description', 'oun_code AS parent'])
            ->map(fn ($r) => [
                'code' => (string) $r->code,
                'description' => (string) ($r->description ?? ''),
                'parent' => $r->parent ? (string) $r->parent : null,
            ])
            ->values()
            ->all();

        $accountsByLevel = [];
        $acctRows = $conn->table('account_main')
            ->whereNotNull('acm_acct_code')
            ->orderBy('acm_acct_level')
            ->orderBy('acm_acct_code')
            ->get(['acm_acct_code AS code', 'acm_acct_desc AS description', 'acm_acct_parent AS parent', 'acm_acct_level AS level']);
        foreach ($acctRows as $r) {
            $lvl = (int) ($r->level ?? 0);
            if ($lvl < 1 || $lvl > 5) {
                continue;
            }
            $accountsByLevel[$lvl] ??= [];
            $accountsByLevel[$lvl][] = [
                'code' => (string) $r->code,
                'description' => (string) ($r->description ?? ''),
                'parent' => $r->parent ? (string) $r->parent : null,
            ];
        }
        ksort($accountsByLevel);

        $accountTypes = $conn->table('account_main')
            ->whereNotNull('acm_acct_activity')
            ->where('acm_acct_activity', '!=', '')
            ->distinct()
            ->orderBy('acm_acct_activity')
            ->pluck('acm_acct_activity')
            ->values()
            ->all();

        $statementItems = $conn->table('account_main')
            ->whereNotNull('acm_behavior')
            ->where('acm_behavior', '!=', '')
            ->distinct()
            ->orderBy('acm_behavior')
            ->pluck('acm_behavior')
            ->values()
            ->all();

        $transTypes = $conn->table('posting_details')
            ->whereNotNull('pde_trans_type')
            ->where('pde_trans_type', '!=', '')
            ->distinct()
            ->orderBy('pde_trans_type')
            ->pluck('pde_trans_type')
            ->values()
            ->all();

        // Legacy pulls Type Customer from lookup_details with lma_code_name = 'CUSTOMER_TYPE'.
        $payToTypes = $conn->table('lookup_details')
            ->where('lma_code_name', 'CUSTOMER_TYPE')
            ->whereNotNull('lde_value')
            ->orderBy('lde_value')
            ->get(['lde_value AS code', 'lde_description AS description'])
            ->map(fn ($r) => ['code' => (string) $r->code, 'description' => (string) ($r->description ?? '')])
            ->values()
            ->all();

        return $this->sendOk([
            'systemIds' => array_values(array_map('strval', $systemIds)),
            'fundTypes' => $fundTypes,
            'activityCodes' => $activityCodes,
            'ptjL3' => $ptjL3,
            'ptj' => $ptj,
            'costCentres' => $costCentres,
            'accountsByLevel' => $accountsByLevel,
            'accountTypes' => array_values(array_map('strval', $accountTypes)),
            'statementItems' => array_values(array_map('strval', $statementItems)),
            'transTypes' => array_values(array_map('strval', $transTypes)),
            'payToTypes' => $payToTypes,
        ]);
    }

    /**
     * Build the line-level join with legacy-matching APPROVE gate plus
     * all filter columns from the combined Top Filter + Smart Filter.
     * Returns a fresh query builder so the caller can clone it for
     * count/list/footer queries independently.
     */
    private function buildFilteredJoin(Request $request, string $q)
    {
        $conn = DB::connection('mysql_secondary');
        $query = $conn->table('posting_master AS pm')
            ->join('posting_details AS pd', 'pm.pmt_posting_id', '=', 'pd.pmt_posting_id')
            ->join('account_main AS am', 'pd.acm_acct_code', '=', 'am.acm_acct_code')
            // 5-level self-join matching legacy (walks acm_acct_parent chain).
            ->join('account_main AS l5', 'am.acm_acct_code', '=', 'l5.acm_acct_code')
            ->join('account_main AS l4', 'l5.acm_acct_parent', '=', 'l4.acm_acct_code')
            ->join('account_main AS l3', 'l4.acm_acct_parent', '=', 'l3.acm_acct_code')
            ->join('account_main AS l2', 'l3.acm_acct_parent', '=', 'l2.acm_acct_code')
            ->join('account_main AS l1', 'l2.acm_acct_parent', '=', 'l1.acm_acct_code')
            ->leftJoin('organization_unit AS ou', 'pd.oun_code', '=', 'ou.oun_code')
            ->leftJoin('fund_type AS ft', 'pd.fty_fund_type', '=', 'ft.fty_fund_type')
            ->leftJoin('activity_type AS ats', 'pd.at_activity_code', '=', 'ats.at_activity_code')
            ->leftJoin('costcentre AS cc', 'pd.ccr_costcentre', '=', 'cc.ccr_costcentre')
            ->leftJoin('capital_project AS cp', 'pd.cpa_project_no', '=', 'cp.cpa_project_no')
            ->leftJoin('lookup_details AS ld', function ($join) {
                $join->on('pd.pde_payto_type', '=', 'ld.lde_value')
                    ->where('ld.lma_code_name', '=', 'CUSTOMER_TYPE');
            })
            ->where('pd.pde_status', 'APPROVE');

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(pm.pmt_posting_no, ''),
                    IFNULL(pd.pde_document_no, ''),
                    IFNULL(pd.pde_doc_description, ''),
                    IFNULL(pd.fty_fund_type, ''),
                    IFNULL(ft.fty_fund_desc, ''),
                    IFNULL(pd.at_activity_code, ''),
                    IFNULL(ats.at_activity_description_bm, ''),
                    IFNULL(pd.oun_code, ''),
                    IFNULL(ou.oun_desc, ''),
                    IFNULL(pd.ccr_costcentre, ''),
                    IFNULL(cc.ccr_costcentre_desc, ''),
                    IFNULL(SUBSTRING(pd.cpa_project_no, 17, 21), ''),
                    IFNULL(cp.cpa_project_desc, ''),
                    IFNULL(pd.acm_acct_code, ''),
                    IFNULL(am.acm_acct_desc, ''),
                    IFNULL(pd.pde_reference, ''),
                    IFNULL(pd.pde_reference1, ''),
                    IFNULL(DATE_FORMAT(pd.pde_trans_date, '%d/%m/%Y'), ''),
                    IFNULL(pd.pde_payto_type, ''),
                    IFNULL(pm.createdby, ''),
                    IFNULL(pm.pmt_system_id, ''),
                    IFNULL(am.acm_acct_activity, ''),
                    IFNULL(pd.pde_trans_type, ''),
                    IFNULL(am.acm_behavior, ''),
                    IFNULL(CONCAT_WS('-', pd.pde_payto_id, pd.pde_payto_name), ''),
                    IFNULL(l1.acm_acct_code, ''),
                    IFNULL(l2.acm_acct_code, ''),
                    IFNULL(l3.acm_acct_code, ''),
                    IFNULL(l4.acm_acct_code, '')
                )) LIKE ?",
                [$like]
            );
        }

        $this->applyScalarFilter($query, $request->input('pmt_system_id'), 'pm.pmt_system_id');
        $this->applyScalarFilter($query, $request->input('fty_fund_type'), 'pd.fty_fund_type');
        $this->applyScalarFilter($query, $request->input('at_activity_code'), 'pd.at_activity_code');
        $this->applyScalarFilter($query, $request->input('ccr_costcentre'), 'pd.ccr_costcentre');
        $this->applyScalarFilter($query, $request->input('acm_acct_code'), 'pd.acm_acct_code');
        $this->applyScalarFilter($query, $request->input('pde_payto_type'), 'pd.pde_payto_type');
        $this->applyScalarFilter($query, $request->input('pmt_posting_no'), 'pm.pmt_posting_no');
        $this->applyScalarFilter($query, $request->input('pde_document_no'), 'pd.pde_document_no');
        $this->applyScalarFilter($query, $request->input('pde_reference'), 'pd.pde_reference');
        $this->applyScalarFilter($query, $request->input('pde_reference1'), 'pd.pde_reference1');
        $this->applyScalarFilter($query, $request->input('pde_trans_type'), 'pd.pde_trans_type');
        $this->applyScalarFilter($query, $request->input('pde_payto_id'), 'pd.pde_payto_id');
        $this->applyScalarFilter($query, $request->input('account_class'), 'l1.acm_acct_code');
        $this->applyScalarFilter($query, $request->input('account_subclass'), 'l2.acm_acct_code');
        $this->applyScalarFilter($query, $request->input('account_series'), 'l3.acm_acct_code');
        $this->applyScalarFilter($query, $request->input('account_subseries'), 'l4.acm_acct_code');
        $this->applyScalarFilter($query, $request->input('account_type'), 'am.acm_acct_activity');
        $this->applyScalarFilter($query, $request->input('acm_behavior'), 'am.acm_behavior');

        // so_code is SUBSTRING(cpa_project_no, 17, 21); filter against the same expression.
        $soCode = trim((string) $request->input('so_code', ''));
        if ($soCode !== '') {
            $query->whereRaw('SUBSTRING(pd.cpa_project_no, 17, 21) = ?', [$soCode]);
        }

        // PTJ level 3 / level 4 — legacy allows either the unit itself or its parent.
        $ounCode = trim((string) $request->input('oun_code', ''));
        if ($ounCode !== '') {
            $query->where(function ($inner) use ($ounCode) {
                $inner->where('pd.oun_code', $ounCode)
                    ->orWhere('ou.oun_code_parent', $ounCode);
            });
        }
        $ounCodeL3 = trim((string) $request->input('oun_code_l3', ''));
        if ($ounCodeL3 !== '') {
            $query->where(function ($inner) use ($ounCodeL3) {
                $inner->where('pd.oun_code', $ounCodeL3)
                    ->orWhere('ou.oun_code_parent', $ounCodeL3);
            });
        }

        // Legacy date bounds use STR_TO_DATE(..., '%d/%m/%Y') on pde_trans_date.
        $dateStart = trim((string) $request->input('date_start', ''));
        $dateEnd = trim((string) $request->input('date_end', ''));
        if ($dateStart !== '' && $dateEnd !== '') {
            $query->whereRaw(
                "DATE(pd.pde_trans_date) BETWEEN STR_TO_DATE(?, '%d/%m/%Y') AND STR_TO_DATE(?, '%d/%m/%Y')",
                [$dateStart, $dateEnd]
            );
        } elseif ($dateStart !== '') {
            $query->whereRaw(
                "DATE(pd.pde_trans_date) >= STR_TO_DATE(?, '%d/%m/%Y')",
                [$dateStart]
            );
        } elseif ($dateEnd !== '') {
            $query->whereRaw(
                "DATE(pd.pde_trans_date) <= STR_TO_DATE(?, '%d/%m/%Y')",
                [$dateEnd]
            );
        }

        return $query;
    }

    private function applyScalarFilter($query, $value, string $column): void
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return;
        }
        $query->where($column, $value);
    }

    private function sortColumn(string $key): string
    {
        return match ($key) {
            'pmt_posting_no', 'pmt_system_id', 'pmt_posteddate' => 'pm.'.$key,
            'pde_document_no', 'pde_trans_date' => 'pd.'.$key,
            'pde_trans_amt' => 'pde_trans_amt_signed',
            'acm_acct_code' => 'pd.acm_acct_code',
            default => 'pd.acm_acct_code',
        };
    }

    private function joinWithSpaces(?string $a, ?string $b): ?string
    {
        $parts = array_filter([trim((string) $a), trim((string) $b)], static fn ($v) => $v !== '');

        return count($parts) ? implode(' - ', $parts) : null;
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
