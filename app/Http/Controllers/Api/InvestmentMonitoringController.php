<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\InvestmentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Investment > Monitoring (PAGEID 1183 / MENUID 1458).
 *
 * Source: FIMS BL `ATR_INVESTMENT_MONITORING`.
 *
 * This is a two-level drill-down:
 *   - Level 1 (batches):    legacy `?dt=batch`     -> {@see batches()}
 *   - Level 2 (investments): legacy `?dt=listing&currbatch=X` -> {@see investments()}
 *
 * Level 1 groups `investment_profile` by `ipf_batch_no` with a summed
 * principal per batch. Level 2 expands the selected batch and joins
 * `manual_journal_master` (system_id='JOURNAL_INVEST') for journal
 * status, plus a correlated subquery over `receipt_details` +
 * `receipt_master` (rma_status='APPROVE', posting_no NOT NULL,
 * rde_source_type='Investment') to aggregate receipts into a single
 * cell — same pattern used by ListOfInvestmentsController but keyed on
 * the monitoring BL's `rde.rde_source_ref_id = ipf.ipf_investment_id`
 * join instead of `rde.rde_source_ref_no = ipf.ipf_investment_no`.
 *
 * Scope (shared by both levels, mirrors legacy `$common`):
 *   - `ipf_status IN ('APPROVE','MATURED')`
 *   - `(bim_bills_no != 'RENEW' OR bim_bills_no IS NULL)`
 *   - `ipf_ref_investment_no IS NULL`
 *
 * The legacy `?action=viewbatch` (batch header summary) is served
 * inline via the Level-2 meta `grandTotalPrincipal`.
 *
 * Batch-level PDF reports (legacy `?action=...`):
 *   - summary  -> investmentSummary_pdf  -> {@see summaryForPdf}
 *     Migrated. Backs the "Investment Summary" report in the UI.
 *   - billBatch -> billRegistrationInvestBatch_pdf. NOT migrated.
 *     The legacy binary renders a formal per-bill workflow
 *     document with five approval signers, address block with
 *     STATE lookup, bank details, and iterates over every bill in
 *     the batch. Requires joining `bills_master`, `bills_details`,
 *     `wf_task`, `wf_task_history`, `staff`, `bank_master`,
 *     `lookup_details`. Deferred — the button is rendered disabled
 *     with a tooltip pointing to this note.
 *   - reportUrl -> billRegistrationInvest_pdf. NOT migrated. Per-bill
 *     variant of billBatch, triggered in legacy via COMPONENT_JS
 *     against a specific `billID`; the monitoring grid does not
 *     expose a bill id per row so there is no entry point in the
 *     migrated UI.
 *
 * Row-action "Download Journal" IS migrated: the response exposes
 * `journalId` (mjm_journal_master.mjm_journal_id) so the Vue view
 * can reuse the existing Manual Journal PDF flow
 * (`getManualJournalDetail` + `downloadManualJournalFormPdf`),
 * same pipeline as ManualJournalListingView's per-row PDF button.
 *
 * The row-level "View" deep-link to legacy menuID 3226 is NOT
 * migrated — that target page has no kerisiV2 route yet; it
 * renders disabled.
 */
class InvestmentMonitoringController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    /** Matches the other Investment controllers — JOURNAL_INVEST. */
    private const MJM_SYSTEM_ID = 'JOURNAL_INVEST';

    /**
     * Row cap for the summary PDF. Legacy `investmentSummary_pdf`
     * renders every qualifying investment in the batch with no
     * pagination; we cap at a conservative number to avoid blowing
     * up jsPDF memory on pathological batches, and surface a
     * `truncated` flag so the view can warn the user.
     */
    private const PDF_ROW_LIMIT = 500;

    /** Allowed sort keys for Level-1 batches. */
    private const BATCH_SORTABLE = [
        'dt_batch',
        'dt_total',
    ];

    /** Allowed sort keys for Level-2 investments. */
    private const INVEST_SORTABLE = [
        'dt_institution',
        'dt_journal_no',
        'dt_principal',
        'dt_rate',
        'dt_receipt_no',
        'dt_receipt_date',
    ];

    public function batches(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dt_batch');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, self::BATCH_SORTABLE, true)) {
            $sortBy = 'dt_batch';
        }

        $base = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->whereIn(DB::raw($this->cs('ipf.ipf_status')), ['APPROVE', 'MATURED'])
            ->whereNull('ipf.ipf_ref_investment_no')
            ->where(function ($builder) {
                $builder->where(DB::raw($this->cs('ipf.bim_bills_no')), '<>', 'RENEW')
                    ->orWhereNull('ipf.bim_bills_no');
            })
            ->whereNotNull('ipf.ipf_batch_no')
            ->where('ipf.ipf_batch_no', '!=', '');

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $concatParts = [
                $this->cs("IFNULL(ipf.ipf_batch_no, '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_principal_amt AS CHAR), '')"),
            ];
            $concat = "CONCAT_WS('__', ".implode(', ', $concatParts).')';
            $base->whereRaw("LOWER($concat) LIKE ?", [$like]);
        }

        // Level 1 groups by ipf_batch_no; total rows = distinct batches
        // after filtering.
        $countSql = (clone $base)
            ->select('ipf.ipf_batch_no')
            ->distinct();
        $total = $countSql->get()->count();

        $orderColumn = match ($sortBy) {
            'dt_batch' => 'ipf_batch_no',
            'dt_total' => DB::raw('total_amount'),
            default => 'ipf_batch_no',
        };

        $rows = (clone $base)
            ->select([
                'ipf.ipf_batch_no as batch_no',
                DB::raw('SUM(ipf.ipf_principal_amt) as total_amount'),
            ])
            ->groupBy('ipf.ipf_batch_no')
            ->orderBy($orderColumn, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'batchNo' => $r->batch_no,
            'totalAmount' => $r->total_amount !== null ? (float) $r->total_amount : null,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function investments(Request $request): JsonResponse
    {
        $batch = trim((string) $request->input('batch', ''));
        if ($batch === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'batch is required.');
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dt_institution');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::INVEST_SORTABLE, true)) {
            $sortBy = 'dt_institution';
        }

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
            ->whereIn(DB::raw($this->cs('ipf.ipf_status')), ['APPROVE', 'MATURED'])
            ->whereNull('ipf.ipf_ref_investment_no')
            ->whereRaw($this->cs('ipf.ipf_batch_no').' = ?', [$batch]);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $concatParts = [
                $this->cs("IFNULL(ipf.iit_inst_code, '')"),
                $this->cs("IFNULL(ipf.ipf_extended_field->>'$.iit_inst_desc', '')"),
                $this->cs("IFNULL(ipf.ipf_investment_no, '')"),
                $this->cs("IFNULL(ipf.ipf_certifcate_no, '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_estimated_period AS CHAR), '')"),
                $this->cs("IFNULL(ipf.ipf_extended_field->>'$.ipf_tenure_desc', '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_start_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_end_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_principal_amt AS CHAR), '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_rate AS CHAR), '')"),
                $this->cs("IFNULL(ipf.mjm_journal_no, '')"),
            ];
            $concat = "CONCAT_WS('__', ".implode(', ', $concatParts).')';
            $base->whereRaw("LOWER($concat) LIKE ?", [$like]);
        }

        $total = (clone $base)->count();

        // Footer grand total (unfiltered by `q`) mirrors the legacy
        // `action=viewbatch` helper + the `footer.Principal` field
        // returned by the `dt=listing` branch. We compute it from the
        // same base query (minus search) so swapping between search
        // and full view reflects the true batch sum.
        $baseForTotal = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->whereIn(DB::raw($this->cs('ipf.ipf_status')), ['APPROVE', 'MATURED'])
            ->whereNull('ipf.ipf_ref_investment_no')
            ->where(function ($builder) {
                $builder->where(DB::raw($this->cs('ipf.bim_bills_no')), '<>', 'RENEW')
                    ->orWhereNull('ipf.bim_bills_no');
            })
            ->whereRaw($this->cs('ipf.ipf_batch_no').' = ?', [$batch]);
        $aggregates = $baseForTotal
            ->selectRaw('COALESCE(SUM(ipf.ipf_principal_amt), 0) as grand_total')
            ->first();
        $grandTotal = $aggregates ? (float) $aggregates->grand_total : 0.0;

        $orderColumn = match ($sortBy) {
            'dt_institution' => 'ipf.iit_inst_code',
            'dt_journal_no' => 'ipf.mjm_journal_no',
            'dt_principal' => 'ipf.ipf_principal_amt',
            'dt_rate' => 'ipf.ipf_rate',
            'dt_receipt_no' => DB::raw('receipt_no_sort'),
            'dt_receipt_date' => DB::raw('receipt_date_sort'),
            default => 'ipf.iit_inst_code',
        };

        // Correlated subquery for receipts. Same pattern as
        // ListOfInvestmentsController but joins on
        // rde.rde_source_ref_id = ipf.ipf_investment_id with
        // rde.rde_source_type = 'Investment', matching the legacy
        // monitoring BL (which differs from the list-of-investments
        // BL that keys on rde_source_ref_no).
        $receiptSub = '(SELECT GROUP_CONCAT(
                DISTINCT CONCAT_WS("__",
                    IFNULL(rma.rma_receipt_no, ""),
                    IFNULL(FORMAT(rde_sum.total, 2), ""),
                    IFNULL(DATE_FORMAT(rma.rma_approve_date, "%d/%m/%Y"), "")
                )
                SEPARATOR "|"
            )
            FROM (
                SELECT rde.rma_receipt_master_id, rde.rde_source_ref_id,
                       SUM(rde.rde_total_amt) AS total
                FROM receipt_details rde
                WHERE rde.rde_transaction_type = '."'CR'".'
                  AND rde.rde_source_type = '."'Investment'".'
                GROUP BY rde.rma_receipt_master_id, rde.rde_source_ref_id
            ) rde_sum
            JOIN receipt_master rma
                ON rma.rma_receipt_master_id = rde_sum.rma_receipt_master_id
                AND rma.rma_status = '."'APPROVE'".'
                AND rma.pmt_posting_no IS NOT NULL
            WHERE rde_sum.rde_source_ref_id = ipf.ipf_investment_id) as receipt_info';

        // Sort helpers so the Receipt No / Receipt Date columns
        // (hidden by legacy d-none but still sortable per dt_sort)
        // order by the first / earliest receipt. Subqueries instead of
        // GROUP_CONCAT because MySQL can't ORDER BY an aggregated alias
        // built inside another subquery without re-joining.
        $receiptNoSort = "(SELECT MIN(rma.rma_receipt_no)
            FROM receipt_details rde
            JOIN receipt_master rma ON rma.rma_receipt_master_id = rde.rma_receipt_master_id
                AND rma.rma_status = 'APPROVE'
                AND rma.pmt_posting_no IS NOT NULL
            WHERE rde.rde_source_ref_id = ipf.ipf_investment_id
              AND rde.rde_source_type = 'Investment'
              AND rde.rde_transaction_type = 'CR') as receipt_no_sort";
        $receiptDateSort = "(SELECT MIN(rma.rma_approve_date)
            FROM receipt_details rde
            JOIN receipt_master rma ON rma.rma_receipt_master_id = rde.rma_receipt_master_id
                AND rma.rma_status = 'APPROVE'
                AND rma.pmt_posting_no IS NOT NULL
            WHERE rde.rde_source_ref_id = ipf.ipf_investment_id
              AND rde.rde_source_type = 'Investment'
              AND rde.rde_transaction_type = 'CR') as receipt_date_sort";

        $rows = (clone $base)
            ->select([
                'ipf.ipf_investment_id',
                'ipf.ipf_batch_no',
                'ipf.iit_inst_code',
                DB::raw("ipf.ipf_extended_field->>'\$.iit_inst_desc' as inst_desc"),
                'iit.iit_inst_name',
                'iit.iit_bank_branch',
                'ipf.ipf_investment_no',
                'ipf.ipf_certifcate_no',
                'ipf.mjm_journal_no as journal_no',
                'mjm.mjm_journal_id as journal_id',
                'mjm.mjm_status as journal_status',
                'ipf.ipf_estimated_period',
                DB::raw("ipf.ipf_extended_field->>'\$.ipf_tenure_desc' as tenure_desc"),
                DB::raw("DATE_FORMAT(ipf.ipf_start_date, '%d/%m/%Y') as start_date_fmt"),
                DB::raw("DATE_FORMAT(ipf.ipf_end_date, '%d/%m/%Y') as end_date_fmt"),
                'ipf.ipf_principal_amt',
                'ipf.ipf_rate',
                'ipf.ipf_status',
                DB::raw($receiptSub),
                DB::raw($receiptNoSort),
                DB::raw($receiptDateSort),
            ])
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('ipf.ipf_investment_id', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $receipts = [];
            if (! empty($r->receipt_info)) {
                foreach (explode('|', (string) $r->receipt_info) as $part) {
                    [$rcptNo, $amt, $date] = array_pad(explode('__', $part), 3, '');
                    if ($rcptNo === '' && $amt === '' && $date === '') {
                        continue;
                    }
                    $receipts[] = [
                        'receiptNo' => $rcptNo,
                        'amount' => $amt,
                        'date' => $date,
                    ];
                }
            }

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'investmentId' => (int) $r->ipf_investment_id,
                'batchNo' => $r->ipf_batch_no,
                'institutionCode' => $r->iit_inst_code,
                'institutionDesc' => $r->inst_desc ?? $r->iit_inst_name,
                'institutionBranch' => $r->iit_bank_branch,
                'investmentNo' => $r->ipf_investment_no,
                'certificateNo' => $r->ipf_certifcate_no,
                'journalNo' => $r->journal_no,
                'journalId' => $r->journal_id !== null ? (int) $r->journal_id : null,
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
                'receipts' => $receipts,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'batch' => $batch,
            'grandTotalPrincipal' => $grandTotal,
        ]);
    }

    /**
     * Backs the "Investment Summary" PDF report (legacy
     * `custom/report/senarai/Investment/investmentSummary_pdf.php`,
     * triggered by `ATR_INVESTMENT_MONITORING` with `action=summary`).
     *
     * The legacy binary renders every investment in the batch with
     * no pagination. We reuse the exact same filter / JOIN as
     * {@see investments()} minus pagination, capped at
     * {@see self::PDF_ROW_LIMIT} to protect jsPDF. The response is
     * a flat JSON payload; the Vue composable
     * `downloadInvestmentMonitoringSummaryPdf` renders the document
     * client-side (landscape A4, same columns as the legacy TCPDF
     * output).
     */
    public function summaryForPdf(Request $request): JsonResponse
    {
        $batch = trim((string) $request->input('batch', ''));
        if ($batch === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'Batch is required.');
        }
        $q = trim((string) $request->input('q', ''));

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
            ->whereIn(DB::raw($this->cs('ipf.ipf_status')), ['APPROVE', 'MATURED'])
            ->whereNull('ipf.ipf_ref_investment_no')
            ->whereRaw($this->cs('ipf.ipf_batch_no').' = ?', [$batch]);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $concatParts = [
                $this->cs("IFNULL(ipf.iit_inst_code, '')"),
                $this->cs("IFNULL(ipf.ipf_extended_field->>'$.iit_inst_desc', '')"),
                $this->cs("IFNULL(ipf.ipf_investment_no, '')"),
                $this->cs("IFNULL(ipf.ipf_certifcate_no, '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_estimated_period AS CHAR), '')"),
                $this->cs("IFNULL(ipf.ipf_extended_field->>'$.ipf_tenure_desc', '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_start_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_end_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_principal_amt AS CHAR), '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_rate AS CHAR), '')"),
                $this->cs("IFNULL(ipf.mjm_journal_no, '')"),
            ];
            $concat = "CONCAT_WS('__', ".implode(', ', $concatParts).')';
            $base->whereRaw("LOWER($concat) LIKE ?", [$like]);
        }

        // The legacy summary report also applies the `bim_bills_no
        // != 'RENEW' OR bim_bills_no IS NULL` rule at the batch
        // header total SQL (`$sqlTotal`). Mirror that here so the
        // totalByBatch reported in the PDF matches the legacy value
        // exactly — the Level-2 list doesn't enforce it on its own
        // row set because it's a display filter, but the header
        // total MUST respect it.
        $totalQuery = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->whereIn(DB::raw($this->cs('ipf.ipf_status')), ['APPROVE', 'MATURED'])
            ->whereNull('ipf.ipf_ref_investment_no')
            ->where(function ($builder) {
                $builder->where(DB::raw($this->cs('ipf.bim_bills_no')), '<>', 'RENEW')
                    ->orWhereNull('ipf.bim_bills_no');
            })
            ->whereRaw($this->cs('ipf.ipf_batch_no').' = ?', [$batch]);
        $totalRow = $totalQuery
            ->selectRaw('COALESCE(SUM(ipf.ipf_principal_amt), 0) as total_by_batch')
            ->first();
        $totalByBatch = $totalRow ? (float) $totalRow->total_by_batch : 0.0;

        $rows = (clone $base)
            ->select([
                'ipf.ipf_investment_id',
                'ipf.iit_inst_code',
                DB::raw("ipf.ipf_extended_field->>'\$.iit_inst_desc' as inst_desc"),
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
            ])
            ->orderBy('ipf.ipf_investment_no')
            ->orderBy('ipf.ipf_investment_id')
            ->limit(self::PDF_ROW_LIMIT + 1)
            ->get();

        $truncated = $rows->count() > self::PDF_ROW_LIMIT;
        if ($truncated) {
            $rows = $rows->take(self::PDF_ROW_LIMIT);
        }

        $grandTotal = 0.0;
        $data = $rows->values()->map(function ($r, int $i) use (&$grandTotal) {
            $principal = $r->ipf_principal_amt !== null ? (float) $r->ipf_principal_amt : 0.0;
            $grandTotal += $principal;

            return [
                'index' => $i + 1,
                'institutionCode' => $r->iit_inst_code,
                'institutionDesc' => $r->inst_desc ?? $r->iit_inst_name,
                'institutionBranch' => $r->iit_bank_branch,
                'investmentNo' => $r->ipf_investment_no,
                'certificateNo' => $r->ipf_certifcate_no,
                'journalNo' => $r->journal_no,
                'journalStatus' => $r->journal_status,
                'period' => $r->ipf_estimated_period !== null ? (int) $r->ipf_estimated_period : null,
                'tenureDesc' => $r->tenure_desc,
                'startDate' => $r->start_date_fmt,
                'endDate' => $r->end_date_fmt,
                'principalAmount' => $r->ipf_principal_amt !== null ? $principal : null,
                'rate' => $r->ipf_rate !== null ? (float) $r->ipf_rate : null,
                'status' => $r->ipf_status,
            ];
        });

        return $this->sendOk([
            'batch' => $batch,
            'totalByBatch' => $totalByBatch,
            'grandTotal' => $grandTotal,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
            'rows' => $data,
            'truncated' => $truncated,
            'limit' => self::PDF_ROW_LIMIT,
        ]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
