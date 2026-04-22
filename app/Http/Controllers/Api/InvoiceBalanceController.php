<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Credit Control > Invoice Balance" (PAGEID 2561 / MENUID 3388).
 *
 * Source: FIMS BL `MZS_API_CC_INVOICE_BALANCE`. The BL aggregates
 * `rep_aging_debtor` grouped by payee / analysis dimensions and computes
 * a self-joined running balance `SUM(IF(DT, amt, -amt))` restricted to
 * `pde_trans_date <= tf_end_date`, then keeps only rows where balance
 * remains positive and the invoice itself is APPROVED in
 * `cust_invoice_master`.
 *
 * Top filters: `tf_customer_type`, `tf_customer_id`, `tf_invoice_no`,
 * `tf_end_date`. Global search mirrors the legacy `CONCAT_WS('__', …)`
 * LIKE match over payee / fund / activity / PTJ / cost centre / account
 * code / invoice no. There is no mutation here — it is a reporting page.
 */
class InvoiceBalanceController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'pde_document_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = [
            'pde_payto_type', 'pde_payto_id', 'pde_payto_name', 'fty_fund_type',
            'oun_code', 'at_activity_code', 'ccr_costcentre', 'acm_acct_code',
            'pde_document_no', 'pde_trans_date', 'pde_trans_amt', 'balance',
        ];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'pde_document_no';
        }

        $endDate = $this->resolveEndDate($request);

        $params = [];
        $filters = '';

        if ($request->filled('tf_customer_type')) {
            $filters .= ' AND rad.pde_payto_type = ?';
            $params[] = $request->input('tf_customer_type');
        }
        if ($request->filled('tf_customer_id')) {
            $filters .= ' AND rad.pde_payto_id = ?';
            $params[] = $request->input('tf_customer_id');
        }
        if ($request->filled('tf_invoice_no')) {
            $filters .= ' AND rad.pde_document_no = ?';
            $params[] = $request->input('tf_invoice_no');
        }

        $subquery = <<<SQL
SELECT
    rad.pde_payto_type,
    rad.pde_payto_id,
    rad.pde_payto_name,
    rad.fty_fund_type,
    rad.oun_code,
    rad.at_activity_code,
    rad.ccr_costcentre,
    rad.acm_acct_code,
    rad.pde_document_no,
    DATE(rad.pde_trans_date) AS pde_trans_date,
    rad.docDescription,
    SUM(rad.pde_trans_amt) AS pde_trans_amt,
    IFNULL((
        SELECT SUM(IF(rr.pde_trans_type='DT', rr.pde_trans_amt, -rr.pde_trans_amt))
        FROM rep_aging_debtor rr
        WHERE rr.pde_status='APPROVE'
          AND DATE(rr.pde_trans_date) <= ?
          AND rr.bills_inv = rad.pde_document_no
          AND rr.fty_fund_type = rad.fty_fund_type
          AND rr.at_activity_code = rad.at_activity_code
          AND rr.oun_code = rad.oun_code
          AND rr.ccr_costcentre = rad.ccr_costcentre
          AND rr.acm_acct_code = rad.acm_acct_code
          AND rr.pde_payto_type = rad.pde_payto_type
          AND rr.pde_payto_id = rad.pde_payto_id
    ), 0) AS balance
FROM rep_aging_debtor rad
JOIN v_debtor_category cat ON rad.acm_acct_code = cat.ACCT_CODE
WHERE rad.pde_status='APPROVE'
  AND DATE(rad.pde_trans_date) <= ?
  AND rad.pde_document_no IN (
    SELECT DISTINCT cim_invoice_no FROM cust_invoice_master WHERE cim_status='APPROVE'
  )
  {$filters}
GROUP BY 1,2,3,4,5,6,7,8,9,10,11
SQL;

        // Sub-query SELECT binds the ? in the correlated sub-select first,
        // then the outer end-date predicate. Any tf_* filters bind after.
        $subParams = array_merge([$endDate, $endDate], $params);

        $outerWhere = ' WHERE balance > 0';
        $outerParams = [];
        if ($q !== '') {
            $outerWhere .= ' AND LOWER(CONCAT_WS(\'__\','
                . 'IFNULL(pde_payto_id,""),'
                . 'IFNULL(pde_payto_name,""),'
                . 'IFNULL(fty_fund_type,""),'
                . 'IFNULL(oun_code,""),'
                . 'IFNULL(at_activity_code,""),'
                . 'IFNULL(ccr_costcentre,""),'
                . 'IFNULL(acm_acct_code,""),'
                . 'IFNULL(pde_document_no,"")'
                . ')) LIKE ?';
            $outerParams[] = $this->likeEscape($q);
        }

        $orderClause = ' ORDER BY ' . $sortBy . ' ' . strtoupper($sortDir);

        $db = DB::connection('mysql_secondary');

        // Count
        $countSql = 'SELECT COUNT(*) AS c FROM (' . $subquery . ') inv' . $outerWhere;
        $totalRow = $db->selectOne($countSql, array_merge($subParams, $outerParams));
        $total = (int) ($totalRow->c ?? 0);

        // Sum of balance and trans_amt for footer
        $sumSql = 'SELECT COALESCE(SUM(pde_trans_amt),0) AS a, COALESCE(SUM(balance),0) AS b '
            . 'FROM (' . $subquery . ') inv' . $outerWhere;
        $sumRow = $db->selectOne($sumSql, array_merge($subParams, $outerParams));

        // Paged rows
        $offset = ($page - 1) * $limit;
        $dataSql = 'SELECT inv.*, '
            . '(SELECT ld.lde_description FROM lookup_details ld WHERE lma_code_name=\'CUSTOMER_TYPE\' AND ld.lde_value = inv.pde_payto_type) AS pde_payto_type_desc '
            . 'FROM (' . $subquery . ') inv'
            . $outerWhere
            . $orderClause
            . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $rows = $db->select($dataSql, array_merge($subParams, $outerParams));

        $data = collect($rows)->values()->map(fn ($r, int $i) => [
            'index' => $offset + $i + 1,
            'pdePaytoType' => $r->pde_payto_type,
            'pdePaytoTypeDesc' => $r->pde_payto_type_desc,
            'pdePaytoId' => $r->pde_payto_id,
            'pdePaytoName' => $r->pde_payto_name,
            'ftyFundType' => $r->fty_fund_type,
            'ounCode' => $r->oun_code,
            'atActivityCode' => $r->at_activity_code,
            'ccrCostcentre' => $r->ccr_costcentre,
            'acmAcctCode' => $r->acm_acct_code,
            'pdeDocumentNo' => $r->pde_document_no,
            'pdeTransDate' => $r->pde_trans_date
                ? Carbon::parse($r->pde_trans_date)->format('d/m/Y')
                : null,
            'docDescription' => $r->docDescription,
            'pdeTransAmt' => (float) ($r->pde_trans_amt ?? 0),
            'balance' => (float) ($r->balance ?? 0),
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => [
                'pdeTransAmt' => (float) ($sumRow->a ?? 0),
                'balance' => (float) ($sumRow->b ?? 0),
            ],
            'endDate' => Carbon::parse($endDate)->format('d/m/Y'),
        ]);
    }

    public function options(): JsonResponse
    {
        $db = 'mysql_secondary';

        $customerTypes = DB::connection($db)
            ->table('lookup_details')
            ->where('lma_code_name', 'CUSTOMER_TYPE')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => $r->lde_value,
                'label' => trim(($r->lde_value ?? '') . ' - ' . ($r->lde_description ?? '')),
            ])
            ->values();

        return $this->sendOk([
            'customerType' => $customerTypes,
        ]);
    }

    /**
     * Autosuggest for top-filter Customer ID. Mirrors legacy
     * `autoSuggestCustID` which varies the source by customer type:
     *  A → student; B → staff; C/D → vend_customer_supplier;
     *  F → investment_institution; else → UNION of student/staff/C+D.
     */
    public function searchCustomer(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $custType = trim((string) $request->input('customer_type', ''));
        $limit = min(50, max(1, (int) $request->input('limit', 20)));

        $db = 'mysql_secondary';

        $base = match (strtoupper($custType)) {
            'A' => DB::connection($db)->table('student')->selectRaw('std_student_id as id, std_student_name as name'),
            'B' => DB::connection($db)->table('staff')->selectRaw('stf_staff_id as id, stf_staff_name as name'),
            'C', 'D' => DB::connection($db)->table('vend_customer_supplier')->selectRaw('vcs_vendor_code as id, vcs_vendor_name as name'),
            'F' => DB::connection($db)->table('investment_institution')->selectRaw('iit_inst_code as id, iit_inst_name as name'),
            default => null,
        };

        if ($base === null) {
            $students = DB::connection($db)->table('student')->selectRaw('std_student_id as id, std_student_name as name');
            $staff = DB::connection($db)->table('staff')->selectRaw('stf_staff_id as id, stf_staff_name as name');
            $creditors = DB::connection($db)->table('vend_customer_supplier')
                ->where('vcs_vendor_code', 'like', 'C%')
                ->selectRaw('vcs_vendor_code as id, vcs_vendor_name as name');
            $debtors = DB::connection($db)->table('vend_customer_supplier')
                ->where('vcs_vendor_code', 'like', 'D%')
                ->selectRaw('vcs_vendor_code as id, vcs_vendor_name as name');

            $union = $students->unionAll($staff)->unionAll($creditors)->unionAll($debtors);

            $base = DB::connection($db)->query()->fromSub($union, 'tbl')->select(['id', 'name']);
        }

        if ($q !== '') {
            $like = $this->likeEscape($q);
            $base->where(function ($b) use ($like) {
                $b->orWhereRaw('LOWER(IFNULL(id, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(name, "")) LIKE ?', [$like]);
            });
        }

        $rows = $base->orderBy('id')->limit($limit)->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'label' => trim(($r->id ?? '') . ' - ' . ($r->name ?? '')),
                'name' => (string) ($r->name ?? ''),
            ])
            ->values();

        return $this->sendOk($rows);
    }

    /**
     * Autosuggest for top-filter Invoice No, scoped by customer type and/or
     * customer id against `cust_invoice_master` APPROVE rows. Mirrors the
     * legacy `autoSuggestInvoiceNo` branches.
     */
    public function searchInvoice(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $custType = trim((string) $request->input('customer_type', ''));
        $custId = trim((string) $request->input('customer_id', ''));
        $limit = min(50, max(1, (int) $request->input('limit', 20)));

        $query = DB::connection('mysql_secondary')
            ->table('cust_invoice_master')
            ->where('cim_status', 'APPROVE')
            ->selectRaw('cim_invoice_no as id, cim_invoice_desc as desc');

        if ($custType !== '') {
            $query->where('cim_cust_type', $custType);
        }
        if ($custId !== '') {
            $query->where('cim_cust_id', $custId);
        }

        if ($q !== '') {
            $like = $this->likeEscape($q);
            $query->where(function ($b) use ($like) {
                $b->orWhereRaw('LOWER(IFNULL(cim_invoice_no, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(cim_invoice_desc, "")) LIKE ?', [$like]);
            });
        }

        $rows = $query->orderBy('cim_invoice_no')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'label' => trim(($r->id ?? '') . ' - ' . ($r->desc ?? '')),
            ])
            ->values();

        return $this->sendOk($rows);
    }

    // --------------------------------------------------------------------------

    private function resolveEndDate(Request $request): string
    {
        $raw = trim((string) $request->input('tf_end_date', ''));
        if ($raw !== '') {
            foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d'] as $format) {
                try {
                    $dt = Carbon::createFromFormat($format, $raw);
                    if ($dt !== false) {
                        return $dt->format('Y-m-d');
                    }
                } catch (\Throwable $e) {
                    // continue
                }
            }
            try {
                return Carbon::parse($raw)->format('Y-m-d');
            } catch (\Throwable $e) {
                // fallthrough
            }
        }

        return Carbon::today()->format('Y-m-d');
    }

    private function likeEscape(string $needle): string
    {
        $n = mb_strtolower($needle, 'UTF-8');

        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $n) . '%';
    }
}
