<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger > Posting to GL (TB) (PAGEID 1139 / MENUID 1409).
 *
 * Legacy BL: `POSTING_TO_TB` with endpoints
 *   - posting=-1         -> list (grouped per posting/document/references)
 *   - displayMaster      -> single posting header
 *   - dtDebitDetails     -> DR line items (w/ lookup joins)
 *   - dtCreditDetails    -> CR line items (w/ lookup joins)
 *
 * All filters are applied to both master + details and require
 * `pmt_status='APPROVE'` and `pde_status='APPROVE'` (legacy parity).
 * The list legacy-groups by
 *   (pmt_posting_id, pmt_posting_no, pde_document_no, pde_reference,
 *    pde_reference1, pmt_system_id, pmt_status, pde_trans_date)
 * and aggregates SUM(..DT..) / SUM(..CR..). We preserve that grouping.
 *
 * The legacy Posting No / view icon deep-linked to MENUID 1413 which is
 * NOT in the current PAGE_SECOND_LEVEL_MENU migration scope. Following
 * the Journal Listing precedent (MENUID 2056), the View action is served
 * by the in-page `show()` endpoint returning master + DR lines + CR lines
 * in one payload.
 *
 * The legacy page also had a `Form` top filter and a `Form (Smart Filter)`
 * alongside this datatable. For UX consistency with every other migrated
 * Kerisi listing (Journal Listing, PTPTN Data, Status PO & PR) the union
 * of both forms is exposed here via a single smart filter modal on the
 * client. All fields are translated 1:1 from legacy keys.
 */
class PostingToTbController extends Controller
{
    use ApiResponse;

    /** Columns that clients may sort by on the list endpoint. */
    private const SORTABLE = [
        'pmt_posting_no',
        'pde_document_no',
        'pmt_system_id',
        'pmt_status',
        'pde_reference',
        'pde_reference1',
        'pde_trans_date',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'pde_trans_date');
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'pde_trans_date';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $postingNo = trim((string) $request->input('pmt_posting_no', ''));
        $systemId = trim((string) $request->input('pmt_system_id', ''));
        $documentNo = trim((string) $request->input('pde_document_no', ''));
        $reference = trim((string) $request->input('pde_reference', ''));
        $reference1 = trim((string) $request->input('pde_reference1', ''));
        $status = trim((string) $request->input('pmt_status', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        $totalAmt = trim((string) $request->input('pmt_total_amt', ''));

        $builder = $this->buildFilteredJoin(
            $q,
            $postingNo,
            $systemId,
            $documentNo,
            $reference,
            $reference1,
            $status,
            $dateFrom,
            $dateTo,
            $totalAmt,
        );

        // Legacy aggregation keys.
        $groupColumns = [
            'pm.pmt_posting_id',
            'pm.pmt_posting_no',
            'pd.pde_document_no',
            'pd.pde_reference',
            'pd.pde_reference1',
            'pm.pmt_system_id',
            'pm.pmt_status',
            'pd.pde_trans_date',
        ];

        // Total row count = number of grouped rows (legacy parity).
        $total = (int) DB::connection('mysql_secondary')
            ->query()
            ->fromSub(
                (clone $builder)
                    ->select(DB::raw('1'))
                    ->groupBy($groupColumns),
                'grouped',
            )
            ->count();

        $rows = (clone $builder)
            ->select([
                'pm.pmt_posting_id',
                'pm.pmt_posting_no',
                'pm.pmt_system_id',
                'pm.pmt_status',
                'pd.pde_document_no',
                'pd.pde_reference',
                'pd.pde_reference1',
                'pd.pde_trans_date',
                DB::raw("SUM(CASE WHEN pd.pde_trans_type = 'DT' THEN pd.pde_trans_amt ELSE 0 END) AS amount_dt"),
                DB::raw("SUM(CASE WHEN pd.pde_trans_type = 'CR' THEN pd.pde_trans_amt ELSE 0 END) AS amount_cr"),
            ])
            ->groupBy($groupColumns)
            ->orderBy($this->sortColumn($sortBy), $sortDir)
            ->orderBy('pm.pmt_posting_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // Grand totals for the footer — same filter, no grouping.
        $totals = (clone $builder)
            ->selectRaw(
                "SUM(CASE WHEN pd.pde_trans_type = 'DT' THEN pd.pde_trans_amt ELSE 0 END) AS sum_dt,
                 SUM(CASE WHEN pd.pde_trans_type = 'CR' THEN pd.pde_trans_amt ELSE 0 END) AS sum_cr"
            )
            ->first();

        $data = [];
        foreach ($rows as $i => $r) {
            $data[] = [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pmtPostingId' => (int) $r->pmt_posting_id,
                'postingNo' => $r->pmt_posting_no,
                'documentNo' => $r->pde_document_no !== null ? trim((string) $r->pde_document_no) : null,
                'systemId' => $r->pmt_system_id,
                'amountCr' => (float) ($r->amount_cr ?? 0),
                'amountDt' => (float) ($r->amount_dt ?? 0),
                'status' => $r->pmt_status,
                'reference' => $r->pde_reference !== null ? trim((string) $r->pde_reference) : null,
                'reference1' => $r->pde_reference1 !== null ? trim((string) $r->pde_reference1) : null,
                'transDate' => $r->pde_trans_date
                    ? Carbon::parse($r->pde_trans_date)->format('d/m/Y')
                    : null,
            ];
        }

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => [
                'amountDt' => (float) ($totals->sum_dt ?? 0),
                'amountCr' => (float) ($totals->sum_cr ?? 0),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $master = DB::connection('mysql_secondary')
            ->table('posting_master')
            ->where('pmt_posting_id', $id)
            ->first();

        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Posting record not found');
        }

        // DR/CR rows with legacy CONCAT_WS(' - ', code, desc) lookup joins.
        $baseDetail = DB::connection('mysql_secondary')
            ->table('posting_details AS pd')
            ->leftJoin('fund_type AS ft', 'pd.fty_fund_type', '=', 'ft.fty_fund_type')
            ->leftJoin('activity_type AS ats', 'pd.at_activity_code', '=', 'ats.at_activity_code')
            ->leftJoin('organization_unit AS ou', 'pd.oun_code', '=', 'ou.oun_code')
            ->leftJoin('account_main AS am', 'pd.acm_acct_code', '=', 'am.acm_acct_code')
            ->where('pd.pmt_posting_id', $id)
            ->where('pd.pde_status', 'APPROVE')
            ->select([
                'pd.pde_posting_detl_id AS id',
                'pd.pde_trans_type',
                DB::raw("CASE WHEN pd.fty_fund_type IS NULL OR pd.fty_fund_type = '' THEN NULL ELSE CONCAT_WS(' - ', pd.fty_fund_type, ft.fty_fund_desc) END AS fund"),
                DB::raw("CASE WHEN pd.at_activity_code IS NULL OR pd.at_activity_code = '' THEN NULL ELSE CONCAT_WS(' - ', pd.at_activity_code, ats.at_activity_description_bm) END AS activity"),
                DB::raw("CASE WHEN pd.oun_code IS NULL OR pd.oun_code = '' THEN NULL ELSE CONCAT_WS(' - ', pd.oun_code, ou.oun_desc) END AS ptj"),
                DB::raw("CASE WHEN pd.acm_acct_code IS NULL OR pd.acm_acct_code = '' THEN NULL ELSE CONCAT_WS(' - ', pd.acm_acct_code, am.acm_acct_desc) END AS account"),
                'pd.pde_document_no AS document_no',
                'pd.pde_reference AS reference',
                'pd.pde_reference1 AS reference1',
                'pd.pde_trans_amt AS amount',
                'pd.pde_payto_id',
                'pd.pde_payto_name',
            ])
            ->orderBy('pd.pde_posting_detl_id');

        $debitRows = (clone $baseDetail)->where('pd.pde_trans_type', 'DT')->get();
        $creditRows = (clone $baseDetail)->where('pd.pde_trans_type', 'CR')->get();

        $mapLine = fn ($row): array => [
            'id' => (int) $row->id,
            'fund' => $row->fund,
            'activity' => $row->activity,
            'ptj' => $row->ptj,
            'account' => $row->account,
            'documentNo' => $row->document_no !== null ? trim((string) $row->document_no) : null,
            'reference' => $row->reference !== null ? trim((string) $row->reference) : null,
            'reference1' => $row->reference1 !== null ? trim((string) $row->reference1) : null,
            'amount' => $row->amount !== null ? (float) $row->amount : 0.0,
            'payTo' => $this->formatPayTo($row->pde_payto_id, $row->pde_payto_name),
        ];

        $debit = $debitRows->map($mapLine)->values()->all();
        $credit = $creditRows->map($mapLine)->values()->all();

        $sumDebit = array_sum(array_column($debit, 'amount'));
        $sumCredit = array_sum(array_column($credit, 'amount'));

        return $this->sendOk([
            'header' => [
                'pmtPostingId' => (int) $master->pmt_posting_id,
                'postingNo' => $master->pmt_posting_no,
                'systemId' => $master->pmt_system_id,
                'status' => $master->pmt_status,
                'totalAmount' => $master->pmt_total_amt !== null ? (float) $master->pmt_total_amt : 0.0,
                'description' => $master->pmt_posting_desc,
                'currency' => $master->cym_currency_code,
                'postedDate' => $master->pmt_posteddate
                    ? Carbon::parse($master->pmt_posteddate)->format('d/m/Y')
                    : null,
                'postedBy' => $master->pmt_postedby,
                'sumDebit' => (float) $sumDebit,
                'sumCredit' => (float) $sumCredit,
            ],
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    public function options(): JsonResponse
    {
        // Distinct lookups derived from posting_master, mirroring legacy dropdowns:
        //   - SELECT DISTINCT pmt_system_id FROM posting_master
        //   - SELECT DISTINCT pmt_status FROM posting_master
        $systems = DB::connection('mysql_secondary')
            ->table('posting_master')
            ->whereNotNull('pmt_system_id')
            ->where('pmt_system_id', '!=', '')
            ->distinct()
            ->orderBy('pmt_system_id')
            ->pluck('pmt_system_id')
            ->values()
            ->all();

        $statuses = DB::connection('mysql_secondary')
            ->table('posting_master')
            ->whereNotNull('pmt_status')
            ->where('pmt_status', '!=', '')
            ->distinct()
            ->orderBy('pmt_status')
            ->pluck('pmt_status')
            ->values()
            ->all();

        return $this->sendOk([
            'systemIds' => array_values(array_map('strval', $systems)),
            'statuses' => array_values(array_map('strval', $statuses)),
        ]);
    }

    /**
     * Build the master+detail join with legacy-matching APPROVE gate plus
     * all smart-filter columns. Returned as a fresh query builder so the
     * caller can clone it for count/list/footer queries independently.
     */
    private function buildFilteredJoin(
        string $q,
        string $postingNo,
        string $systemId,
        string $documentNo,
        string $reference,
        string $reference1,
        string $status,
        string $dateFrom,
        string $dateTo,
        string $totalAmt,
    ) {
        $query = DB::connection('mysql_secondary')
            ->table('posting_master AS pm')
            ->join('posting_details AS pd', 'pm.pmt_posting_id', '=', 'pd.pmt_posting_id')
            ->where('pm.pmt_status', 'APPROVE')
            ->where('pd.pde_status', 'APPROVE');

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(pm.pmt_posting_id, ''),
                    IFNULL(pm.pmt_posting_no, ''),
                    IFNULL(pd.pde_document_no, ''),
                    IFNULL(pd.pde_reference, ''),
                    IFNULL(pd.pde_reference1, ''),
                    IFNULL(pm.pmt_system_id, ''),
                    IFNULL(pm.pmt_status, ''),
                    IFNULL(DATE_FORMAT(pd.pde_trans_date, '%d/%m/%Y'), '')
                )) LIKE ?",
                [$like]
            );
        }

        if ($postingNo !== '') {
            $query->where('pm.pmt_posting_no', $postingNo);
        }
        if ($systemId !== '') {
            $query->whereRaw('IFNULL(pm.pmt_system_id, "") = ?', [$systemId]);
        }
        if ($documentNo !== '') {
            $query->where('pd.pde_document_no', $documentNo);
        }
        if ($reference !== '') {
            $query->where('pd.pde_reference', $reference);
        }
        if ($reference1 !== '') {
            $query->where('pd.pde_reference1', $reference1);
        }
        if ($status !== '') {
            $query->whereRaw('IFNULL(pm.pmt_status, "") = ?', [$status]);
        }
        if ($dateFrom !== '') {
            // Legacy expects dd/mm/yyyy strings; mirror STR_TO_DATE parsing.
            $query->whereRaw(
                "pd.pde_trans_date >= STR_TO_DATE(?, '%d/%m/%Y')",
                [$dateFrom]
            );
        }
        if ($dateTo !== '') {
            $query->whereRaw(
                "pd.pde_trans_date <= STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')",
                [$dateTo.' 23:59:59']
            );
        }
        if ($totalAmt !== '' && is_numeric($totalAmt)) {
            $query->where('pm.pmt_total_amt', (float) $totalAmt);
        }

        return $query;
    }

    /** Map sort_by query param to the aliased column in the joined SQL. */
    private function sortColumn(string $key): string
    {
        return match ($key) {
            'pmt_posting_no', 'pmt_system_id', 'pmt_status' => 'pm.'.$key,
            'pde_document_no', 'pde_reference', 'pde_reference1', 'pde_trans_date' => 'pd.'.$key,
            default => 'pd.pde_trans_date',
        };
    }

    private function formatPayTo(?string $id, ?string $name): ?string
    {
        $parts = array_filter([trim((string) $id), trim((string) $name)], static fn ($v) => $v !== '');

        return count($parts) ? implode(' - ', $parts) : null;
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
