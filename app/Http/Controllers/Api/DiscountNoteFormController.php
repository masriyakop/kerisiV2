<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelNoteRequest;
use App\Http\Requests\SaveDiscountNoteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CustInvoiceDetails;
use App\Models\CustInvoiceMaster;
use App\Models\DiscountNoteDetails;
use App\Models\DiscountNoteMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AR > Discount Note Form (MENUID 1784).
 *
 * Source: BL `DT_AR_DISCOUNT_NOTE_FORM`. The legacy page mapped six
 * `?action=` branches (temp, tempCredit, saveDcNote, submitDcNote,
 * detailHead, dropdown) plus `$_GET['canceldc']` / `$_GET['dt_processFlow']`.
 * Same REST split as the Credit / Debit Note forms:
 *
 *   GET  /...?invoice_id=&policy_id=  → invoiceLines  (temp + tempCredit)
 *   GET  /{id}                        → show          (detailHead)
 *   POST /                            → saveDraft     (saveDcNote)
 *   POST /{id}/submit                 → submit        (stub)
 *   POST /{id}/cancel                 → cancel        (stub)
 *   GET  /{id}/process-flow           → processFlow   (stub)
 *
 * Workflow notes:
 *   Legacy `submitDcNote` / `canceldc` call FIMS stored procedures
 *   (`workflowSubmit`, `workflowUpdate`, `discount_note_cancel`) that are
 *   not yet migrated. `submit` / `cancel` below perform only the post-
 *   procedure status flip (Entry / CANCELLED) and `processFlow` returns
 *   an empty list. See CreditNoteFormController docblock for the
 *   architectural rationale — this controller follows the same contract.
 *
 * Discount-specific filter: the invoice-line lookup is narrowed to fee
 * items covered by the selected `dcp_dc_policy_id` via `discount_fee` +
 * `discount_note_policy`, and the discount amount per line is computed
 * as ROUND((dfe_dc_rate/100)*cid_total_amt, 2).
 */
class DiscountNoteFormController extends Controller
{
    use ApiResponse;

    /**
     * GET `/account-receivable/discount-note-form/discount-policies`.
     *
     * Backs the `Discount Policy *` dropdown on the Discount Note Form.
     * Legacy source (BL `DT_AR_DISCOUNT_NOTE_FORM` line ~495):
     *   SELECT dcp_dc_policy_id FLC_ID, dcp_dc_description FLC_NAME
     *   FROM fims_usr.discount_note_policy
     * The page originally narrowed this query to the saved note's policy
     * via a correlated subquery; for the editor we expose the full list
     * so the user can pick any policy at creation time.
     */
    public function discountPolicies(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        // Legacy `DT_AR_DISCOUNT_NOTE_FORM` does NOT apply a status filter to
        // this lookup (see BL line ~495). We mirror that exactly so every
        // configured policy remains selectable — governance of which
        // policies exist is handled in the Discount Policy setup screen.
        $builder = DB::connection('mysql_secondary')
            ->table('discount_note_policy');

        if ($q !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $builder->where(function ($b) use ($needle) {
                $b->whereRaw("LOWER(IFNULL(CAST(dcp_dc_policy_id AS CHAR), '')) LIKE ?", [$needle])
                    ->orWhereRaw("LOWER(IFNULL(dcp_dc_description, '')) LIKE ?", [$needle])
                    ->orWhereRaw("LOWER(IFNULL(dcp_dc_type, '')) LIKE ?", [$needle]);
            });
        }

        $rows = $builder
            ->orderBy('dcp_dc_policy_id')
            ->get(['dcp_dc_policy_id', 'dcp_dc_type', 'dcp_dc_description', 'dcp_dc_rate']);

        return $this->sendOk(
            $rows->map(function ($r) {
                $id = (string) ($r->dcp_dc_policy_id ?? '');
                $desc = (string) ($r->dcp_dc_description ?? '');
                $type = (string) ($r->dcp_dc_type ?? '');
                $rate = (float) ($r->dcp_dc_rate ?? 0);
                $labelParts = array_filter([$type, $desc]);
                return [
                    'value' => $id,
                    'label' => $labelParts ? implode(' — ', $labelParts) : $id,
                    'policyId' => $id,
                    'dcType' => $type,
                    'description' => $desc,
                    'rate' => $rate,
                ];
            })->values()->all()
        );
    }

    public function invoiceLines(Request $request): JsonResponse
    {
        $invoiceId = (string) $request->input('invoice_id', '');
        $policyId = (string) $request->input('policy_id', '');
        if ($invoiceId === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'invoice_id is required');
        }
        if ($policyId === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'policy_id is required');
        }

        // The legacy filter joins cust_invoice_details with discount_fee
        // (to restrict to discountable fee items) + discount_note_policy
        // (for the description + rate). We replicate that via a subquery
        // join using DB::table() so we don't have to introduce two new
        // lookup models just for this reporting query.
        $baseQuery = DB::connection('mysql_secondary')
            ->table('cust_invoice_details as cid')
            ->join('discount_fee as df', 'df.cii_item_code', '=', 'cid.cii_item_code')
            ->join('discount_note_policy as dnp', 'dnp.dcp_dc_policy_id', '=', 'df.dcp_dc_policy_id')
            ->where('cid.cim_cust_invoice_id', $invoiceId)
            ->where('dnp.dcp_dc_policy_id', $policyId);

        $rows = (clone $baseQuery)
            ->where('cid.cid_transaction_type', 'DT')
            ->get([
                'cid.cid_cust_invoice_detl_id',
                'cid.cim_cust_invoice_id',
                'cid.cii_item_category',
                'cid.cii_item_code',
                'cid.fty_fund_type',
                'cid.at_activity_code',
                'cid.oun_code',
                'cid.ccr_costcentre',
                'cid.cpa_project_no',
                'cid.acm_acct_code',
                'cid.cid_taxcode',
                'cid.cid_taxamt',
                'cid.cid_total_amt',
                'cid.cid_crnote_amt',
                'cid.cid_dnnote_amt',
                'cid.cid_nett_amt',
                'cid.cid_bal_amt',
                'cid.cid_transaction_type',
                'dnp.dcp_dc_description',
                'df.dfe_dc_rate',
                DB::raw("cid.cid_extended_field->>'\$.cii_item_category_desc' as cii_item_category_desc"),
                DB::raw("cid.cid_extended_field->>'\$.cii_item_code_desc' as cii_item_code_desc"),
                DB::raw("cid.cid_extended_field->>'\$.fty_fund_type_desc' as fty_fund_type_desc"),
                DB::raw("cid.cid_extended_field->>'\$.at_activity_code_desc' as at_activity_code_desc"),
                DB::raw("cid.cid_extended_field->>'\$.oun_desc' as oun_desc"),
                DB::raw("cid.cid_extended_field->>'\$.ccr_costcentre_charged_desc' as ccr_costcentre_charged_desc"),
                DB::raw("cid.cid_extended_field->>'\$.acm_acct_desc' as acm_acct_desc"),
            ]);

        $mapLine = function ($r) {
            $total = (float) ($r->cid_total_amt ?? 0);
            $tax = (float) ($r->cid_taxamt ?? 0);
            $rate = (float) ($r->dfe_dc_rate ?? 0);
            $dcAmt = $total > 0 ? round(($rate / 100) * $total, 2) : 0.0;
            $dcTax = $total > 0 ? ($tax / $total) * $dcAmt : 0.0;
            $balance = (float) ($r->cid_bal_amt ?? 0) - ($dcAmt + $dcTax);

            return [
                'ID' => (string) $r->cid_cust_invoice_detl_id,
                'invoiceId' => (string) $r->cim_cust_invoice_id,
                'feeCategoryId' => $r->cii_item_category,
                'feeCategory' => $r->cii_item_category_desc,
                'cii_item_code' => $r->cii_item_code,
                'feeItem' => $r->cii_item_code_desc,
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
                'taxAmt' => $tax,
                'amt' => (float) ($r->cid_bal_amt ?? 0),
                'totalAmt' => (float) ($r->cid_nett_amt ?? 0),
                'dcType' => $r->dcp_dc_description,
                'dcRate' => $rate,
                'dcAmt' => $dcAmt,
                'dcTaxAmt' => round($dcTax, 2),
                'balance' => round($balance, 2),
                'transactionType' => $r->cid_transaction_type,
            ];
        };

        $debit = $rows->values()->map($mapLine);
        // The legacy tempCredit query hits the same filter (DT lines) but
        // projects the acctCode from cid.acm_acct_code rather than the
        // discount account. We return the same DT rows for the credit
        // side so the UI can show the mirror distribution, matching the
        // legacy visual.
        $credit = $rows->values()->map($mapLine);

        $totalDc = $debit->sum('dcAmt');
        $invTotal = $rows->sum('cid_bal_amt');

        return $this->sendOk([
            'debit' => $debit,
            'credit' => $credit,
            'invoiceBalance' => (float) $invTotal,
            'dcAmtTotal' => (float) $totalDc,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $master = DiscountNoteMaster::query()
            ->where('dcm_discount_note_master_id', $id)
            ->where('dcm_system_id', 'AR_DC')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Discount note not found');
        }

        $invoiceMeta = null;
        if ($master->cim_invoice_no) {
            $invoiceMeta = CustInvoiceMaster::query()
                ->where('cim_invoice_no', $master->cim_invoice_no)
                ->first(['cim_cust_invoice_id', 'cim_invoice_no', 'cim_cust_id', 'cim_cust_type', 'cim_bal_amt', 'cim_total_amt', 'cim_status']);
        }

        $details = DiscountNoteDetails::query()
            ->where('dcm_discount_note_master_id', $master->dcm_discount_note_master_id)
            ->get();

        $mapDetail = function ($d) {
            $ext = $this->decodeJson($d->dcd_extended_field ?? null);

            return [
                'dcd_id' => (string) $d->dcd_id,
                'dcd_cust_invoice_detl_id' => $d->dcd_cust_invoice_detl_id,
                'ID' => (string) ($d->dcd_cust_invoice_detl_id ?? $d->dcd_id),
                'dcd_item_category' => $d->dcd_item_category,
                'feeCategoryId' => $d->dcd_item_category,
                'feeCategory' => $ext['feeCategory'] ?? $ext['cii_item_category_desc'] ?? null,
                'cii_item_code' => $d->cii_item_code,
                'feeItem' => $ext['feeItem'] ?? $ext['cii_item_code_desc'] ?? null,
                'dcd_detail_desc' => $d->dcd_detail_desc,
                'dcType' => $d->dcd_detail_desc,
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
                'dcd_taxcode' => $d->dcd_taxcode,
                'taxCode' => $d->dcd_taxcode,
                'taxAmt' => (float) ($d->dcd_taxamt ?? 0),
                'dcd_invoice_amt' => (float) ($d->dcd_invoice_amt ?? 0),
                'amt' => (float) ($d->dcd_invoice_amt ?? 0),
                'dcd_dcnote_amt' => (float) ($d->dcd_dcnote_amt ?? 0),
                'dcAmt' => (float) ($d->dcd_dcnote_amt ?? 0),
                'dcTaxAmt' => (float) ($d->dcd_dc_taxamt ?? 0),
                'dcd_bal_amt' => (float) ($d->dcd_bal_amt ?? 0),
                'balance' => (float) ($d->dcd_bal_amt ?? 0),
                'dcd_transaction_type' => $d->dcd_transaction_type,
            ];
        };

        $debit = $details->where('dcd_transaction_type', 'DT')->values()->map($mapDetail);
        $credit = $details->where('dcd_transaction_type', 'CR')->values()->map($mapDetail);

        $ext = $this->decodeJson($master->dcm_extended_field);

        return $this->sendOk([
            'head' => [
                'dcm_discount_note_master_id' => (string) $master->dcm_discount_note_master_id,
                'dcm_dcnote_no' => $master->dcm_dcnote_no,
                'cim_invoice_no' => $master->cim_invoice_no,
                'cim_cust_invoice_id' => $invoiceMeta?->cim_cust_invoice_id ? (string) $invoiceMeta->cim_cust_invoice_id : null,
                'dcm_cust_id' => $master->dcm_cust_id,
                'dcm_cust_type' => $master->dcm_cust_type,
                'dcm_cust_type_desc' => $ext['dcm_cust_type_desc'] ?? null,
                'dcm_cust_name' => $master->dcm_cust_name,
                'dcm_dcnote_desc' => $master->dcm_dcnote_desc,
                'dcm_dcnote_date' => $master->dcm_dcnote_date,
                'dcm_dc_total_amount' => (float) ($master->dcm_dc_total_amount ?? 0),
                'dcm_status_cd' => $master->dcm_status_cd,
                'dcm_status_cd_desc' => $ext['dcm_status_dc_desc'] ?? $master->dcm_status_cd,
                'dcp_dc_policy_id' => $master->dcp_dc_policy_id,
                'invoiceTotalAmount' => $invoiceMeta ? (float) ($invoiceMeta->cim_total_amt ?? 0) : 0,
                'invoiceBalanceAmount' => $invoiceMeta ? (float) ($invoiceMeta->cim_bal_amt ?? 0) : 0,
            ],
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    public function saveDraft(SaveDiscountNoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $head = $data['head'];
        $username = $this->currentUsername();

        $masterId = (string) ($head['dcm_discount_note_master_id'] ?? '');
        $masterCode = (string) ($head['dcm_dcnote_no'] ?? '');
        $isNew = $masterId === '';

        if ($isNew) {
            $masterId = (string) $this->nextSeq('discount_note_master');
            if ($masterCode === '') {
                $masterCode = $this->generateNoteNo('DC');
            }
        }

        $extended = [
            'dcm_cust_type_desc' => (string) ($head['dcm_cust_type_desc'] ?? ''),
            'dcm_status_dc_desc' => 'Draft',
            'cim_invoice_no_desc' => (string) ($head['cim_invoice_no'] ?? ''),
        ];

        $totalAmt = isset($head['dcm_dc_total_amount']) ? (float) $head['dcm_dc_total_amount'] : null;
        $nowStr = now()->format('Y-m-d H:i:s');

        DB::connection('mysql_secondary')->transaction(function () use ($masterId, $masterCode, $head, $extended, $totalAmt, $nowStr, $username, $isNew, $data) {
            $payload = [
                'dcm_discount_note_master_id' => $masterId,
                'dcm_dcnote_no' => $masterCode,
                'cim_invoice_no' => $head['cim_invoice_no'],
                'dcm_cust_id' => $head['dcm_cust_id'],
                'dcm_cust_type' => $head['dcm_cust_type'],
                'dcm_cust_name' => $head['dcm_cust_name'],
                'dcm_dcnote_desc' => $head['dcm_dcnote_desc'] ?? null,
                'dcm_dcnote_date' => $this->parseLegacyDate($head['dcm_dcnote_date'] ?? null),
                'dcm_dc_total_amount' => $totalAmt,
                'dcm_status_cd' => 'Draft',
                'dcm_system_id' => 'AR_DC',
                'dcp_dc_policy_id' => $head['dcp_dc_policy_id'],
                'dcm_extended_field' => json_encode($extended, JSON_UNESCAPED_UNICODE),
            ];

            if ($isNew) {
                DiscountNoteMaster::query()->create(array_merge($payload, [
                    'createdby' => $username,
                    'createddate' => $nowStr,
                ]));
            } else {
                DiscountNoteMaster::query()
                    ->where('dcm_discount_note_master_id', $masterId)
                    ->update(array_merge($payload, [
                        'updatedby' => $username,
                        'updateddate' => $nowStr,
                    ]));
            }

            DiscountNoteDetails::query()
                ->where('dcm_discount_note_master_id', $masterId)
                ->delete();

            $this->persistDetails($masterId, $data['debit'] ?? [], 'DT', $username, $nowStr);
            $this->persistDetails($masterId, $data['credit'] ?? [], 'CR', $username, $nowStr);

            // Legacy: total DC = SUM(dcd_dcnote_amt WHERE type='DT').
            $resolvedTotal = (float) DiscountNoteDetails::query()
                ->where('dcm_discount_note_master_id', $masterId)
                ->where('dcd_transaction_type', 'DT')
                ->sum('dcd_dcnote_amt');
            DiscountNoteMaster::query()
                ->where('dcm_discount_note_master_id', $masterId)
                ->update(['dcm_dc_total_amount' => $resolvedTotal]);
        });

        return $this->sendOk([
            'status' => 'ok',
            'dcID' => (string) $masterId,
            'discountNoteNo' => $masterCode,
            'status_cd' => 'Draft',
        ]);
    }

    public function submit(string $id): JsonResponse
    {
        $master = DiscountNoteMaster::query()
            ->where('dcm_discount_note_master_id', $id)
            ->where('dcm_system_id', 'AR_DC')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Discount note not found');
        }

        $ext = $this->decodeJson($master->dcm_extended_field);
        $ext['dcm_status_dc_desc'] = 'Entry';

        DiscountNoteMaster::query()
            ->where('dcm_discount_note_master_id', $id)
            ->update([
                'dcm_status_cd' => 'Entry',
                'dcm_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'updatedby' => $this->currentUsername(),
                'updateddate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->sendOk([
            'status' => 'ok',
            'status_cd' => 'Entry',
            'workflow_stub' => true,
            'message' => 'Discount note marked as Entry. Workflow routing is not yet migrated; approver chain must be configured in a later release.',
        ]);
    }

    public function cancel(CancelNoteRequest $request, string $id): JsonResponse
    {
        $master = DiscountNoteMaster::query()
            ->where('dcm_discount_note_master_id', $id)
            ->where('dcm_system_id', 'AR_DC')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Discount note not found');
        }

        $ext = $this->decodeJson($master->dcm_extended_field);
        $ext['dcm_status_dc_desc'] = 'Cancelled';
        $ext['dcm_cancel_reason'] = $request->validated()['cancel_reason'];
        $ext['dcm_cancelled_at'] = now()->toAtomString();
        $ext['dcm_cancelled_by'] = $this->currentUsername();

        DiscountNoteMaster::query()
            ->where('dcm_discount_note_master_id', $id)
            ->update([
                'dcm_status_cd' => 'CANCELLED',
                'dcm_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
                'updatedby' => $this->currentUsername(),
                'updateddate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->sendOk([
            'status' => 'ok',
            'status_cd' => 'CANCELLED',
            'message' => 'Discount note cancelled.',
        ]);
    }

    public function processFlow(string $id): JsonResponse
    {
        $master = DiscountNoteMaster::query()
            ->where('dcm_discount_note_master_id', $id)
            ->where('dcm_system_id', 'AR_DC')
            ->first();
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Discount note not found');
        }

        return $this->sendOk([], [
            'workflow_stub' => true,
            'note' => 'Workflow history tables are not yet migrated.',
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     */
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

            DiscountNoteDetails::query()->create([
                'dcd_id' => (string) $this->nextSeq('discount_note_details'),
                'dcm_discount_note_master_id' => $masterId,
                'dcd_line_no' => '1',
                'dcd_invoice_line_no' => '1',
                'dcd_item_category' => $line['dcd_item_category'] ?? null,
                'cii_item_code' => $line['cii_item_code'] ?? null,
                'dcd_detail_desc' => $line['dcd_detail_desc'] ?? null,
                'fty_fund_type' => $line['fty_fund_type'] ?? null,
                'at_activity_code' => $line['at_activity_code'] ?? null,
                'oun_code' => $line['oun_code'] ?? null,
                'ccr_costcentre' => $line['ccr_costcentre'] ?? null,
                'cpa_project_no' => $line['cpa_project_no'] ?? null,
                'acm_acct_code' => $line['acm_acct_code'] ?? null,
                'dcd_taxcode' => $line['dcd_taxcode'] ?? null,
                'dcd_taxamt' => $line['dcd_taxamt'] ?? null,
                'dcd_invoice_amt' => $line['dcd_invoice_amt'] ?? null,
                'dcd_dcnote_amt' => $line['dcd_dcnote_amt'] ?? null,
                'dcd_dc_taxamt' => $line['dcd_dc_taxamt'] ?? null,
                'dcd_bal_amt' => $line['dcd_bal_amt'] ?? null,
                'dcd_status' => 'Draft',
                'dcd_cust_invoice_detl_id' => $line['dcd_cust_invoice_detl_id'],
                'dcd_transaction_type' => $type,
                'dcd_extended_field' => json_encode($ext, JSON_UNESCAPED_UNICODE),
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
            'discount_note_master' => 'dcm_discount_note_master_id',
            'discount_note_details' => 'dcd_id',
            default => 'id',
        };
        $max = (int) DB::connection('mysql_secondary')->table($table)->max($col);
        return $max + 1;
    }

    private function generateNoteNo(string $prefix): string
    {
        $count = DiscountNoteMaster::query()
            ->whereRaw("DATE_FORMAT(createddate, '%Y%m') = ?", [now()->format('Ym')])
            ->count();
        return sprintf('%s-%s-%05d', $prefix, now()->format('Ym'), $count + 1);
    }

    private function currentUsername(): string
    {
        return (string) (Auth::user()->email ?? Auth::user()->name ?? 'system');
    }
}
