<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\DiscountNoteDetails;
use App\Models\DiscountNoteMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Account Receivable > Discount Note" list (PAGEID 1463 / MENUID 1043).
 *
 * Source: FIMS BL idx-35 in ACCOUNT_RECEIVABLE_BL.json (null-title; the body
 * operates on `discount_note_master` / `discount_note_details` with
 * `dcm_system_id='AR_DC'` and mirrors `DT_AR_CREDIT_NOTE_LIST`). Status label
 * in the legacy BL falls back from `dcm_status_dc_desc` to `dcm_status_cd_desc`
 * and we reproduce that COALESCE.
 *
 * Delete cascades to `discount_note_details` then `discount_note_master`
 * inside a transaction.
 */
class DiscountNoteController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dcm_dcnote_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'dcm_dcnote_no', 'dcm_dcnote_date', 'dcm_cust_id', 'dcm_cust_name',
            'cim_invoice_no', 'dcm_dc_total_amount',
        ];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'dcm_dcnote_no';
        }

        $query = DiscountNoteMaster::query()->where('dcm_system_id', 'AR_DC');

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function (Builder $b) use ($like) {
                $b->orWhereRaw("LOWER(IFNULL(dcm_dcnote_no, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(dcm_cust_id, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(dcm_cust_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(cim_invoice_no, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(dcm_dcnote_desc, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(dcm_extended_field->>'$.dcm_cust_type_desc', '')) LIKE ?", [$like])
                    ->orWhereRaw(
                        "LOWER(IFNULL(COALESCE(dcm_extended_field->>'$.dcm_status_dc_desc', dcm_extended_field->>'$.dcm_status_cd_desc'), '')) LIKE ?",
                        [$like]
                    );
            });
        }

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->select([
                'dcm_discount_note_master_id',
                'dcm_dcnote_no',
                'dcm_dcnote_date',
                'dcm_cust_id',
                'dcm_cust_name',
                'cim_invoice_no',
                'dcm_dcnote_desc',
                'dcm_dc_total_amount',
                DB::raw("dcm_extended_field->>'\$.dcm_cust_type_desc' as cust_type_desc"),
                DB::raw("COALESCE(dcm_extended_field->>'\$.dcm_status_dc_desc', dcm_extended_field->>'\$.dcm_status_cd_desc') as status_desc"),
            ])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $invoices = $rows->pluck('cim_invoice_no')->filter()->unique()->values()->all();
        $invoiceTotals = [];
        if (! empty($invoices)) {
            $invoiceTotals = DB::connection('mysql_secondary')
                ->table('cust_invoice_master')
                ->where('cim_status', 'APPROVE')
                ->whereIn('cim_invoice_no', $invoices)
                ->groupBy('cim_invoice_no')
                ->selectRaw('cim_invoice_no, SUM(cim_total_amt) as total_amt, SUM(cim_bal_amt) as bal_amt')
                ->get()
                ->keyBy('cim_invoice_no')
                ->toArray();
        }

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit, $invoiceTotals) {
            $inv = $invoiceTotals[$r->cim_invoice_no] ?? null;

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'id' => (string) $r->dcm_discount_note_master_id,
                'discountNoteNo' => $r->dcm_dcnote_no,
                'discountNoteDate' => $r->dcm_dcnote_date,
                'customerId' => $r->dcm_cust_id,
                'customerName' => $r->dcm_cust_name,
                'customerType' => $r->cust_type_desc,
                'description' => $r->dcm_dcnote_desc,
                'invoiceNo' => $r->cim_invoice_no,
                'invoiceTotalAmount' => $inv ? (float) $inv->total_amt : 0.0,
                'invoiceBalanceAmount' => $inv ? (float) $inv->bal_amt : 0.0,
                'discountNoteTotalAmount' => (float) ($r->dcm_dc_total_amount ?? 0),
                'status' => $r->status_desc,
            ];
        });

        $footer = [
            'discountNoteTotalAmount' => (float) (clone $query)->sum('dcm_dc_total_amount'),
            'invoiceTotalAmount' => array_sum(array_map(
                fn ($v) => (float) $v->total_amt,
                $invoiceTotals,
            )),
            'invoiceBalanceAmount' => array_sum(array_map(
                fn ($v) => (float) $v->bal_amt,
                $invoiceTotals,
            )),
        ];

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => $footer,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $note = DiscountNoteMaster::query()
            ->where('dcm_discount_note_master_id', $id)
            ->where('dcm_system_id', 'AR_DC')
            ->first(['dcm_discount_note_master_id']);

        if (! $note) {
            return $this->sendError(404, 'NOT_FOUND', 'Discount note not found');
        }

        DB::connection('mysql_secondary')->transaction(function () use ($note) {
            DiscountNoteDetails::query()
                ->where('dcm_discount_note_master_id', $note->dcm_discount_note_master_id)
                ->delete();

            DiscountNoteMaster::query()
                ->where('dcm_discount_note_master_id', $note->dcm_discount_note_master_id)
                ->delete();
        });

        return $this->sendOk(['success' => true]);
    }

    private function likeEscape(string $needle): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
    }
}
