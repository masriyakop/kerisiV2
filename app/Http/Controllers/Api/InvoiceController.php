<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\LookupDetail;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Student Finance > Invoice (PAGEID 828 / MENUID 1023).
 *
 * Source: FIMS BL `DT_SF_INVOICE` (main listing) + `DT_DEBIT_LIST`
 * (per-invoice debit detail drilldown). Reads from
 * DB_SECOND_DATABASE; legacy scope:
 *   - `cust_invoice_master` rows where
 *       cim_cust_type IN ('A','E') AND
 *       (cim_system_id IS NULL OR cim_system_id IN ('STUD_INV','SF_SPON_INV'))
 *   - the invoice MUST have at least one `cust_invoice_details` row with
 *     `cid_transaction_type='DT'` (legacy enforced this via comma-style
 *     join + WHERE; we model it as a `whereExists` to avoid the
 *     1-to-many duplication that the legacy BL papered over with
 *     `SELECT DISTINCT`).
 *
 * Smart filter keys are kept identical to the legacy `smartFilter`
 * payload so existing operators (dropdowns / autosuggest values) keep
 * working with the same exact values.
 *
 * Joined tables straddle utf8mb3 (cust_invoice_master, cust_invoice_details)
 * vs utf8mb4 (student) collations on the legacy DB, so cross-table
 * comparisons go through `CollationSafeSql::cs()` to avoid SQLSTATE
 * [HY000] 1267 / [42000] 1253.
 *
 * Read-only by design for this migration:
 *   - "View" / "Edit" deep-links target legacy menuID=1062 (Invoice Form),
 *     not migrated — frontend renders those buttons disabled.
 *   - "Detail List" cog opens an inline read-only drilldown via
 *     {id}/details (mirrors `dtcr_listing` from `DT_DEBIT_LIST`).
 *   - The legacy bulk select-all PDF download (`MY_SF_DOWNLOAD_INVOICE`)
 *     and per-invoice cancel SP (`invoice_cancel`) are NOT migrated —
 *     this controller has no mutating endpoint.
 */
class InvoiceController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    // Sortable column whitelist. Keys mirror the legacy `dt_key` so the
    // frontend can pass them through unchanged.
    private const SORTABLE = [
        'InvoiceNo' => 'cim.cim_invoice_no',
        'InvoiceDate' => 'cim.cim_invoice_date',
        'cim_status' => 'cim.cim_status',
        'CustomerId' => 'cim.cim_cust_id',
        'CustomerName' => 'cim.cim_cust_name',
        'CustomerType' => 'cim.cim_cust_type',
        'semester' => 'cim.cim_semester_id',
        'cim_batch_no' => 'cim.cim_batch_no',
        'feeCode' => "cim.cim_extended_field->>'\$.fee_code'",
        'studStatus' => "std.std_extended_field->>'\$.std_status_desc'",
        'Amt' => 'cim.cim_nett_amt',
        'Balance' => 'cim.cim_bal_amt',
    ];

    // Same status set as the legacy "Invoice Status" lookup
    //   SELECT wfl_code, wfl_desc FROM wf_lookup
    //   WHERE wfl_code IN ('APPROVE','REJECT','ENTRY','CANCEL')
    private const STATUSES = [
        'ENTRY' => 'Entry',
        'APPROVE' => 'Approved',
        'REJECT' => 'Rejected',
        'CANCEL' => 'Cancelled',
    ];

    public function options(): JsonResponse
    {
        $status = array_map(
            fn ($id, $label) => ['id' => $id, 'label' => $label],
            array_keys(self::STATUSES),
            array_values(self::STATUSES),
        );

        $feeCategory = LookupDetail::query()
            ->where('lma_code_name', 'FCATEGORY')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => (string) $r->lde_value,
                'label' => $r->lde_description ? $r->lde_value.' - '.$r->lde_description : (string) $r->lde_value,
            ])
            ->values();

        $programLevel = LookupDetail::query()
            ->where('lma_code_name', 'PROGRAM_LEVEL')
            ->orderBy('lde_description')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => (string) $r->lde_value,
                'label' => $r->lde_description ? $r->lde_value.' - '.$r->lde_description : (string) $r->lde_value,
            ])
            ->values();

        $studentStatus = LookupDetail::query()
            ->where('lma_code_name', 'STUDENT_STATUS')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => (string) $r->lde_value,
                'label' => $r->lde_description ? (string) $r->lde_description : (string) $r->lde_value,
            ])
            ->values();

        $studyCategory = LookupDetail::query()
            ->where('lma_code_name', 'like', '%METHOD_STUDY%')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => (string) $r->lde_value,
                'label' => $r->lde_description ? (string) $r->lde_description : (string) $r->lde_value,
            ])
            ->values();

        $citizenship = DB::connection('mysql_secondary')
            ->table('country')
            ->orderBy('cny_country_code')
            ->get(['cny_country_code', 'cny_country_desc'])
            ->map(fn ($r) => [
                'id' => (string) $r->cny_country_code,
                'label' => $r->cny_country_desc ? (string) $r->cny_country_desc : (string) $r->cny_country_code,
            ])
            ->values();

        $nationality = LookupDetail::query()
            ->where('lma_code_name', 'NATIONALITY')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => (string) $r->lde_value,
                'label' => $r->lde_description ? (string) $r->lde_description : (string) $r->lde_value,
            ])
            ->values();

        $customerType = LookupDetail::query()
            ->where('lma_code_name', 'CUSTOMER_TYPE')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'id' => (string) $r->lde_value,
                'label' => $r->lde_description ? (string) $r->lde_description : (string) $r->lde_value,
            ])
            ->values();

        return $this->sendOk([
            'status' => $status,
            'feeCategory' => $feeCategory,
            'programLevel' => $programLevel,
            'studentStatus' => $studentStatus,
            'studyCategory' => $studyCategory,
            'citizenship' => $citizenship,
            'nationality' => $nationality,
            'customerType' => $customerType,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'InvoiceDate');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn = self::SORTABLE[$sortBy] ?? self::SORTABLE['InvoiceDate'];

        // Smart filter inputs (legacy `smartFilter[...]` keys preserved).
        $sf = [
            'sddInvoiceStatus' => trim((string) $request->input('sddInvoiceStatus', '')),
            'cim_invoice_no' => trim((string) $request->input('cim_invoice_no', '')),
            'sddFeeCategory' => trim((string) $request->input('sddFeeCategory', '')),
            'sddSemester' => trim((string) $request->input('sddSemester', '')),
            'sddPtj' => trim((string) $request->input('sddPtj', '')),
            'sddProgramLevel' => trim((string) $request->input('sddProgramLevel', '')),
            'sddStudentStatus' => trim((string) $request->input('sddStudentStatus', '')),
            'sddStudyCategory' => trim((string) $request->input('sddStudyCategory', '')),
            'sddCitizenship' => trim((string) $request->input('sddCitizenship', '')),
            'sddNationality' => trim((string) $request->input('sddNationality', '')),
            'sddCustomerType' => trim((string) $request->input('sddCustomerType', '')),
        ];
        if ($sf['sddInvoiceStatus'] !== '' && ! isset(self::STATUSES[$sf['sddInvoiceStatus']])) {
            $sf['sddInvoiceStatus'] = '';
        }

        // Customer/Sponsor ID — combines the legacy `txtCustomerName` and
        // `sddSponsorCode` smart-filter keys (both filtered cim_cust_id).
        // We accept the shared key `cim_cust_id` (preferred) plus the
        // legacy keys for backward compat.
        $custId = trim((string) (
            $request->input('cim_cust_id')
            ?? $request->input('txtCustomerName')
            ?? $request->input('sddSponsorCode')
            ?? ''
        ));

        $base = $this->baseQuery();

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                'LOWER(CONCAT_WS(\'__\','
                    .'IFNULL(cim.cim_invoice_no, \'\'),'
                    .'IFNULL(cim.cim_status, \'\'),'
                    .'IFNULL(cim.cim_cust_id, \'\'),'
                    .'IFNULL(cim.cim_cust_name, \'\'),'
                    .'IFNULL(cim.cim_extended_field->>\'$.cim_cust_type_desc\','
                        .'IF(cim.cim_cust_type=\'E\',\'PENAJA\',\'PELAJAR\')),'
                    .'IFNULL(cim.cim_semester_id, \'\'),'
                    .'IFNULL(cim.cim_extended_field->>\'$.fee_code\', \'\'),'
                    .'IFNULL(std.std_extended_field->>\'$.std_status_desc\', \'\'),'
                    .'IFNULL(cim.cim_nett_amt, \'\'),'
                    .'IFNULL(cim.cim_batch_no, \'\'),'
                    .'IFNULL(cim.cim_bal_amt, \'\'),'
                    .'IFNULL(DATE_FORMAT(cim.cim_invoice_date, \'%d/%m/%Y %H:%i\'), \'\')'
                .')) LIKE ?',
                [$like]
            );
        }

        if ($sf['sddInvoiceStatus'] !== '') {
            $base->where('cim.cim_status', $sf['sddInvoiceStatus']);
        }

        if ($sf['cim_invoice_no'] !== '') {
            $like = $this->likeEscape(mb_strtolower($sf['cim_invoice_no'], 'UTF-8'));
            $base->whereRaw('LOWER(IFNULL(cim.cim_invoice_no, \'\')) LIKE ?', [$like]);
        }

        if ($sf['sddSemester'] !== '') {
            $base->where('cim.cim_semester_id', $sf['sddSemester']);
        }

        if ($sf['sddPtj'] !== '') {
            // Legacy filter is on cust_invoice_details.oun_code; require the
            // invoice to have at least one DT detail row with this PTJ.
            $sf_ptj = $sf['sddPtj'];
            $base->whereExists(function ($sub) use ($sf_ptj) {
                $sub->select(DB::raw(1))
                    ->from('cust_invoice_details as cidf')
                    ->whereColumn('cidf.cim_cust_invoice_id', 'cim.cim_cust_invoice_id')
                    ->where('cidf.cid_transaction_type', 'DT')
                    ->where('cidf.oun_code', $sf_ptj);
            });
        }

        if ($sf['sddFeeCategory'] !== '') {
            $sf_cat = $sf['sddFeeCategory'];
            $base->whereExists(function ($sub) use ($sf_cat) {
                $sub->select(DB::raw(1))
                    ->from('cust_invoice_details as cidf2')
                    ->whereColumn('cidf2.cim_cust_invoice_id', 'cim.cim_cust_invoice_id')
                    ->where('cidf2.cid_transaction_type', 'DT')
                    ->where('cidf2.cii_item_category', $sf_cat);
            });
        }

        if ($sf['sddProgramLevel'] !== '') {
            $base->where('std.std_program_level', $sf['sddProgramLevel']);
        }
        if ($sf['sddStudentStatus'] !== '') {
            $base->where('std.std_status', $sf['sddStudentStatus']);
        }
        if ($sf['sddStudyCategory'] !== '') {
            $base->where('std.std_method_study', $sf['sddStudyCategory']);
        }
        if ($sf['sddCitizenship'] !== '') {
            $base->where('std.std_citizenship_country', $sf['sddCitizenship']);
        }
        if ($sf['sddNationality'] !== '') {
            $base->where('std.std_citizenship_status', $sf['sddNationality']);
        }
        if ($sf['sddCustomerType'] !== '') {
            $base->where('cim.cim_cust_type', $sf['sddCustomerType']);
        }
        if ($custId !== '') {
            $base->where('cim.cim_cust_id', $custId);
        }

        // Distinct count + grand total.
        $countSub = (clone $base)
            ->select('cim.cim_cust_invoice_id', 'cim.cim_nett_amt')
            ->distinct();
        $aggregate = DB::connection('mysql_secondary')
            ->query()
            ->fromSub($countSub, 'aa')
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(cim_nett_amt), 0) as s')
            ->first();
        $total = (int) ($aggregate->c ?? 0);
        $grand = (float) ($aggregate->s ?? 0);

        $rows = (clone $base)
            ->select([
                'cim.cim_cust_invoice_id',
                'cim.cim_invoice_no',
                'cim.cim_invoice_date',
                'cim.cim_status',
                'cim.cim_cust_id',
                'cim.cim_cust_name',
                'cim.cim_cust_type',
                'cim.cim_semester_id',
                'cim.cim_batch_no',
                'cim.cim_nett_amt',
                'cim.cim_bal_amt',
                DB::raw("cim.cim_extended_field->>'\$.fee_code' as fee_code"),
                DB::raw("cim.cim_extended_field->>'\$.cim_cust_type_desc' as cust_type_desc"),
                DB::raw("cim.cim_extended_field->>'\$.cim_status_desc' as status_inv"),
                DB::raw("std.std_extended_field->>'\$.std_status_desc' as student_status_desc"),
            ])
            ->distinct()
            ->orderBy(DB::raw($sortColumn), $sortDir)
            ->orderBy('cim.cim_cust_invoice_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $custTypeLabel = $r->cust_type_desc
                ?: ($r->cim_cust_type === 'E' ? 'PENAJA' : 'PELAJAR');

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'id' => (int) $r->cim_cust_invoice_id,
                'invoiceNo' => $r->cim_invoice_no,
                'invoiceDate' => $r->cim_invoice_date
                    ? Carbon::parse($r->cim_invoice_date)->format('d/m/Y')
                    : null,
                'invoiceDateIso' => $r->cim_invoice_date
                    ? Carbon::parse($r->cim_invoice_date)->toIso8601String()
                    : null,
                'status' => $r->cim_status,
                'statusLabel' => $r->status_inv ?: $r->cim_status,
                'customerId' => $r->cim_cust_id,
                'customerName' => $r->cim_cust_name,
                'customerType' => $r->cim_cust_type,
                'customerTypeLabel' => $custTypeLabel,
                'semester' => $r->cim_semester_id,
                'batchNo' => $r->cim_batch_no,
                'feeCode' => $r->fee_code,
                'studentStatus' => $r->student_status_desc,
                'amount' => $r->cim_nett_amt !== null ? (float) $r->cim_nett_amt : 0.0,
                'balance' => $r->cim_bal_amt !== null ? (float) $r->cim_bal_amt : 0.0,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => [
                'totalAmt' => $grand,
            ],
        ]);
    }

    /**
     * Per-invoice debit detail list. Mirrors the `dtcr_listing` action of
     * the legacy `DT_DEBIT_LIST` BL (read-only). Returns all detail rows
     * (no pagination — typical invoices have at most a handful of lines)
     * plus the legacy footer aggregates so the drawer can show totals.
     */
    public function details(int $id): JsonResponse
    {
        $exists = DB::connection('mysql_secondary')
            ->table('cust_invoice_master')
            ->where('cim_cust_invoice_id', $id)
            ->exists();
        if (! $exists) {
            return $this->sendError(404, 'NOT_FOUND', 'Invoice not found');
        }

        $rows = DB::connection('mysql_secondary')
            ->table('cust_invoice_details')
            ->where('cim_cust_invoice_id', $id)
            ->where('cid_transaction_type', 'DT')
            ->select([
                'cid_cust_invoice_detl_id',
                DB::raw("cid_extended_field->>'\$.cii_item_category_desc' as item"),
                DB::raw("cid_extended_field->>'\$.cii_item_code_desc' as sub_item"),
                DB::raw("cid_extended_field->>'\$.fty_fund_type_desc' as fund_type"),
                DB::raw("cid_extended_field->>'\$.at_activity_code_desc' as activity_code"),
                DB::raw("cid_extended_field->>'\$.oun_desc' as ptj_code"),
                DB::raw("cid_extended_field->>'\$.ccr_costcentre_charged_desc' as costcentre"),
                DB::raw("cid_extended_field->>'\$.cpa_project_desc' as code_so"),
                DB::raw("cid_extended_field->>'\$.acm_acct_desc' as acct_code"),
                'cid_taxcode',
                'cid_taxamt',
                'cid_total_amt',
                'cid_crnote_amt',
                'cid_dnnote_amt',
                'cid_dcnote_amt',
                'cid_paid_amt',
                'cid_bal_amt',
            ])
            ->orderBy('cid_cust_invoice_detl_id')
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => $i + 1,
            'id' => (int) $r->cid_cust_invoice_detl_id,
            'item' => $r->item,
            'subItem' => $r->sub_item,
            'fundType' => $r->fund_type,
            'activityCode' => $r->activity_code,
            'ptjCode' => $r->ptj_code,
            'costcentre' => $r->costcentre,
            'codeSO' => $r->code_so,
            'acctCode' => $r->acct_code,
            'taxCode' => ($r->cid_taxamt !== null && $r->cid_taxamt > 0) ? $r->cid_taxcode : null,
            'taxAmt' => ($r->cid_taxamt !== null && $r->cid_taxamt > 0) ? (float) $r->cid_taxamt : null,
            'amt' => $r->cid_total_amt !== null ? (float) $r->cid_total_amt : 0.0,
            'cnAmt' => $r->cid_crnote_amt !== null ? (float) $r->cid_crnote_amt : 0.0,
            'dbAmt' => $r->cid_dnnote_amt !== null ? (float) $r->cid_dnnote_amt : 0.0,
            'dcAmt' => $r->cid_dcnote_amt !== null ? (float) $r->cid_dcnote_amt : 0.0,
            'totalAmt' => $r->cid_paid_amt !== null ? (float) $r->cid_paid_amt : 0.0,
            'balAmt' => $r->cid_bal_amt !== null ? (float) $r->cid_bal_amt : 0.0,
        ]);

        $footer = [
            'amt' => (float) $rows->sum('cid_total_amt'),
            'cnAmt' => (float) $rows->sum('cid_crnote_amt'),
            'dbAmt' => (float) $rows->sum('cid_dnnote_amt'),
            'dcAmt' => (float) $rows->sum('cid_dcnote_amt'),
            'totalAmt' => (float) $rows->sum('cid_paid_amt'),
            'balAmt' => (float) $rows->sum('cid_bal_amt'),
        ];

        return $this->sendOk([
            'rows' => $data,
            'footer' => $footer,
        ]);
    }

    /**
     * Common base query: cust_invoice_master LEFT JOIN student, scoped
     * to the legacy `cim_cust_type IN ('A','E')` AND
     * (cim_system_id IS NULL OR IN ('STUD_INV','SF_SPON_INV')) AND
     * the invoice has at least one DT detail row.
     */
    private function baseQuery(): Builder
    {
        $coll = 'COLLATE utf8mb4_unicode_ci';

        return DB::connection('mysql_secondary')
            ->table('cust_invoice_master as cim')
            ->leftJoin('student as std', function ($join) use ($coll) {
                $join->on(
                    DB::raw("cim.cim_cust_id $coll"),
                    '=',
                    DB::raw("std.std_student_id $coll"),
                );
            })
            ->whereIn('cim.cim_cust_type', ['A', 'E'])
            ->where(function ($w) {
                $w->whereNull('cim.cim_system_id')
                    ->orWhereIn('cim.cim_system_id', ['STUD_INV', 'SF_SPON_INV']);
            })
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('cust_invoice_details as cid0')
                    ->whereColumn('cid0.cim_cust_invoice_id', 'cim.cim_cust_invoice_id')
                    ->where('cid0.cid_transaction_type', 'DT');
            });
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
