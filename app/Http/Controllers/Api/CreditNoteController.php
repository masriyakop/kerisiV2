<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CreditNoteDetails;
use App\Models\CreditNoteMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Account Receivable > Credit Note" list (PAGEID 1459 / MENUID 1041).
 *
 * Source: FIMS BL `DT_AR_CREDIT_NOTE_LIST` — scopes to `cnm_system_id='AR_CN'`
 * and joins `cust_invoice_master` (status='APPROVE') to surface the invoice
 * total/balance alongside the credit note row. Delete cascades to
 * `credit_note_details` then `credit_note_master`, wrapped in a transaction.
 *
 * The legacy BL used a single concatenated search over many fields (ref no,
 * customer id/name/type, date, status, amount). We replicate that with a
 * case-insensitive OR chain. No smart filter is shipped for this list — the
 * legacy file has the smart-filter block commented out.
 *
 * Meta of the response also carries the aggregate footer (`grandCnAmt`,
 * `invoiceTotalAmt`, `invoiceBalanceAmt`) to match the legacy footer row.
 */
class CreditNoteController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'cnm_crnote_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'cnm_crnote_no', 'cnm_crnote_date', 'cnm_cust_id', 'cnm_cust_name',
            'cim_invoice_no', 'cnm_cn_total_amount',
        ];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'cnm_crnote_no';
        }

        $query = CreditNoteMaster::query()->where('cnm_system_id', 'AR_CN');

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function (Builder $b) use ($like) {
                $b->orWhereRaw("LOWER(IFNULL(cnm_crnote_no, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(cnm_cust_id, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(cnm_cust_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(cim_invoice_no, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(cnm_crnote_desc, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(cnm_extended_field->>'$.cnm_cust_type_desc', '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(cnm_extended_field->>'$.cnm_status_cd_desc', '')) LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->select([
                'cnm_credit_note_master_id',
                'cnm_crnote_no',
                'cnm_crnote_date',
                'cnm_cust_id',
                'cnm_cust_name',
                'cim_invoice_no',
                'cnm_crnote_desc',
                'cnm_cn_total_amount',
                DB::raw("cnm_extended_field->>'\$.cnm_cust_type_desc' as cust_type_desc"),
                DB::raw("cnm_extended_field->>'\$.cnm_status_cd_desc' as status_desc"),
            ])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // Resolve linked invoice totals once per batch so we avoid N+1 sub-selects.
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
                'id' => (string) $r->cnm_credit_note_master_id,
                'creditNoteNo' => $r->cnm_crnote_no,
                'creditNoteDate' => $r->cnm_crnote_date,
                'customerId' => $r->cnm_cust_id,
                'customerName' => $r->cnm_cust_name,
                'customerType' => $r->cust_type_desc,
                'description' => $r->cnm_crnote_desc,
                'invoiceNo' => $r->cim_invoice_no,
                'invoiceTotalAmount' => $inv ? (float) $inv->total_amt : 0.0,
                'invoiceBalanceAmount' => $inv ? (float) $inv->bal_amt : 0.0,
                'creditNoteTotalAmount' => (float) ($r->cnm_cn_total_amount ?? 0),
                'status' => $r->status_desc,
            ];
        });

        $footer = [
            'creditNoteTotalAmount' => (float) (clone $query)->sum('cnm_cn_total_amount'),
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
        $note = CreditNoteMaster::query()
            ->where('cnm_credit_note_master_id', $id)
            ->where('cnm_system_id', 'AR_CN')
            ->first(['cnm_credit_note_master_id']);

        if (! $note) {
            return $this->sendError(404, 'NOT_FOUND', 'Credit note not found');
        }

        DB::connection('mysql_secondary')->transaction(function () use ($note) {
            CreditNoteDetails::query()
                ->where('cnm_credit_note_master_id', $note->cnm_credit_note_master_id)
                ->delete();

            CreditNoteMaster::query()
                ->where('cnm_credit_note_master_id', $note->cnm_credit_note_master_id)
                ->delete();
        });

        return $this->sendOk(['success' => true]);
    }

    private function likeEscape(string $needle): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
    }
}
