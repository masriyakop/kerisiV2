<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CustInvoiceDetails;
use App\Models\CustInvoiceMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Student Finance > Manual Invoice Listing (PAGEID 2389 / MENUID 2897).
 *
 * Source: FIMS BL `DT_SF_MANUAL_INV_LISTING`. Reads from
 * `cust_invoice_master` scoped to `cim_system_id='STUD_INV'` AND
 * `cim_invoice_type='12'`. Smart filters and the grand-total footer are
 * kept identical to the legacy BL; columns mirror `dt_bi` / `dt_key`
 * from PAGE_SECOND_LEVEL_MENU.json.
 *
 * Delete cascades to `cust_invoice_details` then `cust_invoice_master`
 * inside a transaction, gated to `cim_status='DRAFT'` per the legacy
 * `dt_js` guard (`row.cim_status!='DRAFT'?'disabled':…`). The legacy
 * BL itself has no server-side guard — we add one here to prevent
 * deletions of approved / verified / cancelled invoices via direct API
 * access.
 *
 * The View/Edit deep-links point at MENUID 2898 (Manual Invoice Form)
 * which is NOT migrated yet; the frontend renders those buttons
 * disabled until the form is migrated.
 */
class ManualInvoiceListingController extends Controller
{
    use ApiResponse;

    // Matches the legacy "Status" smart-filter lookup:
    //   SELECT wfl_code, wfl_desc FROM wf_lookup
    //   WHERE wfl_code IN ('APPROVE','REJECT','ENTRY','CANCEL','VERIFIED','ENDORSE')
    private const STATUSES = [
        'APPROVE' => 'Approved',
        'ENTRY' => 'Entry',
        'VERIFIED' => 'Verified',
        'ENDORSE' => 'Endorsed',
        'REJECT' => 'Rejected',
        'CANCEL' => 'Cancelled',
    ];

    // Matches the legacy "Debtor Type" smart-filter lookup scoped to
    // CUSTOMER_TYPE where lde_value IN ('A','E'). Labels follow the legacy
    // IF(cim_cust_type='E','PENAJA','PELAJAR') convention.
    private const CUST_TYPES = [
        'A' => 'PELAJAR',
        'E' => 'PENAJA',
    ];

    private const SORTABLE = [
        'cim_invoice_no',
        'cim_invoice_date',
        'cim_cust_id',
        'cim_cust_name',
        'cim_status',
        'cim_total_amt',
    ];

    public function options(): JsonResponse
    {
        return $this->sendOk([
            'debtorType' => array_map(
                fn ($id, $label) => ['id' => $id, 'label' => $label],
                array_keys(self::CUST_TYPES),
                array_values(self::CUST_TYPES),
            ),
            'status' => array_map(
                fn ($id, $label) => ['id' => $id, 'label' => $label],
                array_keys(self::STATUSES),
                array_values(self::STATUSES),
            ),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'cim_invoice_date');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'cim_invoice_date';
        }

        $invoiceDate = trim((string) $request->input('cim_invoice_date', '')); // dd/mm/yyyy
        $custType = trim((string) $request->input('cim_cust_type', ''));
        $status = trim((string) $request->input('cim_status', ''));
        if ($custType !== '' && ! isset(self::CUST_TYPES[$custType])) {
            $custType = '';
        }
        if ($status !== '' && ! isset(self::STATUSES[$status])) {
            $status = '';
        }

        $base = CustInvoiceMaster::query()
            ->where('cim_system_id', 'STUD_INV')
            ->where('cim_invoice_type', '12');

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(cim_invoice_no, ''),
                    IFNULL(cim_status, ''),
                    IFNULL(cim_cust_id, ''),
                    IFNULL(cim_cust_name, ''),
                    IFNULL(cim_extended_field->>'$.cim_cust_type_desc',
                        IF(cim_cust_type='E','PENAJA','PELAJAR')),
                    IFNULL(cim_semester_id, ''),
                    IFNULL(cim_total_amt, ''),
                    IFNULL(DATE_FORMAT(cim_invoice_date, '%d/%m/%Y %H:%i'), '')
                )) LIKE ?",
                [$like]
            );
        }

        if ($invoiceDate !== '') {
            // Legacy BL treats this as a substring match so "04/2021" still
            // works. Preserve that behaviour instead of parsing to a range.
            $like = $this->likeEscape(mb_strtolower($invoiceDate, 'UTF-8'));
            $base->whereRaw(
                "LOWER(IFNULL(DATE_FORMAT(cim_invoice_date, '%d/%m/%Y'), '')) LIKE ?",
                [$like]
            );
        }

        if ($custType !== '') {
            $base->where('cim_cust_type', $custType);
        }

        if ($status !== '') {
            $base->where('cim_status', $status);
        }

        $total = (clone $base)->count();
        $grand = (clone $base)->sum('cim_total_amt');

        $rows = (clone $base)
            ->select([
                'cim_cust_invoice_id',
                'cim_invoice_no',
                'cim_invoice_date',
                'cim_status',
                'cim_cust_id',
                'cim_cust_name',
                'cim_cust_type',
                'cim_total_amt',
                'cim_crnote_amt',
                'cim_dnnote_amt',
                'cim_dcnote_amt',
                'cim_paid_amt',
                'cim_bal_amt',
                DB::raw("cim_extended_field->>'\$.cim_cust_type_desc' as cust_type_desc"),
            ])
            ->orderBy($sortBy, $sortDir)
            ->orderBy('cim_cust_invoice_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $debtorTypeLabel = $r->cust_type_desc
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
                'debtorId' => $r->cim_cust_id,
                'debtorName' => $r->cim_cust_name,
                'debtorType' => $r->cim_cust_type,
                'debtorTypeLabel' => $debtorTypeLabel,
                'totalAmt' => $r->cim_total_amt !== null ? (float) $r->cim_total_amt : 0.0,
                'crNoteAmt' => $r->cim_crnote_amt !== null ? (float) $r->cim_crnote_amt : 0.0,
                'dnNoteAmt' => $r->cim_dnnote_amt !== null ? (float) $r->cim_dnnote_amt : 0.0,
                'dcNoteAmt' => $r->cim_dcnote_amt !== null ? (float) $r->cim_dcnote_amt : 0.0,
                'paidAmt' => $r->cim_paid_amt !== null ? (float) $r->cim_paid_amt : 0.0,
                'balAmt' => $r->cim_bal_amt !== null ? (float) $r->cim_bal_amt : 0.0,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => [
                'totalAmt' => (float) ($grand ?? 0),
            ],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $invoice = CustInvoiceMaster::query()
            ->where('cim_cust_invoice_id', $id)
            ->where('cim_system_id', 'STUD_INV')
            ->where('cim_invoice_type', '12')
            ->first(['cim_cust_invoice_id', 'cim_status']);

        if (! $invoice) {
            return $this->sendError(404, 'NOT_FOUND', 'Manual invoice not found');
        }

        if ($invoice->cim_status !== 'DRAFT') {
            return $this->sendError(
                409,
                'INVOICE_NOT_DRAFT',
                'Only DRAFT invoices can be deleted.',
            );
        }

        DB::connection('mysql_secondary')->transaction(function () use ($invoice) {
            CustInvoiceDetails::query()
                ->where('cim_cust_invoice_id', $invoice->cim_cust_invoice_id)
                ->delete();
            CustInvoiceMaster::query()
                ->where('cim_cust_invoice_id', $invoice->cim_cust_invoice_id)
                ->delete();
        });

        return $this->sendOk(['success' => true]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
