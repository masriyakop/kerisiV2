<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateScheduleRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CollationSafeSql;
use App\Models\InvestmentProfile;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Investment > Generate Schedule (PAGEID 1206 / MENUID 1475).
 *
 * Source: FIMS BL `API_INVESTMENT_GENERATE_ACCRUAL`.
 *
 * Read-only datatable joining `investment_profile` with
 * `investment_type` on `mysql_secondary`. Scope matches the legacy
 * `$common` block:
 *   - `ipf_status IN ('APPROVE','MATURED')`
 *   - `NOT EXISTS (investment_accrual WHERE ipf_investment_no =
 *      ip.ipf_investment_no)` — only investments without any accrual
 *      schedule yet.
 *
 * Global search mirrors the legacy CONCAT_WS needle over:
 *   ipf_investment_no, ivt_description, ipf_principal_amt (formatted),
 *   ipf_rate, ipf_start_date (d/m/Y), ipf_end_date (d/m/Y).
 *
 * Smart filter: NONE. The page JSON declares `dt_filter="default"`
 * (no smart filter modal) and there are no `form (Smart Filter)` rows
 * attached to the datatable. The legacy BL contains a commented /
 * partially-wired `smartFilter` block but the one active field
 * (`ipf_investment_no`) is effectively dead UI on the legacy page, so
 * we skip it here.
 *
 * Meta mirrors the legacy footer `$grandTotal = sum(ipf_principal_amt)`.
 *
 * Action column: the header "Generate Schedule" button fans the
 * selected investment numbers out to the stored procedure
 * `investment_accrual(?)` on `mysql_secondary`. This mirrors the
 * legacy BL `INSERT_UPDATE_INVESTMENT_ACCRUAL` with
 * `mode=generateScheduleAccrual` — per-row `CALL investment_accrual(X)`
 * is all the legacy did. See {@see generate()}.
 */
class InvestmentGenerateScheduleController extends Controller
{
    use ApiResponse;
    use CollationSafeSql;

    public function __construct(
        protected AuditService $auditService,
    ) {}

    /** Allowed sort keys, mapped to concrete SQL expressions in index(). */
    private const SORTABLE = [
        'dt_invest_no',
        'dt_invest_type',
        'dt_rate',
        'dt_amount',
        'dt_start_date',
        'dt_end_date',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'dt_invest_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'dt_invest_no';
        }

        $base = InvestmentProfile::query()
            ->from('investment_profile as ip')
            ->join('investment_type as it', function ($join) {
                $join->on(
                    DB::raw($this->cs('it.ivt_type_code')),
                    '=',
                    DB::raw($this->cs('ip.ivt_type_code')),
                );
            })
            ->whereIn(DB::raw($this->cs('ip.ipf_status')), ['APPROVE', 'MATURED'])
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('investment_accrual as ia')
                    ->whereRaw(
                        $this->cs('ia.ipf_investment_no').' = '.$this->cs('ip.ipf_investment_no')
                    );
            });

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $concatParts = [
                $this->cs("IFNULL(ip.ipf_investment_no, '')"),
                $this->cs("IFNULL(it.ivt_description, '')"),
                $this->cs("IFNULL(FORMAT(ip.ipf_principal_amt, 2), '')"),
                $this->cs("IFNULL(CAST(ip.ipf_rate AS CHAR), '')"),
                $this->cs("IFNULL(DATE_FORMAT(ip.ipf_start_date, '%d/%m/%Y'), '')"),
                $this->cs("IFNULL(DATE_FORMAT(ip.ipf_end_date, '%d/%m/%Y'), '')"),
            ];
            $concat = "CONCAT_WS('__', ".implode(', ', $concatParts).')';
            $base->whereRaw("LOWER($concat) LIKE ?", [$like]);
        }

        // Footer aggregate mirrors legacy `$grandTotal = sum(ipf_principal_amt)`.
        $aggregates = (clone $base)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(ip.ipf_principal_amt), 0) as grand_total')
            ->first();
        $total = $aggregates ? (int) $aggregates->total_count : 0;
        $grandTotal = $aggregates ? (float) $aggregates->grand_total : 0.0;

        $orderColumn = match ($sortBy) {
            'dt_invest_no' => 'ip.ipf_investment_no',
            'dt_invest_type' => 'it.ivt_description',
            'dt_rate' => 'ip.ipf_rate',
            'dt_amount' => 'ip.ipf_principal_amt',
            'dt_start_date' => 'ip.ipf_start_date',
            'dt_end_date' => 'ip.ipf_end_date',
            default => 'ip.ipf_investment_no',
        };

        $rows = (clone $base)
            ->select([
                'ip.ipf_investment_id',
                'ip.ipf_investment_no',
                'it.ivt_description',
                'ip.ipf_rate',
                'ip.ipf_principal_amt',
                DB::raw("DATE_FORMAT(ip.ipf_start_date, '%d/%m/%Y') as start_date_fmt"),
                DB::raw("DATE_FORMAT(ip.ipf_end_date, '%d/%m/%Y') as end_date_fmt"),
            ])
            // Legacy hard-codes `ORDER BY ip.ipf_investment_no` before
            // the dynamic orderBy. We honour that by appending the
            // investment_no as the final tiebreaker instead.
            ->orderBy($orderColumn, $sortDir)
            ->orderBy('ip.ipf_investment_no', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'investmentId' => $r->ipf_investment_id !== null ? (int) $r->ipf_investment_id : null,
            'investmentNo' => $r->ipf_investment_no,
            'investmentType' => $r->ivt_description,
            'rate' => $r->ipf_rate !== null ? (float) $r->ipf_rate : null,
            'principalAmount' => $r->ipf_principal_amt !== null
                ? (float) $r->ipf_principal_amt
                : null,
            'startDate' => $r->start_date_fmt,
            'endDate' => $r->end_date_fmt,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'grandTotalPrincipal' => $grandTotal,
        ]);
    }

    /**
     * Generate accrual schedules for one or more investments.
     *
     * Mirrors legacy `INSERT_UPDATE_INVESTMENT_ACCRUAL` (mode
     * `generateScheduleAccrual`):
     *
     *     foreach ($_POST['ipf_investment'] as $no => $_) {
     *         CALL investment_accrual($no);
     *     }
     *
     * The stored procedure `investment_accrual(?)` lives on the
     * legacy database (DB_SECOND_DATABASE / `mysql_secondary`) and
     * populates `investment_accrual` rows for the given investment.
     * We do NOT attempt to port the procedure body — that logic is
     * owned by the legacy DBA team.
     *
     * Defensive re-validation per number (not in the legacy code but
     * added here to avoid silent no-ops / partial failures when the
     * UI is stale):
     *   - Investment exists with status IN ('APPROVE','MATURED').
     *   - No existing `investment_accrual` rows for the same
     *     `ipf_investment_no` (page scope — the list is built from
     *     NOT EXISTS on that table).
     *
     * Each number is processed independently and caught per-iteration
     * so a single stored-procedure error does not abort the batch.
     * Response enumerates both successes and failures.
     *
     * Audit: one entry per successful generate, action
     * `investment.schedule.generated`, keyed to the investment
     * profile record so downstream reporting can link back.
     */
    public function generate(GenerateScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $numbers = array_values(array_unique(array_map('trim', $validated['investment_numbers'])));
        $numbers = array_values(array_filter($numbers, fn ($n) => $n !== ''));

        if (empty($numbers)) {
            return $this->sendError(
                422,
                'VALIDATION_ERROR',
                'No investment numbers provided.',
                ['investment_numbers' => ['At least one investment must be selected.']],
            );
        }

        $processed = [];
        $failed = [];

        foreach ($numbers as $investmentNo) {
            $profile = InvestmentProfile::query()
                ->from('investment_profile as ip')
                ->whereRaw($this->cs('ip.ipf_investment_no').' = ?', [$investmentNo])
                ->whereIn(DB::raw($this->cs('ip.ipf_status')), ['APPROVE', 'MATURED'])
                ->first();

            if (! $profile) {
                $failed[] = [
                    'investmentNo' => $investmentNo,
                    'reason' => 'Investment not found or not eligible (status must be APPROVE or MATURED).',
                ];
                continue;
            }

            $hasAccrual = DB::connection('mysql_secondary')
                ->table('investment_accrual')
                ->whereRaw($this->cs('ipf_investment_no').' = ?', [$investmentNo])
                ->exists();

            if ($hasAccrual) {
                $failed[] = [
                    'investmentNo' => $investmentNo,
                    'reason' => 'Accrual schedule already exists for this investment.',
                ];
                continue;
            }

            try {
                // Legacy call is unconditional:
                //   CALL <db>.investment_accrual('<investment_no>');
                // The stored procedure handles its own
                // transaction + row generation. We use a parameter
                // bind instead of string concat (legacy interpolated
                // into the SQL).
                DB::connection('mysql_secondary')
                    ->statement('CALL investment_accrual(?)', [$investmentNo]);

                $this->auditService->log(
                    'investment.schedule.generated',
                    $request->user(),
                    'InvestmentProfile',
                    $profile->ipf_investment_id ?? null,
                    null,
                    ['ipf_investment_no' => $investmentNo],
                );

                $processed[] = $investmentNo;
            } catch (Throwable $e) {
                // Keep going — partial success is still useful to the
                // user (the legacy flow would simply error out mid-
                // loop with no visibility). We surface the SQLSTATE
                // message but strip any trailing stack chatter by
                // only taking the first line.
                $reason = strtok($e->getMessage(), "\n") ?: 'Stored procedure failed.';
                $failed[] = [
                    'investmentNo' => $investmentNo,
                    'reason' => $reason,
                ];
            }
        }

        return $this->sendOk([
            'processed' => $processed,
            'failed' => $failed,
            'successCount' => count($processed),
            'failureCount' => count($failed),
        ]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
