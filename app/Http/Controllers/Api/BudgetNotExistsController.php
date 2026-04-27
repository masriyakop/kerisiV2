<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\BudgetTransaction;
use App\Models\PostingDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Budget Not Exists (PAGEID 2200 / MENUID 2657).
 *
 * Migrated from legacy BL `NAD_API_SM_REPORT_BUDGET_NOT_EXIST`. Read-only
 * report listing approved posting-detail rows whose `pde_document_no` has
 * not yet been registered in `budget_transaction.bgt_ref` and whose
 * account either has the budget flag (`account_main.acm_flag_budget='Y'`)
 * or whose code starts with `A01`.
 *
 * The endpoint serves the datatable. CSV export is performed client-side
 * from the datatable rows (kitchen-sink default — no server-side CSV
 * generator is provided here, since the FIMS legacy `generateCSV` helper is
 * not part of this stack).
 */
class BudgetNotExistsController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 5));
        $q = (string) ($request->input('q') ?? '');
        $sortBy = $request->input('sort_by', 'pde_trans_date');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc'));

        $allowedSort = [
            'pmt_posting_id', 'fty_fund_type', 'at_activity_code',
            'oun_code', 'ccr_costcentre', 'acm_acct_code',
            'cpa_project_no', 'pde_document_no', 'pde_reference',
            'pde_reference1', 'pde_trans_type', 'pde_trans_amt',
            'pde_trans_date', 'pde_payto_type', 'pde_payto_id',
            'pde_payto_name', 'pde_status', 'pde_doc_description',
        ];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'pde_trans_date';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $registeredRefsQuery = BudgetTransaction::query()
            ->whereNotNull('bgt_ref')
            ->select('bgt_ref');

        $query = PostingDetail::query()
            ->from('posting_details as pd')
            ->join('posting_master as pm', 'pm.pmt_posting_id', '=', 'pd.pmt_posting_id')
            ->join('account_main as am', 'am.acm_acct_code', '=', 'pd.acm_acct_code')
            ->where('pm.pmt_status', 'APPROVE')
            ->whereNotIn('pd.pde_document_no', $registeredRefsQuery)
            ->where(function ($builder) {
                $builder->where('am.acm_flag_budget', 'Y')
                    ->orWhereRaw('SUBSTR(pd.acm_acct_code, 1, 3) = ?', ['A01']);
            });

        if ($q !== '') {
            $needle = mb_strtolower(trim($q), 'UTF-8');
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->whereRaw('LOWER(IFNULL(pd.fty_fund_type, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.at_activity_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.oun_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.ccr_costcentre, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.acm_acct_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.cpa_project_no, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.pde_document_no, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.pde_reference, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.pde_reference1, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.pde_trans_type, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.pde_payto_type, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.pde_payto_id, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.pde_payto_name, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.pde_status, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pd.pde_doc_description, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pm.pmt_system_id, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pm.pmt_posting_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(pm.pmt_status, "")) LIKE ?', [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy("pd.{$sortBy}", $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get([
                'pm.pmt_posting_id',
                'pd.fty_fund_type',
                'pd.at_activity_code',
                'pd.oun_code',
                'pd.ccr_costcentre',
                'pd.acm_acct_code',
                'pd.cpa_project_no',
                'pd.pde_document_no',
                'pd.pde_reference',
                'pd.pde_reference1',
                'pd.pde_trans_type',
                'pd.pde_trans_amt',
                'pd.pde_trans_date',
                'pd.pde_payto_type',
                'pd.pde_payto_id',
                'pd.pde_payto_name',
                'pd.pde_status',
                'pd.pde_doc_description',
                'pm.pmt_system_id',
                'pm.pmt_posting_desc',
                'pm.pmt_status',
            ]);

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            $transDate = $row->pde_trans_date;
            if ($transDate && ! ($transDate instanceof \DateTimeInterface)) {
                try {
                    $transDate = new \DateTime((string) $transDate);
                } catch (\Throwable) {
                    $transDate = null;
                }
            }

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pmt_posting_id' => (int) $row->pmt_posting_id,
                'fty_fund_type' => $row->fty_fund_type,
                'at_activity_code' => $row->at_activity_code,
                'oun_code' => $row->oun_code,
                'ccr_costcentre' => $row->ccr_costcentre,
                'acm_acct_code' => $row->acm_acct_code,
                'cpa_project_no' => $row->cpa_project_no,
                'pde_document_no' => $row->pde_document_no,
                'pde_reference' => $row->pde_reference,
                'pde_reference1' => $row->pde_reference1,
                'pde_trans_type' => $row->pde_trans_type,
                'pde_trans_amt' => $row->pde_trans_amt !== null ? (float) $row->pde_trans_amt : null,
                'pde_trans_date' => $transDate ? $transDate->format('Y-m-d') : null,
                'pde_payto_type' => $row->pde_payto_type,
                'pde_payto_id' => $row->pde_payto_id,
                'pde_payto_name' => $row->pde_payto_name,
                'pde_status' => $row->pde_status,
                'pde_doc_description' => $row->pde_doc_description,
                'pmt_system_id' => $row->pmt_system_id,
                'pmt_posting_desc' => $row->pmt_posting_desc,
                'pmt_status' => $row->pmt_status,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }
}
