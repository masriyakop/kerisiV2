<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CashbookDetailsRecon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Cashbook > List Of Cashbook (DAILY|MONTHLY)" listing endpoints
 * (PAGEID 1397 / MENUID 1702 — Daily, PAGEID 2024 / MENUID 2471 — Monthly).
 *
 * Source: FIMS BL `NF_BL_CC_CASHBOOK`. Backed by `cashbook_details_recon`
 * left-joined with `account_main` on `mysql_secondary`. Read-only — the
 * legacy UI does not expose any mutating actions for this listing.
 *
 * The same controller serves both Daily and Monthly views; the two are
 * differentiated by the required `type` query parameter (DAILY|MONTHLY)
 * which maps to `cashbook_details_recon.cbk_type`.
 */
class CashbookListController extends Controller
{
    use ApiResponse;

    public function index(Request $request, string $type): JsonResponse
    {
        $type = strtoupper($type);
        if (! in_array($type, ['DAILY', 'MONTHLY'], true)) {
            return $this->sendError(400, 'BAD_REQUEST', 'type must be DAILY or MONTHLY');
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'cbk_ref_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSort = [
            'cbk_ref_id', 'cbk_trans_period', 'cbk_trans_date', 'cbk_debit_amt',
            'cbk_credit_amt', 'cbk_recon_status', 'cbk_recon_flag',
            'acm_acct_code_bank',
        ];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'cbk_ref_id';
        }

        $base = CashbookDetailsRecon::query()
            ->from('cashbook_details_recon as CR')
            ->leftJoin('account_main as AM', 'AM.acm_acct_code', '=', 'CR.acm_acct_code_bank')
            ->where('CR.cbk_type', $type);

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $base->where(function ($builder) use ($like) {
                foreach ([
                    'CR.cbk_ref_id', 'CR.acm_acct_code_bank', 'AM.acm_acct_desc',
                    'CR.cbk_trans_period', 'CR.cbk_trans_ref',
                    'CR.cbk_recon_status', 'CR.cbk_subsystem_id', 'CR.cbk_payto_name',
                ] as $col) {
                    $builder->orWhereRaw('LOWER(IFNULL(' . $col . ', "")) LIKE ?', [$like]);
                }
            });
        }

        // Smart filters
        if ($request->filled('acm_acct_code_bank')) {
            $base->where('CR.acm_acct_code_bank', $request->input('acm_acct_code_bank'));
        }
        if ($request->filled('cbk_trans_date')) {
            $base->whereRaw("DATE_FORMAT(CR.cbk_trans_date, '%d/%m/%Y') = ?", [$request->input('cbk_trans_date')]);
        }
        if ($request->filled('cbk_recon_status')) {
            $base->where('CR.cbk_recon_status', $request->input('cbk_recon_status'));
        }
        if ($request->filled('cbk_recon_flag')) {
            $base->where('CR.cbk_recon_flag', $request->input('cbk_recon_flag'));
        }
        if ($request->filled('cbk_ref_id')) {
            $base->where('CR.cbk_ref_id', $request->input('cbk_ref_id'));
        }
        if ($request->filled('cbk_trans_period')) {
            $base->where('CR.cbk_trans_period', $request->input('cbk_trans_period'));
        }

        // Aggregate totals on the filtered set (records, debit, credit).
        $totals = (clone $base)
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(CR.cbk_debit_amt), 0) as da, COALESCE(SUM(CR.cbk_credit_amt), 0) as ca')
            ->first();

        $total = (int) ($totals->c ?? 0);
        $totalDebit = (float) ($totals->da ?? 0);
        $totalCredit = (float) ($totals->ca ?? 0);

        $rows = (clone $base)
            ->select([
                'CR.cbk_ref_id',
                'CR.acm_acct_code_bank',
                'AM.acm_acct_desc',
                'CR.cbk_trans_period',
                'CR.cbk_trans_ref',
                'CR.cbk_trans_date',
                'CR.cbk_debit_amt',
                'CR.cbk_credit_amt',
                'CR.cbk_payto_id',
                'CR.cbk_payto_name',
                'CR.cbk_recon_status',
                'CR.cbk_recon_flag',
                'CR.cbk_subsystem_id',
                'CR.cbk_type',
            ])
            ->orderBy('CR.' . $sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->values()
            ->map(fn ($r, int $i) => [
                'index' => (($page - 1) * $limit) + $i + 1,
                'cbk_ref_id' => $r->cbk_ref_id,
                'acm_acct_code_bank' => trim(implode(' - ', array_filter([$r->acm_acct_code_bank, $r->acm_acct_desc]))),
                'cbk_trans_period' => $r->cbk_trans_period,
                'cbk_trans_ref' => $r->cbk_trans_ref,
                'cbk_trans_date' => $r->cbk_trans_date ? \Illuminate\Support\Carbon::parse($r->cbk_trans_date)->format('d/m/Y') : null,
                'cbk_debit_amt' => (float) $r->cbk_debit_amt,
                'cbk_credit_amt' => (float) $r->cbk_credit_amt,
                'cbk_payto_name' => trim(implode(' - ', array_filter([$r->cbk_payto_name, $r->cbk_payto_id]))),
                'cbk_recon_status' => $r->cbk_recon_status === 'M' ? 'MATCHED' : 'UNMATCHED',
                'cbk_recon_flag' => match (trim((string) $r->cbk_recon_flag)) {
                    'A' => 'AUTOMATIC',
                    'M' => 'MANUAL',
                    default => '',
                },
                'cbk_subsystem_id' => $r->cbk_subsystem_id,
                'cbk_type' => $r->cbk_type,
            ]);

        return $this->sendOk($rows, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => [
                'cbk_debit_amt' => $totalDebit,
                'cbk_credit_amt' => $totalCredit,
            ],
        ]);
    }

    public function options(Request $request, string $type): JsonResponse
    {
        $type = strtoupper($type);
        if (! in_array($type, ['DAILY', 'MONTHLY'], true)) {
            return $this->sendError(400, 'BAD_REQUEST', 'type must be DAILY or MONTHLY');
        }

        $accountCode = CashbookDetailsRecon::query()
            ->from('cashbook_details_recon as CR')
            ->leftJoin('account_main as AM', 'AM.acm_acct_code', '=', 'CR.acm_acct_code_bank')
            ->where('CR.cbk_type', $type)
            ->whereNotNull('CR.acm_acct_code_bank')
            ->select('CR.acm_acct_code_bank', 'AM.acm_acct_desc')
            ->groupBy('CR.acm_acct_code_bank', 'AM.acm_acct_desc')
            ->orderBy('CR.acm_acct_code_bank')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->acm_acct_code_bank,
                'label' => trim(($r->acm_acct_code_bank ?? '') . ' - ' . ($r->acm_acct_desc ?? '')),
            ])
            ->values();

        $period = CashbookDetailsRecon::query()
            ->where('cbk_type', $type)
            ->whereNotNull('cbk_trans_period')
            ->select('cbk_trans_period')
            ->distinct()
            ->orderBy('cbk_trans_period', 'desc')
            ->pluck('cbk_trans_period')
            ->map(fn ($p) => ['id' => (string) $p, 'label' => (string) $p])
            ->values();

        $cashType = CashbookDetailsRecon::query()
            ->whereNotNull('cbk_type')
            ->select('cbk_type')
            ->distinct()
            ->orderBy('cbk_type')
            ->pluck('cbk_type')
            ->map(fn ($t) => ['id' => $t, 'label' => $t])
            ->values();

        return $this->sendOk([
            'smartFilter' => [
                'accountCode' => $accountCode,
                'period' => $period,
                'reconStatus' => [
                    ['id' => 'U', 'label' => 'UNMATCHED'],
                    ['id' => 'M', 'label' => 'MATCHED'],
                ],
                'reconFlag' => [
                    ['id' => 'A', 'label' => 'AUTOMATIC'],
                    ['id' => 'M', 'label' => 'MANUAL'],
                ],
                'type' => $cashType,
            ],
        ]);
    }
}
