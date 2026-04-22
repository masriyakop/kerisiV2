<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelNoteRequest;
use App\Http\Requests\SaveDebitNoteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CustInvoiceDetails;
use App\Models\CustInvoiceMaster;
use App\Models\DebitNoteDetails;
use App\Models\DebitNoteMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AR > Debit Note Form (PAGEID 1476 / MENUID 1783).
 *
 * Source: BL `DT_AR_DEBIT_NOTE_FORM`. See `CreditNoteFormController` for the
 * same action-to-REST mapping and the workflow stubbing rationale — this
 * controller mirrors the credit-note form but targets `debit_note_master`
 * / `debit_note_details`.
 *
 * Legacy mapping:
 *   action=temp / tempCredit  → invoiceLines (debit = DT, credit = CR on
 *                               the Debit Note; same convention as legacy)
 *   action=saveDnNote         → saveDraft
 *   action=submitDnNote       → submit (stub)
 *   $_GET['canceldn']         → cancel (stub)
 *   $_GET['dt_processFlow']   → processFlow (stub; empty list)
 *   action=detailMaster /
 *   detailHead                → show (combined master + both tabs)
 */
class DebitNoteFormController extends Controller
{
    use ApiResponse;

    public function invoiceLines(Request $request): JsonResponse
    {
        $invoiceId = (string) $request->input('invoice_id', '');
        if ($invoiceId === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'invoice_id is required');
        }

        $all = CustInvoiceDetails::query()
            ->where('cim_cust_invoice_id', $invoiceId)
            ->get([
                'cid_cust_invoice_detl_id',
                'cim_cust_invoice_id',
                'cii_item_category',
                'cii_item_code',
                'fty_fund_type',
                'at_activity_code',
                'oun_code',
                'ccr_costcentre',
                'cpa_project_no',
                'acm_acct_code',
                'cid_taxcode',
                'cid_taxamt',
                'cid_total_amt',
                'cid_crnote_amt',
                'cid_dnnote_amt',
                'cid_dcnote_amt',
                'cid_nett_amt',
                'cid_bal_amt',
                'cid_transaction_type',
                DB::raw("cid_extended_field->>'\$.cii_item_category_desc' as cii_item_category_desc"),
                DB::raw("cid_extended_field->>'\$.cii_item_code_desc' as cii_item_code_desc"),
                DB::raw("cid_extended_field->>'\$.fty_fund_type_desc' as fty_fund_type_desc"),
                DB::raw("cid_extended_field->>'\$.at_activity_code_desc' as at_activity_code_desc"),
                DB::raw("cid_extended_field->>'\$.oun_desc' as oun_desc"),
                DB::raw("cid_extended_field->>'\$.ccr_costcentre_charged_desc' as ccr_costcentre_charged_desc"),
                DB::raw("cid_extended_field->>'\$.acm_acct_desc' as acm_acct_desc"),
            ]);

        $mapLine = function ($r) {
            return [
                'ID' => (string) $r->cid_cust_invoice_detl_id,
                'invoiceId' => (string) $r->cim_cust_invoice_id,
                'feeCategoryId' => $r->cii_item_category,
                'feeCategory' => ($r->cii_item_category !== null || $r->cii_item_category_desc !== null)
                    ? trim((string) $r->cii_item_category) . ' - ' . (string) $r->cii_item_category_desc
                    : null,
                'cii_item_code' => $r->cii_item_code,
                'feeItem' => ($r->cii_item_code !== null || $r->cii_item_code_desc !== null)
                    ? trim((string) $r->cii_item_code) . ' - ' . (string) $r->cii_item_code_desc
                    : null,
                'fty_fund_type' => $r->fty_fund_type,
                'fundType' => $r->fty_fund_type_desc,
                'at_activity_code' => $r->at_activity_code,
                'activityCode' => $r->at_activity_code_desc,
                'oun_code' => $r->oun_code,
                'ptjCode' => $r->oun_desc,
                'ccr_costcentre' => $r->ccr_costcentre,
                'costcentre' => $r->ccr_costcentre_charged_desc,
                'cpa_project_no' => $r->cpa_project_no,
                'codeSO' => $r->cpa_project_no,
                'acm_acct_code' => $r->acm_acct_code,
                'acctCode' => $r->acm_acct_desc,
                'taxCode' => $r->cid_taxcode,
                'taxAmt' => (float) ($r->cid_taxamt ?? 0),
                'amt' => (float) ($r->cid_total_amt ?? 0),
                'totalAmt' => (float) ($r->cid_nett_amt ?? 0),
                'cnAmt' => (float) ($r->cid_crnote_amt ?? 0),
                'dnAmt' => (float) ($r->cid_dnnote_amt ?? 0),
                'dcAmt' => (float) ($r->cid_dcnote_amt ?? 0),
                'balance' => (float) ($r->cid_bal_amt ?? 0),
                'transactionType' => $r->cid_transaction_type,
            ];
        };

        // For Debit Note form: debit tab = DT rows, credit tab = CR rows
        // (see legacy DT_AR_DEBIT_NOTE_FORM `action=temp` / `tempCredit`).
        $debit = $all->where('cid_transaction_type', 'DT')->values()->map($mapLine);
        $credit = $all->where('cid_transaction_type', 'CR')->values()->map($mapLine);

        return $this->sendOk([
            'debit' => $debit,
            'credit' => $credit,
            'invoiceBalance' => (float) $all->where('cid_transaction_type', 'DT')->sum('cid_bal_amt'),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $master = DebitNoteMaster::query()
            ->where('dnm_debit_note_master_id', $id)
            ->where('dnm_system_id', 'AR_DN')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Debit note not found');
        }

        $invoiceMeta = null;
        if ($master->cim_invoice_no) {
            $invoiceMeta = CustInvoiceMaster::query()
                ->where('cim_invoice_no', $master->cim_invoice_no)
                ->first(['cim_cust_invoice_id', 'cim_invoice_no', 'cim_cust_id', 'cim_cust_type', 'cim_bal_amt', 'cim_total_amt', 'cim_status']);
        }

        $details = DebitNoteDetails::query()
            ->where('dnm_debit_note_master_id', $master->dnm_debit_note_master_id)
            ->get();

        $mapDetail = function ($d) {
            $ext = $this->decodeJson($d->dnd_extended_field ?? null);

            return [
                'dnd_id' => (string) $d->dnd_id,
                'dnd_cust_invoice_detl_id' => $d->dnd_cust_invoice_detl_id,
                'ID' => (string) ($d->dnd_cust_invoice_detl_id ?? $d->dnd_id),
                'dnd_item_category' => $d->dnd_item_category,
                'feeCategoryId' => $d->dnd_item_category,
                'feeCategory' => $ext['feeCategory'] ?? $ext['cii_item_category_desc'] ?? null,
                'cii_item_code' => $d->cii_item_code,
                'feeItem' => $ext['feeItem'] ?? $ext['cii_item_code_desc'] ?? null,
                'cnd_detail_desc' => $d->cnd_detail_desc,
                'fty_fund_type' => $d->fty_fund_type,
                'fundType' => $ext['fundType'] ?? $ext['fty_fund_type_desc'] ?? null,
                'at_activity_code' => $d->at_activity_code,
                'activityCode' => $ext['activityCode'] ?? $ext['at_activity_code_desc'] ?? null,
                'oun_code' => $d->oun_code,
                'ptjCode' => $ext['ptjCode'] ?? $ext['oun_desc'] ?? null,
                'ccr_costcentre' => $d->ccr_costcentre,
                'costcentre' => $ext['costcentre'] ?? $ext['ccr_costcentre_charged_desc'] ?? null,
                'cpa_project_no' => $d->cpa_project_no,
                'codeSO' => $d->cpa_project_no,
                'acm_acct_code' => $d->acm_acct_code,
                'acctCode' => $ext['acctCode'] ?? $ext['acm_acct_desc'] ?? null,
                'dnd_taxcode' => $d->dnd_taxcode,
                'taxCode' => $d->dnd_taxcode,
                'taxAmt' => (float) ($d->dnd_dn_taxamt ?? 0),
                'dnd_invoice_amt' => (float) ($d->dnd_invoice_amt ?? 0),
                'amt' => (float) ($d->dnd_invoice_amt ?? 0),
                'dnd_dnnote_amt' => (float) ($d->dnd_dnnote_amt ?? 0),
                'dnAmt' => (float) ($d->dnd_dnnote_amt ?? 0),
                'dnd_bal_amt' => (float) ($d->dnd_bal_amt ?? 0),
                'balance' => (float) ($d->dnd_bal_amt ?? 0),
                'dnd_transaction_type' => $d->dnd_transaction_type,
            ];
        };

        $debit = $details->where('dnd_transaction_type', 'DT')->values()->map($mapDetail);
        $credit = $details->where('dnd_transaction_type', 'CR')->values()->map($mapDetail);

        $ext = $this->decodeJson($master->dnm_extended_field);

        return $this->sendOk([
            'head' => [
                'dnm_debit_note_master_id' => (string) $master->dnm_debit_note_master_id,
                'dnm_dnnote_no' => $master->dnm_dnnote_no,
                'cim_invoice_no' => $master->cim_invoice_no,
                'cim_cust_invoice_id' => $invoiceMeta?->cim_cust_invoice_id ? (string) $invoiceMeta->cim_cust_invoice_id : null,
                'dnm_cust_id' => $master->dnm_cust_id,
                'dnm_cust_type' => $master->dnm_cust_type,
                'dnm_cust_type_desc' => $ext['dnm_cust_type_desc'] ?? null,
                'dnm_cust_name' => $master->dnm_cust_name,
                'dnm_dnnote_desc' => $master->dnm_dnnote_desc,
                'dnm_dnnote_date' => $master->dnm_dnnote_date,
                'dnm_dn_total_amount' => (float) ($master->dnm_dn_total_amount ?? 0),
                'dnm_status_cd' => $master->dnm_status_cd,
                'dnm_status_cd_desc' => $ext['dnm_status_dn_desc'] ?? $master->dnm_status_cd,
                'invoiceTotalAmount' => $invoiceMeta ? (float) ($invoiceMeta->cim_total_amt ?? 0) : 0,
                'invoiceBalanceAmount' => $invoiceMeta ? (float) ($invoiceMeta->cim_bal_amt ?? 0) : 0,
            ],
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    public function saveDraft(SaveDebitNoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $head = $data['head'];
        $username = $this->currentUsername();

        $masterId = (string) ($head['dnm_debit_note_master_id'] ?? '');
        $masterCode = (string) ($head['dnm_dnnote_no'] ?? '');
        $isNew = $masterId === '';

        if ($isNew) {
            $masterId = (string) $this->nextSeq('debit_note_master');
            if ($masterCode === '') {
                $masterCode = $this->generateNoteNo('DN');
            }
        }

        $extended = [
            'dnm_cust_type_desc' => (string) ($head['dnm_cust_type_desc'] ?? ''),
            'dnm_status_dn_desc' => 'Draft',
        ];
        $totalAmt = isset($head['dnm_dn_total_amount']) ? (float) $head['dnm_dn_total_amount'] : null;
        $nowStr = now()->format('Y-m-d H:i:s');

        DB::connection('mysql_secondary')->transaction(function () use ($masterId, $masterCode, $head, $extended, $totalAmt, $nowStr, $username, $isNew, $data) {
            $payload = [
                'dnm_debit_note_master_id' => $masterId,
                'dnm_dnnote_no' => $masterCode,
                'cim_invoice_no' => $head['cim_invoice_no'],
                'dnm_cust_id' => $head['dnm_cust_id'],
                'dnm_cust_type' => $head['dnm_cust_type'],
                'dnm_cust_name' => $head['dnm_cust_name'],
                'dnm_dnnote_desc' => $head['dnm_dnnote_desc'] ?? null,
                'dnm_dnnote_date' => $this->parseLegacyDate($head['dnm_dnnote_date'] ?? null),
                'dnm_dn_total_amount' => $totalAmt,
                'dnm_status_cd' => 'Draft',
                'dnm_system_id' => 'AR_DN',
                'dnm_extended_field' => json_encode($extended, JSON_UNESCAPED_UNICODE),
            ];

            if ($isNew) {
                DebitNoteMaster::query()->create(array_merge($payload, [
                    'createdby' => $username,
                    'createddate' => $nowStr,
                ]));
            } else {
                DebitNoteMaster::query()
                    ->where('dnm_debit_note_master_id', $masterId)
                    ->update(array_merge($payload, [
                        'updatedby' => $username,
                        'updateddate' => $nowStr,
                    ]));
            }

            DebitNoteDetails::query()
                ->where('dnm_debit_note_master_id', $masterId)
                ->delete();

            $this->persistDetails($masterId, $data['debit'] ?? [], 'DT', $username, $nowStr);
            $this->persistDetails($masterId, $data['credit'] ?? [], 'CR', $username, $nowStr);

            // Recompute from persisted DT lines to mirror legacy BL.
            $resolvedTotal = (float) DebitNoteDetails::query()
                ->where('dnm_debit_note_master_id', $masterId)
                ->where('dnd_transaction_type', 'DT')
                ->sum('dnd_dnnote_amt');
            DebitNoteMaster::query()
                ->where('dnm_debit_note_master_id', $masterId)
                ->update(['dnm_dn_total_amount' => $resolvedTotal]);
        });

        return $this->sendOk([
            'status' => 'ok',
            'dnID' => (string) $masterId,
            'debitNoteNo' => $masterCode,
            'status_cd' => 'Draft',
        ]);
    }

    public function submit(string $id): JsonResponse
    {
        $master = DebitNoteMaster::query()
            ->where('dnm_debit_note_master_id', $id)
            ->where('dnm_system_id', 'AR_DN')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Debit note not found');
        }

        $ext = $this->decodeJson($master->dnm_extended_field);
        $ext['dnm_status_dn_desc'] = 'Entry';

        DebitNoteMaster::query()
            ->where('dnm_debit_note_master_id', $id)
            ->update([
                'dnm_status_cd' => 'Entry',
                'dnm_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'updatedby' => $this->currentUsername(),
                'updateddate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->sendOk([
            'status' => 'ok',
            'status_cd' => 'Entry',
            'workflow_stub' => true,
            'message' => 'Debit note marked as Entry. Workflow routing is not yet migrated; approver chain must be configured in a later release.',
        ]);
    }

    public function cancel(CancelNoteRequest $request, string $id): JsonResponse
    {
        $master = DebitNoteMaster::query()
            ->where('dnm_debit_note_master_id', $id)
            ->where('dnm_system_id', 'AR_DN')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Debit note not found');
        }

        $ext = $this->decodeJson($master->dnm_extended_field);
        $ext['dnm_status_dn_desc'] = 'Cancelled';
        $ext['dnm_cancel_reason'] = $request->validated()['cancel_reason'];
        $ext['dnm_cancelled_at'] = now()->toAtomString();
        $ext['dnm_cancelled_by'] = $this->currentUsername();

        DebitNoteMaster::query()
            ->where('dnm_debit_note_master_id', $id)
            ->update([
                'dnm_status_cd' => 'CANCELLED',
                'dnm_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'updatedby' => $this->currentUsername(),
                'updateddate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->sendOk([
            'status' => 'ok',
            'status_cd' => 'CANCELLED',
            'message' => 'Debit note cancelled.',
        ]);
    }

    public function processFlow(string $id): JsonResponse
    {
        $master = DebitNoteMaster::query()
            ->where('dnm_debit_note_master_id', $id)
            ->where('dnm_system_id', 'AR_DN')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Debit note not found');
        }

        return $this->sendOk([], [
            'workflow_stub' => true,
            'note' => 'Workflow history tables are not yet migrated.',
        ]);
    }

    private function persistDetails(string $masterId, array $lines, string $type, string $username, string $nowStr): void
    {
        foreach ($lines as $line) {
            $ext = $line['extended'] ?? [
                'feeCategory' => null,
                'feeItem' => null,
                'fundType' => null,
                'activityCode' => null,
                'ptjCode' => null,
                'costcentre' => null,
                'codeSO' => null,
                'acctCode' => null,
            ];

            DebitNoteDetails::query()->create([
                'dnd_id' => (string) $this->nextSeq('debit_note_details'),
                'dnm_debit_note_master_id' => $masterId,
                'dnd_line_no' => '1',
                'dnd_item_category' => $line['dnd_item_category'] ?? null,
                'cii_item_code' => $line['cii_item_code'] ?? null,
                'cnd_detail_desc' => $line['cnd_detail_desc'] ?? null,
                'fty_fund_type' => $line['fty_fund_type'] ?? null,
                'at_activity_code' => $line['at_activity_code'] ?? null,
                'oun_code' => $line['oun_code'] ?? null,
                'ccr_costcentre' => $line['ccr_costcentre'] ?? null,
                'cpa_project_no' => $line['cpa_project_no'] ?? null,
                'acm_acct_code' => $line['acm_acct_code'] ?? null,
                'dnd_taxcode' => $line['dnd_taxcode'] ?? null,
                'dnd_invoice_amt' => $line['dnd_invoice_amt'] ?? null,
                'dnd_dnnote_amt' => $line['dnd_dnnote_amt'] ?? null,
                'dnd_dn_taxamt' => $line['dnd_dn_taxamt'] ?? null,
                'dnd_bal_amt' => $line['dnd_bal_amt'] ?? null,
                'dnd_status' => 'Draft',
                'dnd_cust_invoice_detl_id' => $line['dnd_cust_invoice_detl_id'],
                'dnd_transaction_type' => $type,
                'dnd_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'createdby' => $username,
                'createddate' => $nowStr,
            ]);
        }
    }

    private function decodeJson(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function parseLegacyDate(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $raw, $m)) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }
        return $raw;
    }

    private function nextSeq(string $table): int
    {
        $col = match ($table) {
            'debit_note_master' => 'dnm_debit_note_master_id',
            'debit_note_details' => 'dnd_id',
            default => 'id',
        };
        $max = (int) DB::connection('mysql_secondary')->table($table)->max($col);
        return $max + 1;
    }

    private function generateNoteNo(string $prefix): string
    {
        $count = DebitNoteMaster::query()
            ->whereRaw("DATE_FORMAT(createddate, '%Y%m') = ?", [now()->format('Ym')])
            ->count();
        return sprintf('%s-%s-%05d', $prefix, now()->format('Ym'), $count + 1);
    }

    private function currentUsername(): string
    {
        return (string) (Auth::user()->email ?? Auth::user()->name ?? 'system');
    }
}
