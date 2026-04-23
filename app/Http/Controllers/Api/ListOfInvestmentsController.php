<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\InvestmentInstitution;
use App\Models\InvestmentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Investment > List of Investments (PAGEID 1174 / MENUID 1448).
 *
 * Source: FIMS BL `API_LIST_OF_NEW_INVESTMENT` (action=listing_all_dt).
 * Read-only datatable joining `investment_profile` with
 * `investment_institution` (INNER) and `manual_journal_master` (LEFT,
 * system_id='JOURNAL_INVEST') on mysql_secondary. Receipt info is
 * pulled via a correlated subquery over `receipt_details` +
 * `receipt_master` instead of a CTE so the result is one row per
 * investment (the legacy SQL duplicated rows per receipt; the new
 * output aggregates all receipts for an investment into one cell).
 *
 * Scope: `ipf_status != 'DRAFT'` — same gate as legacy.
 *
 * Smart filter (9 fields per page JSON): Prefix (LEFT(inv_no,3)),
 * Batch No, Institution, Period From/To (ipf_start_date), Matured
 * From/To (ipf_end_date), Amount (substring match), Status.
 *
 * Action column has four buttons in the legacy UI: Download Journal,
 * Edit, View Journal Details, Cancel. All four depend on flows that
 * are NOT migrated yet (Edit -> menuID 3226, View Journal ->
 * menuID 3313, Cancel -> legacy action=cancel_inv with side-effects
 * on investment_report_detail, Download Journal -> legacy
 * investment.downloadjournal()). The Vue view renders each as a
 * disabled button with a tooltip until those flows land.
 */
class ListOfInvestmentsController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    /** Allowed sort keys -> concrete SQL expressions. */
    private const SORTABLE = [
        'dt_batch',
        'dt_institution',
        'dt_invest_no',
        'dt_journal_no',
        'dt_tenure',
        'dt_amount',
        'dt_rate',
        'dt_status',
    ];

    /** Status dropdown values (page JSON lookup). */
    private const STATUSES = [
        'APPROVE', 'PENDING', 'CANCEL', 'WITHDRAW', 'MATURED', 'MATURED WITH PROFIT',
    ];

    /**
     * Legacy BL picks 'MNL_INVEST' when PROJECT_TYPE == 'maips',
     * 'JOURNAL_INVEST' otherwise. kerisiV2 has no equivalent flag,
     * so default to JOURNAL_INVEST. If a project needs MNL_INVEST,
     * promote this to a config value.
     */
    private const MJM_SYSTEM_ID = 'JOURNAL_INVEST';

    public function options(): JsonResponse
    {
        // Prefix — distinct first-3 chars of ipf_investment_no.
        $prefixes = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->selectRaw('DISTINCT LEFT(ipf.ipf_investment_no, 3) as prefix')
            ->where('ipf.ipf_status', '!=', 'DRAFT')
            ->whereNotNull('ipf.ipf_investment_no')
            ->where('ipf.ipf_investment_no', '!=', '')
            ->orderBy('prefix')
            ->pluck('prefix')
            ->filter(fn ($p) => $p !== null && $p !== '')
            ->map(fn ($p) => ['id' => (string) $p, 'label' => (string) $p])
            ->values();

        $batchNos = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->select('ipf_batch_no')
            ->where('ipf.ipf_status', '!=', 'DRAFT')
            ->whereNotNull('ipf.ipf_batch_no')
            ->where('ipf.ipf_batch_no', '!=', '')
            ->distinct()
            ->orderBy('ipf_batch_no', 'desc')
            ->pluck('ipf_batch_no')
            ->map(fn ($b) => ['id' => (string) $b, 'label' => (string) $b])
            ->values();

        $institutions = InvestmentInstitution::query()
            ->select(['iit_inst_code', 'iit_inst_name', 'iit_bank_branch'])
            ->whereNotNull('iit_inst_code')
            ->where('iit_inst_code', '!=', '')
            ->orderBy('iit_inst_code')
            ->get()
            ->map(fn ($i) => [
                'id' => (string) $i->iit_inst_code,
                'label' => '['.$i->iit_inst_code.'] '
                    .($i->iit_inst_name ?? '')
                    .($i->iit_bank_branch ? ' - '.$i->iit_bank_branch : ''),
            ])
            ->values();

        $status = collect(self::STATUSES)
            ->map(fn ($s) => ['id' => $s, 'label' => $s])
            ->values();

        return $this->sendOk([
            'prefix' => $prefixes,
            'batchNo' => $batchNos,
            'institution' => $institutions,
            'status' => $status,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dt_batch');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'dt_batch';
        }

        $filterPrefix = trim((string) $request->input('filter_prefix', ''));
        $filterBatch = trim((string) $request->input('filter_batch', ''));
        $filterInstitution = trim((string) $request->input('filter_institution', ''));
        $filterPeriodFrom = trim((string) $request->input('filter_period_from', ''));
        $filterPeriodTo = trim((string) $request->input('filter_period_to', ''));
        $filterMaturedFrom = trim((string) $request->input('filter_matured_from', ''));
        $filterMaturedTo = trim((string) $request->input('filter_matured_to', ''));
        $filterAmount = trim((string) $request->input('filter_amount', ''));
        $filterStatus = trim((string) $request->input('filter_status', ''));

        // Collation-safe joins via CollationSafeSql trait. See trait
        // PHPDoc for the utf8mb3 vs utf8mb4_0900_ai_ci rationale.
        $base = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->join('investment_institution as iit', function ($join) {
                $join->on(
                    DB::raw($this->cs('ipf.iit_inst_code')),
                    '=',
                    DB::raw($this->cs('iit.iit_inst_code')),
                );
            })
            ->leftJoin('manual_journal_master as mjm', function ($join) {
                $join->on(
                    DB::raw($this->cs('ipf.mjm_journal_no')),
                    '=',
                    DB::raw($this->cs('mjm.mjm_journal_no')),
                )->whereRaw($this->cs('mjm.mjm_system_id').' = ?', [self::MJM_SYSTEM_ID]);
            })
            ->whereRaw($this->cs('ipf.ipf_status')." <> 'DRAFT'");

        if ($q !== '') {
            // Legacy BL does OR across 8 columns; same shape preserved
            // with collation wrappers. Case-insensitive via LOWER().
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $cols = [
                "IFNULL(ipf.ipf_batch_no, '')",
                "IFNULL(iit.iit_inst_name, '')",
                "IFNULL(iit.iit_bank_branch, '')",
                "IFNULL(ipf.ipf_investment_no, '')",
                "IFNULL(ipf.ipf_certifcate_no, '')",
                "IFNULL(ipf.mjm_journal_no, '')",
                "IFNULL(mjm.mjm_status, '')",
                "IFNULL(ipf.ipf_status, '')",
            ];
            $params = [];
            $ors = [];
            foreach ($cols as $c) {
                $ors[] = 'LOWER('.$this->cs($c).') LIKE ?';
                $params[] = $like;
            }
            $base->whereRaw('('.implode(' OR ', $ors).')', $params);
        }

        if ($filterPrefix !== '') {
            $base->whereRaw($this->cs('LEFT(ipf.ipf_investment_no, 3)').' = ?', [$filterPrefix]);
        }
        if ($filterBatch !== '') {
            $base->whereRaw($this->cs('ipf.ipf_batch_no').' = ?', [$filterBatch]);
        }
        if ($filterInstitution !== '') {
            $base->whereRaw($this->cs('ipf.iit_inst_code').' = ?', [$filterInstitution]);
        }
        // Dates accept dd/mm/yyyy (legacy format) — parse via STR_TO_DATE.
        if ($filterPeriodFrom !== '') {
            $base->whereRaw(
                "DATE(ipf.ipf_start_date) >= DATE(STR_TO_DATE(?, '%d/%m/%Y'))",
                [$filterPeriodFrom]
            );
        }
        if ($filterPeriodTo !== '') {
            $base->whereRaw(
                "DATE(ipf.ipf_start_date) <= DATE(STR_TO_DATE(?, '%d/%m/%Y'))",
                [$filterPeriodTo]
            );
        }
        if ($filterMaturedFrom !== '') {
            $base->whereRaw(
                "DATE(ipf.ipf_end_date) >= DATE(STR_TO_DATE(?, '%d/%m/%Y'))",
                [$filterMaturedFrom]
            );
        }
        if ($filterMaturedTo !== '') {
            $base->whereRaw(
                "DATE(ipf.ipf_end_date) <= DATE(STR_TO_DATE(?, '%d/%m/%Y'))",
                [$filterMaturedTo]
            );
        }
        if ($filterAmount !== '') {
            // Legacy substring semantics (matches "1000" against 10,000 etc.).
            $like = $this->likeEscape(mb_strtolower($filterAmount, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(CAST(ipf.ipf_principal_amt AS CHAR), '')").') LIKE ?',
                [$like]
            );
        }
        if ($filterStatus !== '') {
            $base->whereRaw($this->cs('ipf.ipf_status').' = ?', [$filterStatus]);
        }

        $total = (clone $base)->count();

        $orderColumn = match ($sortBy) {
            'dt_batch' => 'ipf.ipf_batch_no',
            'dt_institution' => 'ipf.iit_inst_code',
            'dt_invest_no' => 'ipf.ipf_investment_no',
            'dt_journal_no' => 'ipf.mjm_journal_no',
            'dt_tenure' => 'ipf.ipf_estimated_period',
            'dt_amount' => 'ipf.ipf_principal_amt',
            'dt_rate' => 'ipf.ipf_rate',
            'dt_status' => 'ipf.ipf_status',
            default => 'ipf.ipf_batch_no',
        };

        // Correlated subquery for receipt info. GROUP_CONCAT collapses
        // multiple approved CR receipts for a single investment into
        // one cell (legacy SQL duplicated rows per receipt). Separator
        // is '|' so the Vue layer can split safely.
        $receiptSub = '(SELECT GROUP_CONCAT(
                DISTINCT CONCAT_WS("__",
                    IFNULL(rma.rma_receipt_no, ""),
                    IFNULL(FORMAT(rde_sum.total, 2), ""),
                    IFNULL(DATE_FORMAT(rma.rma_approve_date, "%d/%m/%Y"), "")
                )
                SEPARATOR "|"
            )
            FROM (
                SELECT rde.rma_receipt_master_id, rde.rde_source_ref_no,
                       SUM(rde.rde_total_amt) AS total
                FROM receipt_details rde
                WHERE rde.rde_transaction_type = '."'CR'".'
                GROUP BY rde.rma_receipt_master_id, rde.rde_source_ref_no
            ) rde_sum
            JOIN receipt_master rma
                ON rma.rma_receipt_master_id = rde_sum.rma_receipt_master_id
                AND rma.rma_status = '."'APPROVE'".'
                AND rma.pmt_posting_no IS NOT NULL
            WHERE '.$this->cs('rde_sum.rde_source_ref_no').' = '.$this->cs('ipf.ipf_investment_no').') as receipt_info';

        // Withdrawal type classification mirrors the legacy CASE.
        $withdrawalExpr = "CASE
                WHEN ipf.ipf_withdrawal_date IS NOT NULL AND ipf.ipf_withdrawal_date <> ipf.ipf_end_date THEN 'PREMATURED'
                WHEN ipf.ipf_withdrawal_date = ipf.ipf_end_date THEN 'UPON MATURITY'
                ELSE ''
            END as withdrawal_type";

        $rows = (clone $base)
            ->select([
                'ipf.ipf_investment_id',
                'ipf.ipf_batch_no',
                'ipf.iit_inst_code',
                'iit.iit_inst_name',
                'iit.iit_bank_branch',
                'ipf.ipf_investment_no',
                'ipf.ipf_certifcate_no',
                'ipf.mjm_journal_no as journal_no',
                'mjm.mjm_status as journal_status',
                'ipf.ipf_estimated_period',
                DB::raw("ipf.ipf_extended_field->>'\$.ipf_tenure_desc' as tenure_desc"),
                DB::raw("DATE_FORMAT(ipf.ipf_start_date, '%d/%m/%Y') as start_date_fmt"),
                DB::raw("DATE_FORMAT(ipf.ipf_end_date, '%d/%m/%Y') as end_date_fmt"),
                'ipf.ipf_principal_amt',
                'ipf.ipf_rate',
                'ipf.ipf_status',
                DB::raw($withdrawalExpr),
                DB::raw($receiptSub),
            ])
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('ipf.ipf_investment_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $receipts = [];
            if (! empty($r->receipt_info)) {
                foreach (explode('|', (string) $r->receipt_info) as $item) {
                    [$no, $amount, $date] = array_pad(explode('__', $item, 3), 3, '');
                    if ($no === '' && $amount === '' && $date === '') {
                        continue;
                    }
                    $receipts[] = [
                        'receiptNo' => $no,
                        'amount' => $amount,
                        'date' => $date,
                    ];
                }
            }

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'investmentId' => (int) $r->ipf_investment_id,
                'batchNo' => $r->ipf_batch_no,
                'institutionCode' => $r->iit_inst_code,
                'institutionName' => $r->iit_inst_name,
                'institutionBranch' => $r->iit_bank_branch,
                'investmentNo' => $r->ipf_investment_no,
                'certificateNo' => $r->ipf_certifcate_no,
                'journalNo' => $r->journal_no,
                'journalStatus' => $r->journal_status,
                'period' => $r->ipf_estimated_period !== null ? (int) $r->ipf_estimated_period : null,
                'tenureDesc' => $r->tenure_desc,
                'startDate' => $r->start_date_fmt,
                'endDate' => $r->end_date_fmt,
                'principalAmount' => $r->ipf_principal_amt !== null
                    ? (float) $r->ipf_principal_amt
                    : null,
                'rate' => $r->ipf_rate !== null ? (float) $r->ipf_rate : null,
                'status' => $r->ipf_status,
                'withdrawalType' => $r->withdrawal_type,
                'receipts' => $receipts,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
