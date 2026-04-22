<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Debtor Portal > Debtors Statement" endpoint
 * (PAGEID for MENUID 2267).
 *
 * Source: FIMS BL `NF_BL_DP_DEBTORS_STATEMENT`.
 *
 * ## Behaviour
 *
 * Runs a 6-way UNION over AR documents for the selected debtor and
 * returns a chronologically-sorted ledger with a running-balance
 * (outstanding / advance) calculated in PHP — mirroring the legacy
 * `@amtLedger` session variable trick which doesn't translate to plain
 * parameterised SQL reliably.
 *
 * The UNION components are (sorting column first in each):
 *   (1) APPROVE invoices          (cust_invoice_master)       debit
 *   (2) APPROVE credit notes      (credit_note_master)        credit (negative)
 *   (3) APPROVE debit notes       (debit_note_master)         debit
 *   (4) APPROVE discount notes    (discount_note_master)      credit (negative)
 *   (5) APPROVE deposit credit    (deposit_master + details)  credit/advance
 *   (6) APPROVE deposit debit     (deposit_master + details)  advance
 *   (7) Invoice knockoffs         (cust_invoice_knockoff + rm) credit
 *
 * Filtering:
 *   - `debtor_id`  — legacy `vcs_vendor_code`; defaults to
 *                     auth()->user()->name.
 *   - `cust_type`  — legacy hard-coded to 'D' (debtor).
 *   - `q`          — case-insensitive LIKE on document no / description.
 *
 * The response meta carries the footer totals (debit, credit,
 * credit-note, discount-note, debit-note, advance, balance) needed for
 * the UI's grand-total row. Running balance / advance per row is
 * computed AFTER ordering, using the same rule as the legacy BL:
 *
 *   amtLedger += row.balance                             (signed)
 *   outstanding = max(amtLedger, 0)
 *   advance     = max(-amtLedger, 0)
 */
class DebtorStatementController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 25));
        $q = trim((string) $request->input('q', ''));
        $custType = (string) ($request->input('cust_type', 'D'));

        $debtorId = $this->debtorId($request);
        if ($debtorId === null) {
            return $this->sendOk([], [
                'page' => $page, 'limit' => $limit,
                'total' => 0, 'totalPages' => 0,
                'debtorId' => null, 'custType' => $custType,
                'footer' => $this->zeroFooter(),
            ]);
        }

        $all = $this->fetchLedger($debtorId, $custType);

        // Apply optional global text filter AFTER the UNION (cheaper to do in
        // PHP since the dataset is inherently small per debtor).
        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $all = array_values(array_filter($all, function (array $r) use ($needle) {
                foreach (['document_no', 'ref_no', 'tran_desc'] as $col) {
                    $v = (string) ($r[$col] ?? '');
                    if ($v !== '' && str_contains(mb_strtolower($v, 'UTF-8'), $needle)) {
                        return true;
                    }
                }
                return false;
            }));
        }

        // Sort by (sorting0, transactionDate, sorting, documentNo) per legacy.
        usort($all, function ($a, $b) {
            return [$a['sorting0'], $a['transaction_date'], $a['sorting'], $a['document_no']]
                <=> [$b['sorting0'], $b['transaction_date'], $b['sorting'], $b['document_no']];
        });

        // Compute running balance / advance per row.
        $amtLedger = 0.0;
        $running = [];
        $footer = $this->zeroFooter();
        foreach ($all as $row) {
            $amtLedger += (float) $row['balance'];
            $outstanding = $amtLedger > 0 ? $amtLedger : 0.0;
            $advance = $amtLedger < 0 ? -$amtLedger : 0.0;
            $running[] = array_merge($row, [
                'calculation' => $amtLedger,
                'outstanding' => $outstanding,
                'advance_running' => $advance,
            ]);
            $footer['debit'] += (float) $row['debit'];
            $footer['credit'] += (float) $row['credit'];
            $footer['cn'] += (float) $row['cn'];
            $footer['dn'] += (float) $row['dn'];
            $footer['dc'] += (float) $row['dc'];
        }
        $footer['advance'] = $amtLedger < 0 ? -$amtLedger : 0.0;
        $footer['balance'] = $amtLedger > 0 ? $amtLedger : 0.0;

        $total = count($running);
        $slice = array_slice($running, ($page - 1) * $limit, $limit);

        $data = array_map(function (array $r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'transactionDate' => $r['transaction_date'],
                'documentNo' => $r['document_no'],
                'refNo' => $r['ref_no'],
                'description' => $r['tran_desc'],
                'debit' => (float) $r['debit'],
                'credit' => (float) $r['credit'],
                'cn' => (float) $r['cn'],
                'dn' => (float) $r['dn'],
                'dc' => (float) $r['dc'],
                'advance' => (float) $r['advance_running'],
                'outstanding' => (float) $r['outstanding'],
            ];
        }, $slice, array_keys($slice));

        return $this->sendOk(array_values($data), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'debtorId' => $debtorId,
            'custType' => $custType,
            'footer' => $footer,
        ]);
    }

    /**
     * Execute the 7-branch UNION and normalise each row to a common shape:
     * `transaction_date, document_no, sorting0, sorting, debit, credit,
     *  balance, tran_desc, cn, dn, dc, ref_no`.
     *
     * Returned as a plain array for in-memory sort + running balance.
     *
     * @return array<int,array<string,mixed>>
     */
    private function fetchLedger(string $custId, string $custType): array
    {
        $conn = DB::connection('mysql_secondary');

        // (1) Approved invoices
        $invoices = $conn->table('cust_invoice_master')
            ->where('cim_status', 'APPROVE')
            ->where('cim_cust_type', $custType)
            ->where('cim_cust_id', $custId)
            ->whereNotNull('cim_posting_no')
            ->where('cim_system_id', 'AR_INV')
            ->selectRaw("
                COALESCE(cim_approve_date, cim_invoice_date) AS transaction_date,
                cim_invoice_no AS document_no,
                1 AS sorting0,
                1 AS sorting,
                cim_total_amt AS debit,
                0 AS credit,
                cim_total_amt AS balance,
                CONCAT_WS(' - ', 'Invoice', cim_invoice_desc) AS tran_desc,
                0 AS cn, 0 AS dn, 0 AS dc,
                '' AS ref_no
            ")
            ->get();

        // (2) Approved credit notes
        $credits = $conn->table('credit_note_master as cnm')
            ->where('cnm.cnm_status_cd', 'APPROVE')
            ->whereNotNull('cnm.cnm_posting_no')
            ->where('cnm.cnm_system_id', 'AR_CN')
            ->where('cnm.cnm_cn_total_amount', '>', 0)
            ->where('cnm.cnm_cust_type', $custType)
            ->where('cnm.cnm_cust_id', $custId)
            ->selectRaw("
                COALESCE(
                    (SELECT MAX(wfa.createddate) FROM wf_application_status wfa
                       WHERE wfa.was_status = cnm.cnm_status_cd
                         AND wfa.was_application_id = cnm.cnm_crnote_no),
                    cnm.cnm_crnote_date
                ) AS transaction_date,
                cnm.cnm_crnote_no AS document_no,
                2 AS sorting0,
                2 AS sorting,
                0 AS debit,
                0 AS credit,
                -cnm.cnm_cn_total_amount AS balance,
                CONCAT_WS(' - ', 'CN', cnm.cnm_crnote_desc) AS tran_desc,
                cnm.cnm_cn_total_amount AS cn, 0 AS dn, 0 AS dc,
                cnm.cim_invoice_no AS ref_no
            ")
            ->get();

        // (3) Approved debit notes
        $debits = $conn->table('debit_note_master as dnm')
            ->where('dnm.dnm_status_cd', 'APPROVE')
            ->whereNotNull('dnm.dnm_posting_no')
            ->where('dnm.dnm_system_id', 'AR_DN')
            ->where('dnm.dnm_cust_type', $custType)
            ->where('dnm.dnm_cust_id', $custId)
            ->selectRaw("
                COALESCE(
                    (SELECT MAX(wfa.createddate) FROM wf_application_status wfa
                       WHERE wfa.was_status = dnm.dnm_status_cd
                         AND wfa.was_application_id = dnm.dnm_dnnote_no),
                    dnm.dnm_dnnote_date
                ) AS transaction_date,
                dnm.dnm_dnnote_no AS document_no,
                2 AS sorting0,
                3 AS sorting,
                0 AS debit,
                0 AS credit,
                dnm.dnm_dn_total_amount AS balance,
                CONCAT_WS(' - ', 'DN', dnm.dnm_dnnote_desc) AS tran_desc,
                0 AS cn, dnm.dnm_dn_total_amount AS dn, 0 AS dc,
                dnm.cim_invoice_no AS ref_no
            ")
            ->get();

        // (4) Approved discount notes
        $discounts = $conn->table('discount_note_master as dcm')
            ->where('dcm.dcm_status_cd', 'APPROVE')
            ->whereNotNull('dcm.dcm_posting_no')
            ->where('dcm.dcm_system_id', 'AR_DC')
            ->where('dcm.dcm_dc_total_amount', '>', 0)
            ->where('dcm.dcm_cust_type', $custType)
            ->where('dcm.dcm_cust_id', $custId)
            ->selectRaw("
                COALESCE(
                    (SELECT MAX(wfa.createddate) FROM wf_application_status wfa
                       WHERE wfa.was_status = dcm.dcm_status_cd
                         AND wfa.was_application_id = dcm.dcm_dcnote_no),
                    dcm.dcm_dcnote_date
                ) AS transaction_date,
                dcm.dcm_dcnote_no AS document_no,
                2 AS sorting0,
                4 AS sorting,
                0 AS debit,
                0 AS credit,
                -dcm.dcm_dc_total_amount AS balance,
                CONCAT_WS(' - ', 'DC', dcm.dcm_dcnote_desc) AS tran_desc,
                0 AS cn, 0 AS dn, dcm.dcm_dc_total_amount AS dc,
                dcm.cim_invoice_no AS ref_no
            ")
            ->get();

        // (5) Deposit CR lines (payment / advance in)
        $depositCr = $conn->table('deposit_master as dm')
            ->join('deposit_details as dd', 'dm.dpm_deposit_master_id', '=', 'dd.dpm_deposit_master_id')
            ->where('dd.ddt_type', 'CR')
            ->where('dm.dpm_payto_type', $custType)
            ->whereIn('dm.dpm_status', ['APPROVE', '1'])
            ->where('dm.vcs_vendor_code', $custId)
            ->groupByRaw("COALESCE(dd.ddt_transaction_date, dd.createddate), dm.dpm_deposit_no, COALESCE(dd.ddt_description, dm.dpm_ref_no_note)")
            ->selectRaw("
                COALESCE(dd.ddt_transaction_date, dd.createddate) AS transaction_date,
                dm.dpm_deposit_no AS document_no,
                2 AS sorting0,
                7 AS sorting,
                0 AS debit,
                SUM(dd.ddt_amt) AS credit,
                SUM(-dd.ddt_amt) AS balance,
                COALESCE(dd.ddt_description, dm.dpm_ref_no_note) AS tran_desc,
                0 AS cn, 0 AS dn, 0 AS dc,
                GROUP_CONCAT(dm.dpm_ref_no) AS ref_no
            ")
            ->get();

        // (6) Deposit DT lines (advance-out / reversal)
        $depositDt = $conn->table('deposit_master as dm')
            ->join('deposit_details as dd', 'dm.dpm_deposit_master_id', '=', 'dd.dpm_deposit_master_id')
            ->where('dd.ddt_type', 'DT')
            ->where('dm.dpm_payto_type', $custType)
            ->whereIn('dm.dpm_status', ['APPROVE', '1'])
            ->where('dm.vcs_vendor_code', $custId)
            ->groupByRaw("dd.createddate, dm.dpm_deposit_no, COALESCE(dd.ddt_description, dm.dpm_ref_no_note)")
            ->selectRaw("
                dd.createddate AS transaction_date,
                dm.dpm_deposit_no AS document_no,
                2 AS sorting0,
                8 AS sorting,
                0 AS debit,
                0 AS credit,
                0 AS balance,
                COALESCE(dd.ddt_description, dm.dpm_ref_no_note) AS tran_desc,
                0 AS cn, 0 AS dn, 0 AS dc,
                GROUP_CONCAT(dd.ddt_doc_no) AS ref_no
            ")
            ->get();

        // (7) Invoice knock-offs (receipt matched against invoice)
        $knockoffs = $conn->table('cust_invoice_knockoff as cik')
            ->join('cust_invoice_master as cim', 'cik.cik_invoice_no', '=', 'cim.cim_invoice_no')
            ->join('receipt_master as rm', 'cik.cik_document_no', '=', 'rm.rma_receipt_no')
            ->where('cim.cim_cust_id', $custId)
            ->whereNotNull('rm.pmt_posting_no')
            ->whereNotNull('cim.cim_posting_no')
            ->where('cim.cim_status', 'APPROVE')
            ->where('cik.cik_status', '!=', 'CANCEL')
            ->where('cim.cim_system_id', 'AR_INV')
            ->where('cik.cid_transaction_type', 'DT')
            ->groupBy('cik.cik_document_no', 'rm.rma_receipt_desc')
            ->selectRaw("
                MAX(cik.cik_knockoff_date) AS transaction_date,
                cik.cik_document_no AS document_no,
                2 AS sorting0,
                4 AS sorting,
                0 AS debit,
                SUM(cik.cik_knockoff_amt) AS credit,
                SUM(-cik.cik_knockoff_amt) AS balance,
                rm.rma_receipt_desc AS tran_desc,
                0 AS cn, 0 AS dn, 0 AS dc,
                GROUP_CONCAT(cik.cik_invoice_no) AS ref_no
            ")
            ->get();

        $all = [];
        foreach ([$invoices, $credits, $debits, $discounts, $depositCr, $depositDt, $knockoffs] as $coll) {
            foreach ($coll as $r) {
                $all[] = (array) $r;
            }
        }
        return $all;
    }

    private function zeroFooter(): array
    {
        return [
            'debit' => 0.0,
            'credit' => 0.0,
            'cn' => 0.0,
            'dn' => 0.0,
            'dc' => 0.0,
            'advance' => 0.0,
            'balance' => 0.0,
        ];
    }

    private function debtorId(Request $request): ?string
    {
        $override = trim((string) $request->input('debtor_id', $request->input('vcs_vendor_code', '')));
        if ($override !== '') {
            return $override;
        }
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        $name = trim((string) ($user->name ?? ''));
        return $name === '' ? null : $name;
    }
}
