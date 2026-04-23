<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\InvestmentInstitution;
use App\Models\InvestmentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Investment > List Of Accrual (PAGEID 1548 / MENUID 1877).
 *
 * Source: FIMS BL `API_LIST_OF_ACCRUAL` (action=listing_all_dt).
 * Read-only datatable that joins `investment_profile`,
 * `investment_institution`, and `investment_accrual` (all
 * DB_SECOND_DATABASE / mysql_secondary). Scope matches legacy SQL:
 *   - `ipf_status != 'DRAFT'`
 *   - EXISTS an `investment_accrual` row for the investment number
 *
 * Smart filter mirrors the page JSON (Batch No / Institution /
 * Period / Tenure / Amount / Status) with the same substring /
 * exact semantics used by setSmartFilter() in the legacy BL.
 *
 * Action column deep-links to MENUID 1878 (Accrual detail) in the
 * legacy system. That detail page is NOT migrated yet; the Vue view
 * renders the Action as a disabled button until it lands.
 */
class ListOfAccrualController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    /** Allowed sort keys, mapped to concrete SQL expressions in index(). */
    private const SORTABLE = [
        'dt_batch',
        'dt_institution',
        'dt_invest_no',
        'dt_tenure',
        'dt_amount',
        'dt_rate',
        'dt_total_sum',
        'dt_status',
    ];

    /** Legacy status values exposed in the Smart Filter dropdown. */
    private const STATUSES = ['APPROVE', 'PENDING', 'CANCEL'];

    public function options(): JsonResponse
    {
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
        $filterPeriod = trim((string) $request->input('filter_period', ''));
        $filterTenure = trim((string) $request->input('filter_tenure', ''));
        $filterAmount = trim((string) $request->input('filter_amount', ''));
        $filterStatus = trim((string) $request->input('filter_status', ''));

        // Collation-safe cross-table comparisons: some FIMS tables are
        // utf8mb3, others utf8mb4_0900_ai_ci. CollationSafeSql::cs()
        // wraps every text expression with
        // `CONVERT(... USING utf8mb4) COLLATE utf8mb4_unicode_ci` so
        // every `=` / `LIKE` / `CONCAT_WS` works regardless of the
        // underlying charset.
        $base = InvestmentProfile::query()
            ->from('investment_profile as ipf')
            ->join('investment_institution as iit', function ($join) {
                $join->on(
                    DB::raw($this->cs('ipf.iit_inst_code')),
                    '=',
                    DB::raw($this->cs('iit.iit_inst_code')),
                );
            })
            ->whereRaw($this->cs('ipf.ipf_status')." <> 'DRAFT'")
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('investment_accrual as iac')
                    ->whereRaw(
                        $this->cs('iac.ipf_investment_no').' = '.$this->cs('ipf.ipf_investment_no')
                    );
            });

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $concatParts = [
                $this->cs("IFNULL(ipf.ipf_batch_no, '')"),
                $this->cs("IFNULL(ipf.iit_inst_code, '')"),
                $this->cs("IFNULL(ipf.ipf_extended_field->>'$.iit_inst_desc', '')"),
                $this->cs("IFNULL(iit.iit_bank_branch, '')"),
                $this->cs("IFNULL(ipf.ipf_investment_no, '')"),
                $this->cs("IFNULL(ipf.ipf_certifcate_no, '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_estimated_period AS CHAR), '')"),
                $this->cs("IFNULL(ipf.ipf_extended_field->>'$.ipf_tenure_desc', '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_start_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_end_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_principal_amt AS CHAR), '')"),
                $this->cs("IFNULL(CAST(ipf.ipf_rate AS CHAR), '')"),
                $this->cs("IFNULL(ipf.ipf_receipt_withdraw, '')"),
                $this->cs("IFNULL(DATE_FORMAT(ipf.ipf_receipt_date_withdraw, '%d/%m/%Y'), '')"),
            ];
            $concat = "CONCAT_WS('__', ".implode(', ', $concatParts).')';
            $base->whereRaw("LOWER($concat) LIKE ?", [$like]);
        }

        // Smart filter parity with the legacy setSmartFilter() keys.
        if ($filterBatch !== '') {
            $like = $this->likeEscape(mb_strtolower($filterBatch, 'UTF-8'));
            $base->whereRaw('LOWER('.$this->cs("IFNULL(ipf.ipf_batch_no, '')").') LIKE ?', [$like]);
        }
        if ($filterInstitution !== '') {
            $like = $this->likeEscape(mb_strtolower($filterInstitution, 'UTF-8'));
            $concat = "CONCAT('[', "
                .$this->cs("IFNULL(ipf.iit_inst_code, '')")
                .", '] ', "
                .$this->cs("IFNULL(ipf.ipf_extended_field->>'$.iit_inst_desc', '')")
                .')';
            $base->whereRaw("LOWER($concat) LIKE ?", [$like]);
        }
        if ($filterPeriod !== '') {
            $like = $this->likeEscape(mb_strtolower($filterPeriod, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(CAST(ipf.ipf_estimated_period AS CHAR), '')").') LIKE ?',
                [$like]
            );
        }
        if ($filterTenure !== '') {
            $like = $this->likeEscape(mb_strtolower($filterTenure, 'UTF-8'));
            $base->whereRaw(
                'LOWER('.$this->cs("IFNULL(ipf.ipf_extended_field->>'$.ipf_tenure_desc', '')").') LIKE ?',
                [$like]
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

        // Legacy SQL does `GROUP BY ipf.ipf_investment_id` (primary
        // key) simply to dedupe the many-to-one join with
        // investment_accrual. We use whereExists instead so no GROUP
        // BY is needed and counting is straightforward.
        $total = (clone $base)->count();

        $orderColumn = match ($sortBy) {
            'dt_batch' => 'ipf.ipf_batch_no',
            'dt_institution' => 'ipf.iit_inst_code',
            'dt_invest_no' => 'ipf.ipf_investment_no',
            'dt_tenure' => 'ipf.ipf_estimated_period',
            'dt_amount' => 'ipf.ipf_principal_amt',
            'dt_rate' => 'ipf.ipf_rate',
            'dt_total_sum' => DB::raw('total_sum'),
            'dt_status' => 'ipf.ipf_status',
            default => 'ipf.ipf_batch_no',
        };

        $totalSumExpr = '(SELECT COALESCE(SUM(iac.iac_amount), 0)
            FROM investment_accrual iac
            WHERE '.$this->cs('iac.ipf_investment_no').' = '.$this->cs('ipf.ipf_investment_no').'
              AND iac.pmt_posting_no IS NOT NULL) as total_sum';

        $rows = (clone $base)
            ->select([
                'ipf.ipf_investment_id',
                'ipf.ipf_batch_no',
                'ipf.iit_inst_code',
                DB::raw("ipf.ipf_extended_field->>'\$.iit_inst_desc' as inst_desc"),
                'iit.iit_bank_branch',
                'ipf.ipf_investment_no',
                'ipf.ipf_certifcate_no',
                'ipf.ipf_estimated_period',
                DB::raw("ipf.ipf_extended_field->>'\$.ipf_tenure_desc' as tenure_desc"),
                DB::raw("DATE_FORMAT(ipf.ipf_start_date, '%d/%m/%Y') as start_date_fmt"),
                DB::raw("DATE_FORMAT(ipf.ipf_end_date, '%d/%m/%Y') as end_date_fmt"),
                'ipf.ipf_principal_amt',
                'ipf.ipf_rate',
                'ipf.ipf_status',
                DB::raw($totalSumExpr),
            ])
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('ipf.ipf_investment_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'investmentId' => (int) $r->ipf_investment_id,
            'batchNo' => $r->ipf_batch_no,
            'institutionCode' => $r->iit_inst_code,
            'institutionDesc' => $r->inst_desc,
            'institutionBranch' => $r->iit_bank_branch,
            'investmentNo' => $r->ipf_investment_no,
            'certificateNo' => $r->ipf_certifcate_no,
            'period' => $r->ipf_estimated_period !== null ? (int) $r->ipf_estimated_period : null,
            'tenureDesc' => $r->tenure_desc,
            'startDate' => $r->start_date_fmt,
            'endDate' => $r->end_date_fmt,
            'principalAmount' => $r->ipf_principal_amt !== null
                ? (float) $r->ipf_principal_amt
                : null,
            'rate' => $r->ipf_rate !== null ? (float) $r->ipf_rate : null,
            'totalSum' => (float) $r->total_sum,
            'status' => $r->ipf_status,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
