<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\DebitNoteDetails;
use App\Models\DebitNoteMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Account Receivable > Debit Note" list (PAGEID 1461 / MENUID 1042).
 *
 * Source: FIMS BL `DT_AR_DEBIT_NOTE_LIST` — scopes to `dnm_system_id='AR_DN'`,
 * joins `cust_invoice_master` (status='APPROVE') for invoice total/balance.
 * Delete cascades to `debit_note_details` then `debit_note_master`.
 */
class DebitNoteController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dnm_dnnote_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'dnm_dnnote_no', 'dnm_dnnote_date', 'dnm_cust_id', 'dnm_cust_name',
            'cim_invoice_no', 'dnm_dn_total_amount',
        ];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'dnm_dnnote_no';
        }

        $query = DebitNoteMaster::query()->where('dnm_system_id', 'AR_DN');

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function (Builder $b) use ($like) {
                $b->orWhereRaw("LOWER(IFNULL(dnm_dnnote_no, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(dnm_cust_id, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(dnm_cust_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(cim_invoice_no, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(dnm_dnnote_desc, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(dnm_extended_field->>'$.dnm_cust_type_desc', '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(dnm_extended_field->>'$.dnm_status_dn_desc', '')) LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->select([
                'dnm_debit_note_master_id',
                'dnm_dnnote_no',
                'dnm_dnnote_date',
                'dnm_cust_id',
                'dnm_cust_name',
                'cim_invoice_no',
                'dnm_dnnote_desc',
                'dnm_dn_total_amount',
                DB::raw("dnm_extended_field->>'\$.dnm_cust_type_desc' as cust_type_desc"),
                DB::raw("dnm_extended_field->>'\$.dnm_status_dn_desc' as status_desc"),
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
                'id' => (string) $r->dnm_debit_note_master_id,
                'debitNoteNo' => $r->dnm_dnnote_no,
                'debitNoteDate' => $r->dnm_dnnote_date,
                'customerId' => $r->dnm_cust_id,
                'customerName' => $r->dnm_cust_name,
                'customerType' => $r->cust_type_desc,
                'description' => $r->dnm_dnnote_desc,
                'invoiceNo' => $r->cim_invoice_no,
                'invoiceTotalAmount' => $inv ? (float) $inv->total_amt : 0.0,
                'invoiceBalanceAmount' => $inv ? (float) $inv->bal_amt : 0.0,
                'debitNoteTotalAmount' => (float) ($r->dnm_dn_total_amount ?? 0),
                'status' => $r->status_desc,
            ];
        });

        $footer = [
            'debitNoteTotalAmount' => (float) (clone $query)->sum('dnm_dn_total_amount'),
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
        $note = DebitNoteMaster::query()
            ->where('dnm_debit_note_master_id', $id)
            ->where('dnm_system_id', 'AR_DN')
            ->first(['dnm_debit_note_master_id']);

        if (! $note) {
            return $this->sendError(404, 'NOT_FOUND', 'Debit note not found');
        }

        DB::connection('mysql_secondary')->transaction(function () use ($note) {
            DebitNoteDetails::query()
                ->where('dnm_debit_note_master_id', $note->dnm_debit_note_master_id)
                ->delete();

            DebitNoteMaster::query()
                ->where('dnm_debit_note_master_id', $note->dnm_debit_note_master_id)
                ->delete();
        });

        return $this->sendOk(['success' => true]);
    }

    private function likeEscape(string $needle): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
    }
}
