<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawInvestmentRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\InvestmentInstitution;
use App\Models\InvestmentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Investment > Investment to be Withdrawn (PAGEID 2895 / MENUID 3485).
 *
 * Source: FIMS BL `API_INV_WITHDRAWN`.
 *
 * Legacy actions:
 *   - action=listing_all_dt   -> {@see index()}
 *   - getDataModal            -> {@see modal()}
 *   - edit_investment         -> {@see withdraw()}
 *
 * Scope: `ipf_status = 'APPROVE'` — only approved investments appear.
 * The UI lets users mark one as withdrawn (flips `ipf_status_withdraw`
 * to 'APPROVE' and tags `ipf_batch_no_wdraw='SYSTEM'`). A "Withdrawn?"
 * column mirrors the legacy `CASE WHEN ipf_status_withdraw='APPROVE'
 * THEN 'WITHDRAWN' ELSE 'RENEW' END` label.
 *
 * Smart filter (6 fields per page JSON): Batch No, Institution,
 * Period From/To (ipf_start_date), Amount (substring match),
 * Status (5 fixed values — preserved as-is from legacy lookup query,
 * though in practice only APPROVE matches the scope).
 *
 * Action column: single "Withdraw" button (legacy Edit icon). It is
 * enabled only when `ipf_status_withdraw <> 'APPROVE'` — i.e. the
 * investment has not yet been marked as withdrawn. Already-withdrawn
 * rows still appear (legacy behaviour) but render the button disabled.
 *
 * RBAC: the legacy code also gates the action on FUG_GROUP_CODE
 * containing INVESTMENT_VERIFIER or INVESTMENT_APPROVER. kerisiV2 has
 * no equivalent group mapping yet, so we keep the endpoint behind
 * `auth:sanctum` only. Adding a dedicated `investment.withdraw`
 * permission is a suggested follow-up once the Investment RBAC slot
 * is introduced project-wide.
 */
class InvestmentToBeWithdrawnController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    private const SORTABLE = [
        'dt_batch',
        'dt_institution',
        'dt_invest_no',
        'dt_journal_no',
        'dt_tenure',
        'dt_amount',
        'dt_rate',
        'dt_status',
        'dt_withdrawn',
    ];

    /** Status dropdown values (from Form_Item_lookup_query). */
    private const STATUSES = [
        'APPROVE', 'PENDING', 'CANCEL', 'WITHDRAW', 'MATURED',
    ];

    /** Matches ListOfInvestmentsController — JOURNAL_INVEST by default. */
    private const MJM_SYSTEM_ID = 'JOURNAL_INVEST';

    public function options(): JsonResponse
    {
        $batchNos = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->select('ipf_batch_no')
            ->where('ipf.ipf_status', 'APPROVE')
            ->whereNotNull('ipf.ipf_batch_no')
            ->where('ipf.ipf_batch_no', '!=', '')
            ->distinct()
            ->orderBy('ipf_batch_no', 'desc')
            ->pluck('ipf_batch_no')
            ->map(fn ($b) => ['id' => (string) $b, 'label' => (string) $b])
            ->values();

        $institutions = InvestmentInstitution::query()
            ->select(['iit_inst_code', 'iit_inst_name', 'iit_bank_branch'])
            ->whereNotNull('iit_inst_code')
            ->where('iit_inst_code', '!=', '')
            ->orderBy('iit_inst_code')
            ->get()
            ->map(fn ($i) => [
                'id' => (string) $i->iit_inst_code,
                'label' => '['.$i->iit_inst_code.'] '
                    .($i->iit_inst_name ?? '')
                    .($i->iit_bank_branch ? ' - '.$i->iit_bank_branch : ''),
            ])
            ->values();

        $status = collect(self::STATUSES)
            ->map(fn ($s) => ['id' => $s, 'label' => $s])
            ->values();

        return $this->sendOk([
            'batchNo' => $batchNos,
            'institution' => $institutions,
            'status' => $status,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dt_batch');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'dt_batch';
        }

        $filterBatch = trim((string) $request->input('filter_batch', ''));
        $filterInstitution = trim((string) $request->input('filter_institution', ''));
        $filterPeriodFrom = trim((string) $request->input('filter_period_from', ''));
        $filterPeriodTo = trim((string) $request->input('filter_period_to', ''));
        $filterAmount = trim((string) $request->input('filter_amount', ''));
        $filterStatus = trim((string) $request->input('filter_status', ''));

        // Joins mirror the legacy `$common` block: institution via
        // INNER (so un-mapped institutions drop out, same as legacy
        // which relied on the join populating labels), journal via
        // LEFT JOIN scoped to system_id='JOURNAL_INVEST'.
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
            ->whereRaw($this->cs('ipf.ipf_status')." = 'APPROVE'");

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $cols = [
                "IFNULL(ipf.ipf_batch_no, '')",
                "IFNULL(iit.iit_inst_name, '')",
                "IFNULL(iit.iit_bank_branch, '')",
                "IFNULL(ipf.ipf_investment_no, '')",
                "IFNULL(ipf.ipf_certifcate_no, '')",
                "IFNULL(ipf.mjm_journal_no, '')",
                "IFNULL(mjm.mjm_status, '')",
            ];
            $params = [];
            $ors = [];
            foreach ($cols as $c) {
                $ors[] = 'LOWER('.$this->cs($c).') LIKE ?';
                $params[] = $like;
            }
            $base->whereRaw('('.implode(' OR ', $ors).')', $params);
        }

        if ($filterBatch !== '') {
            $base->whereRaw($this->cs('ipf.ipf_batch_no').' = ?', [$filterBatch]);
        }
        if ($filterInstitution !== '') {
            $base->whereRaw($this->cs('ipf.iit_inst_code').' = ?', [$filterInstitution]);
        }
        if ($filterPeriodFrom !== '') {
            $base->whereRaw(
                "DATE(ipf.ipf_start_date) >= DATE(STR_TO_DATE(?, '%d/%m/%Y'))",
                [$filterPeriodFrom]
            );
        }
        if ($filterPeriodTo !== '') {
            $base->whereRaw(
                "DATE(ipf.ipf_start_date) <= DATE(STR_TO_DATE(?, '%d/%m/%Y'))",
                [$filterPeriodTo]
            );
        }
        if ($filterAmount !== '') {
            $like = $this->likeEscape(mb_strtolower($filterAmount, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(CAST(ipf.ipf_principal_amt AS CHAR), '')").') LIKE ?',
                [$like]
            );
        }
        if ($filterStatus !== '') {
            $base->whereRaw($this->cs('ipf.ipf_status').' = ?', [$filterStatus]);
        }

        $total = (clone $base)->count();

        $orderColumn = match ($sortBy) {
            'dt_batch' => 'ipf.ipf_batch_no',
            'dt_institution' => 'ipf.iit_inst_code',
            'dt_invest_no' => 'ipf.ipf_investment_no',
            'dt_journal_no' => 'ipf.mjm_journal_no',
            'dt_tenure' => 'ipf.ipf_estimated_period',
            'dt_amount' => 'ipf.ipf_principal_amt',
            'dt_rate' => 'ipf.ipf_rate',
            'dt_status' => 'ipf.ipf_status',
            'dt_withdrawn' => 'ipf.ipf_status_withdraw',
            default => 'ipf.ipf_batch_no',
        };

        $receiptSub = '(SELECT GROUP_CONCAT(
                DISTINCT CONCAT_WS("__",
                    IFNULL(rma.rma_receipt_no, ""),
                    IFNULL(FORMAT(rde_sum.total, 2), ""),
                    IFNULL(DATE_FORMAT(rma.rma_approve_date, "%d/%m/%Y"), "")
                )
                SEPARATOR "|"
            )
            FROM (
                SELECT rde.rma_receipt_master_id, rde.rde_source_ref_no,
                       SUM(rde.rde_total_amt) AS total
                FROM receipt_details rde
                WHERE rde.rde_transaction_type = '."'CR'".'
                GROUP BY rde.rma_receipt_master_id, rde.rde_source_ref_no
            ) rde_sum
            JOIN receipt_master rma
                ON rma.rma_receipt_master_id = rde_sum.rma_receipt_master_id
                AND rma.rma_status = '."'APPROVE'".'
                AND rma.pmt_posting_no IS NOT NULL
            WHERE '.$this->cs('rde_sum.rde_source_ref_no').' = '.$this->cs('ipf.ipf_investment_no').') as receipt_info';

        $withdrawnExpr = "CASE
                WHEN ipf.ipf_status_withdraw = 'APPROVE' THEN 'WITHDRAWN'
                ELSE 'RENEW'
            END as withdrawn_label";

        $rows = (clone $base)
            ->select([
                'ipf.ipf_investment_id',
                'ipf.ipf_batch_no',
                'ipf.iit_inst_code',
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
                'ipf.ipf_status_withdraw',
                DB::raw($withdrawnExpr),
                DB::raw($receiptSub),
            ])
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('ipf.ipf_investment_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            $receipts = [];
            if (! empty($r->receipt_info)) {
                foreach (explode('|', (string) $r->receipt_info) as $item) {
                    [$no, $amount, $date] = array_pad(explode('__', $item, 3), 3, '');
                    if ($no === '' && $amount === '' && $date === '') {
                        continue;
                    }
                    $receipts[] = [
                        'receiptNo' => $no,
                        'amount' => $amount,
                        'date' => $date,
                    ];
                }
            }

            $canWithdraw = ($r->ipf_status_withdraw ?? null) !== 'APPROVE';

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'investmentId' => (int) $r->ipf_investment_id,
                'batchNo' => $r->ipf_batch_no,
                'institutionCode' => $r->iit_inst_code,
                'institutionName' => $r->iit_inst_name,
                'institutionBranch' => $r->iit_bank_branch,
                'investmentNo' => $r->ipf_investment_no,
                'certificateNo' => $r->ipf_certifcate_no,
                'journalNo' => $r->journal_no,
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
                'withdrawnLabel' => $r->withdrawn_label,
                'canWithdraw' => $canWithdraw,
                'receipts' => $receipts,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    /**
     * Legacy `getDataModal` — populates the Withdraw confirmation
     * modal with a read-only snapshot of the target investment.
     */
    public function modal(int $id): JsonResponse
    {
        $row = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->select([
                'ipf.ipf_investment_id',
                'ipf.ipf_investment_no',
                'ipf.ipf_certifcate_no',
                DB::raw("DATE_FORMAT(ipf.ipf_start_date, '%d/%m/%Y') as start_date_fmt"),
                DB::raw("DATE_FORMAT(ipf.ipf_end_date, '%d/%m/%Y') as end_date_fmt"),
                'ipf.ipf_status_withdraw',
            ])
            ->where('ipf.ipf_investment_id', $id)
            ->first();

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Investment not found');
        }

        $tenure = trim(
            (string) ($row->start_date_fmt ?? '')
            .($row->end_date_fmt ? ' - '.$row->end_date_fmt : '')
        );

        return $this->sendOk([
            'investmentId' => (int) $row->ipf_investment_id,
            'investmentNo' => $row->ipf_investment_no,
            'certificateNo' => $row->ipf_certifcate_no,
            'tenure' => $tenure !== '' ? $tenure : null,
            'alreadyWithdrawn' => ($row->ipf_status_withdraw ?? null) === 'APPROVE',
        ]);
    }

    /**
     * Legacy `edit_investment` — flips the investment to withdrawn.
     * The legacy UPDATE targets `ipf_investment_no`, but looking up by
     * `ipf_investment_id` (our route param) is safer: it avoids
     * accidentally bulk-updating if an investment_no was ever reused.
     */
    public function withdraw(WithdrawInvestmentRequest $request, int $id): JsonResponse
    {
        $profile = InvestmentProfile::query()
            ->where('ipf_investment_id', $id)
            ->first();

        if (! $profile) {
            return $this->sendError(404, 'NOT_FOUND', 'Investment not found');
        }

        if ($profile->ipf_status !== 'APPROVE') {
            return $this->sendError(
                422,
                'VALIDATION_ERROR',
                'Only approved investments can be withdrawn',
                ['ipf_status' => ['Investment is not in APPROVE status']],
            );
        }

        if ($profile->ipf_status_withdraw === 'APPROVE') {
            return $this->sendError(
                422,
                'VALIDATION_ERROR',
                'Investment is already marked as withdrawn',
                ['ipf_status_withdraw' => ['Already withdrawn']],
            );
        }

        $profile->fill([
            'ipf_status_withdraw' => 'APPROVE',
            'ipf_batch_no_wdraw' => 'SYSTEM',
        ])->save();

        return $this->sendOk(['success' => true]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
