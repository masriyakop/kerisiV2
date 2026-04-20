<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AccountMain;
use App\Models\FundType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "List of Account Code (PPI)" listing endpoint.
 *
 * Source: FIMS BL `SNA_API_SETUPMAINTENANCE_LISTACCOUNTCODEPPI` (PAGEID 2870 / MENUID 3453).
 * Tables (mysql_secondary): account_main (self-joined 5 levels), account_main_fund, fund_type.
 * All filtering/joining uses the Eloquent query builder — no raw SQL statements.
 */
class AccountCodePpiController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'acm_acct_code');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSort = [
            'acm_acct_code', 'acm_acct_desc', 'acm_acct_level', 'acm_acct_activity',
            'acm_acct_type', 'acm_acct_status', 'acm_behavior',
        ];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'acm_acct_code';
        }

        $query = $this->baseQuery();

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(AM1.acm_acct_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(AM1.acm_acct_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(AM1.acm_acct_activity, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(AM1.acm_acct_level, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(AM1.acm_acct_parent, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(AM1.acm_acct_group, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(AM1.acm_behavior, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(AMF.fty_fund_type, "")) LIKE ?', [$like]);
            });
        }

        // Top filter
        if ($request->filled('cm_fund_type')) {
            $query->where('AMF.fty_fund_type', $request->input('cm_fund_type'));
        }
        if ($request->filled('acm_acct_activity')) {
            $query->where('AM1.acm_acct_activity', $request->input('acm_acct_activity'));
        }
        if ($request->filled('acm_acct_type')) {
            $query->where('AM1.acm_acct_type', $request->input('acm_acct_type'));
        }
        if ($request->filled('cm_account_code')) {
            $query->where('AM1.acm_acct_code', $request->input('cm_account_code'));
        }

        // Smart filter
        if ($request->filled('acm_acct_code_smart_filter')) {
            $query->where('AM1.acm_acct_code', $request->input('acm_acct_code_smart_filter'));
        }
        if ($request->filled('acm_acct_desc')) {
            $query->where('AM1.acm_acct_desc', $request->input('acm_acct_desc'));
        }
        if ($request->filled('acm_acct_level')) {
            $query->where('AM1.acm_acct_level', $request->input('acm_acct_level'));
        }
        if ($request->filled('acm_behavior')) {
            $query->whereRaw('IFNULL(AM1.acm_behavior, "") = ?', [$request->input('acm_behavior')]);
        }
        if ($request->filled('acm_acct_status')) {
            $want = strtoupper((string) $request->input('acm_acct_status')) === 'ACTIVE' ? '1' : '0';
            $query->where('AM1.acm_acct_status', $want);
        }

        $groupedQuery = $query->groupBy(
            'AM1.acm_acct_code',
            'AM1.acm_acct_desc',
            'AM1.acm_acct_activity',
            'AM1.acm_acct_level',
            'AM1.acm_acct_parent',
            'AM1.acm_acct_status',
            'AM1.acm_acct_group',
            'AM1.acm_behavior',
            'AM2.acm_acct_type',
            'AM3.acm_acct_type',
            'AM4.acm_acct_type',
            'AM5.acm_acct_type',
        );

        $connection = AccountMain::query()->getModel()->getConnectionName();
        $total = (int) DB::connection($connection)
            ->query()
            ->fromSub($groupedQuery, 'tbl')
            ->count();

        $sortExpression = match ($sortBy) {
            'acm_acct_type' => DB::raw('acct_class'),
            default => DB::raw("AM1.{$sortBy}"),
        };

        $rows = (clone $groupedQuery)
            ->orderBy($sortExpression, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($row, $idx) => [
            'index' => (($page - 1) * $limit) + $idx + 1,
            'acm_acct_code' => $row->acm_acct_code,
            'acm_acct_desc' => $row->acm_acct_desc,
            'acm_acct_level' => $row->acm_acct_level,
            'acm_acct_activity' => $row->acm_acct_activity,
            'acm_acct_type' => $row->acct_class,
            'fund_type' => $row->fund_type,
            'acm_behavior' => $row->acm_behavior,
            'acm_acct_status' => (string) $row->acm_acct_status === '1' ? 'ACTIVE' : 'INACTIVE',
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    public function options(): JsonResponse
    {
        $fundType = FundType::query()
            ->where('fty_status', '1')
            ->orderBy('fty_fund_type')
            ->get(['fty_fund_type', 'fty_fund_desc'])
            ->map(fn ($row) => [
                'id' => $row->fty_fund_type,
                'label' => $row->fty_fund_type . ' - ' . $row->fty_fund_desc,
            ]);

        $accountType = AccountMain::query()
            ->select('acm_acct_activity')
            ->whereNotNull('acm_acct_activity')
            ->where('acm_acct_activity', '!=', '')
            ->distinct()
            ->orderBy('acm_acct_activity')
            ->pluck('acm_acct_activity')
            ->map(fn ($v) => ['id' => $v, 'label' => $v])
            ->values();

        $accountClass = AccountMain::query()
            ->select('acm_acct_type')
            ->whereNotNull('acm_acct_type')
            ->where('acm_acct_type', '!=', '')
            ->distinct()
            ->orderBy('acm_acct_type')
            ->pluck('acm_acct_type')
            ->map(fn ($v) => ['id' => $v, 'label' => $v])
            ->values();

        $accountCode = AccountMain::query()
            ->select('acm_acct_code', 'acm_acct_desc')
            ->whereNotNull('acm_acct_code')
            ->where('acm_acct_code', '!=', '')
            ->orderBy('acm_acct_code')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->acm_acct_code,
                'label' => $row->acm_acct_code . ' - ' . $row->acm_acct_desc,
            ]);

        $accountLevel = AccountMain::query()
            ->select('acm_acct_level')
            ->whereNotNull('acm_acct_level')
            ->distinct()
            ->orderBy('acm_acct_level')
            ->pluck('acm_acct_level')
            ->map(fn ($v) => ['id' => (string) $v, 'label' => (string) $v])
            ->values();

        $statementItem = AccountMain::query()
            ->select('acm_behavior')
            ->whereNotNull('acm_behavior')
            ->distinct()
            ->orderBy('acm_behavior')
            ->pluck('acm_behavior')
            ->map(fn ($v) => ['id' => $v, 'label' => $v])
            ->values();

        $accountDesc = AccountMain::query()
            ->select('acm_acct_desc')
            ->whereNotNull('acm_acct_desc')
            ->where('acm_acct_desc', '!=', '')
            ->distinct()
            ->orderBy('acm_acct_desc')
            ->pluck('acm_acct_desc')
            ->map(fn ($v) => ['id' => $v, 'label' => $v])
            ->values();

        $status = [
            ['id' => 'ACTIVE', 'label' => 'ACTIVE'],
            ['id' => 'INACTIVE', 'label' => 'INACTIVE'],
        ];

        return $this->sendOk([
            'topFilter' => [
                'fundType' => $fundType,
                'accountType' => $accountType,
                'accountClass' => $accountClass,
                'accountCode' => $accountCode,
            ],
            'smartFilter' => [
                'accountCode' => $accountCode,
                'accountDesc' => $accountDesc,
                'accountLevel' => $accountLevel,
                'statementItem' => $statementItem,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Shared base query with 5-level self-join on account_main and fund joins.
     * Mirrors the FROM/JOIN clause of the legacy BL, expressed via the Eloquent
     * query builder (no raw SQL strings).
     */
    private function baseQuery()
    {
        return AccountMain::query()
            ->from('account_main as AM1')
            ->leftJoin('account_main as AM2', 'AM2.acm_acct_code', '=', 'AM1.acm_acct_parent')
            ->leftJoin('account_main as AM3', 'AM3.acm_acct_code', '=', 'AM2.acm_acct_parent')
            ->leftJoin('account_main as AM4', 'AM4.acm_acct_code', '=', 'AM3.acm_acct_parent')
            ->leftJoin('account_main as AM5', 'AM5.acm_acct_code', '=', 'AM4.acm_acct_parent')
            ->leftJoin('account_main_fund as AMF', 'AMF.acm_acct_code', '=', 'AM1.acm_acct_code')
            ->leftJoin('fund_type as FT', 'FT.fty_fund_type', '=', 'AMF.fty_fund_type')
            ->select([
                'AM1.acm_acct_code',
                'AM1.acm_acct_desc',
                'AM1.acm_acct_activity',
                'AM1.acm_acct_level',
                'AM1.acm_acct_parent',
                'AM1.acm_acct_status',
                'AM1.acm_acct_group',
                'AM1.acm_behavior',
                DB::raw("CASE
                    WHEN AM1.acm_acct_level = '1' THEN AM1.acm_acct_type
                    WHEN AM1.acm_acct_level = '2' THEN AM2.acm_acct_type
                    WHEN AM1.acm_acct_level = '3' THEN AM3.acm_acct_type
                    WHEN AM1.acm_acct_level = '4' THEN AM4.acm_acct_type
                    WHEN AM1.acm_acct_level = '5' THEN AM5.acm_acct_type
                END AS acct_class"),
                DB::raw("GROUP_CONCAT(CONCAT_WS(' - ', AMF.fty_fund_type, FT.fty_fund_desc) SEPARATOR ',\n') AS fund_type"),
            ]);
    }
}
