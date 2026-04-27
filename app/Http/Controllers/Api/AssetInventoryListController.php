<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Asset > List of Asset (PAGEID 1271 / MENUID 1548).
 *
 * Source: legacy FIMS BL `API_ASSET_INVENTORY_LISTOFASSET`
 * (?dt_listingAssetInventory=1).
 *
 * # In scope (this migration)
 * Read-only listing of `asset_inventory_main` joined to a small set of
 * lookup tables driven by the columns the SPA actually displays
 * (`dt_bi`):
 *
 *   - organization_unit  (current PTJ name)
 *   - cost_centre        (current cost centre name)
 *   - fund_type          (fund description)
 *   - activity           (activity description)
 *   - account_main       (account description)
 *
 * Smart filter fields cover the most common columns: aim_asset_code,
 * aim_serial_no, aim_brand_name, aim_asset_type, aim_category,
 * aim_status, aim_reg_source, aim_acq_date_start/_end,
 * aim_registered_date (year), aim_initial_cost_min/_max, oun_code,
 * ccr_costcentre, fty_fund_type, at_activity_code, acm_acct_code,
 * cpa_project_no.
 *
 * # Out of scope (deferred)
 * The legacy BL also exposes ~10 autosuggest endpoints, depreciation
 * columns (depreciation_setup / depreciation_provision joins),
 * building + room labels, and an organization_authorization-driven
 * RBAC scoping that gates which cost centres / PTJs each staff member
 * can see. Those require either:
 *   - dedicated lookup endpoints (autosuggest), and / or
 *   - the FIMS staff-id session context that is not yet wired into the
 *     Sanctum user (see TenderQuotationController and friends — same
 *     limitation applies project-wide).
 *
 * Until that context is available, the listing returns the full set of
 * assets that match the supplied filters and relies on the page-level
 * `permission:asset.read` check for authorisation. This is documented
 * in the page README and on the controller for follow-up.
 */
class AssetInventoryListController extends Controller
{
    use ApiResponse;

    private const CONN = 'mysql_secondary';

    private const SORTABLE = [
        'aim_asset_code',
        'aim_asset_type',
        'aim_gasset_code',
        'aim_category',
        'aim_asset_desc',
        'aim_serial_no',
        'aim_brand_name',
        'aim_initial_cost',
        'aim_install_cost',
        'aim_status',
        'aim_registered_date',
        'aim_acq_date',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(200, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'aim_asset_code');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'aim_asset_code';
        }

        $base = $this->baseQuery($request);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(aim.aim_asset_code,''),
                    IFNULL(aim.aim_gasset_code,''),
                    IFNULL(aim.aim_asset_type,''),
                    IFNULL(aim.aim_category,''),
                    IFNULL(aim.aim_asset_desc,''),
                    IFNULL(aim.aim_serial_no,''),
                    IFNULL(aim.aim_brand_name,''),
                    IFNULL(aim.aim_status,''),
                    IFNULL(aim.aim_initial_cost,''),
                    IFNULL(aim.aim_install_cost,''),
                    IFNULL(aim.grm_receive_no,''),
                    IFNULL(aim.aim_order_no,''),
                    IFNULL(aim.mjm_journal_no,''),
                    IFNULL(aim.bim_bills_no,''),
                    IFNULL(aim.vma_voucher_no,'')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $base)->count();
        $totalInitial = (float) ((clone $base)->sum('aim.aim_initial_cost') ?? 0);
        $totalInstall = (float) ((clone $base)->sum('aim.aim_install_cost') ?? 0);

        $rows = (clone $base)
            ->select([
                'aim.aim_asset_id',
                'aim.aim_asset_code',
                'aim.aim_gasset_code',
                'aim.aim_asset_type',
                'aim.aim_category',
                DB::raw("CONCAT_WS(' - ', aim.aim_asset_code, aim.aim_asset_desc) AS item"),
                'aim.aim_asset_desc',
                'aim.aim_asset_detail_1',
                'aim.aim_serial_no',
                'aim.aim_brand_name',
                DB::raw("CONCAT_WS(' - ', aim.oun_code_current, ou.oun_desc) AS current_ptj"),
                DB::raw("CONCAT_WS(' - ', aim.fty_fund_type, ft.fty_fund_desc) AS fund"),
                DB::raw("CONCAT_WS(' - ', aim.at_activity_code, ac.at_activity_description_bm) AS activity"),
                DB::raw("CONCAT_WS(' - ', aim.acm_acct_code, am.acm_acct_desc) AS account_code"),
                DB::raw("CONCAT_WS(' - ', aim.ccr_costcentre_current, cc.ccr_costcentre_desc) AS current_cost_centre"),
                'aim.aim_initial_cost',
                'aim.aim_install_cost',
                'aim.grm_receive_no',
                DB::raw('aim.aim_order_no AS pom_order_no'),
                'aim.mjm_journal_no',
                'aim.bim_bills_no',
                'aim.vma_voucher_no',
                'aim.aim_status',
                'aim.aim_registered_date',
                'aim.aim_acq_date',
            ])
            ->orderBy('aim.'.$sortBy, $sortDir)
            ->orderBy('aim.aim_asset_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'assetId' => (int) $r->aim_asset_id,
                'assetCode' => $r->aim_asset_code,
                'assetType' => $r->aim_asset_type,
                'assetNo' => $r->aim_asset_code,
                'gAssetNo' => $r->aim_gasset_code,
                'category' => $r->aim_category,
                'item' => $r->item,
                'assetDescription' => $r->aim_asset_desc,
                'detail1' => $r->aim_asset_detail_1,
                'serialNo' => $r->aim_serial_no,
                'brand' => $r->aim_brand_name,
                'currentPtj' => $r->current_ptj,
                'fund' => $r->fund,
                'activity' => $r->activity,
                'accountCode' => $r->account_code,
                'currentCostCentre' => $r->current_cost_centre,
                'initialCost' => $r->aim_initial_cost !== null ? (float) $r->aim_initial_cost : null,
                'installCost' => $r->aim_install_cost !== null ? (float) $r->aim_install_cost : null,
                'grnNo' => $r->grm_receive_no,
                'porNo' => $r->pom_order_no,
                'journalNo' => $r->mjm_journal_no,
                'billNo' => $r->bim_bills_no,
                'voucherNo' => $r->vma_voucher_no,
                'status' => $r->aim_status,
                'statusDate' => $r->aim_registered_date,
                'acqDate' => $r->aim_acq_date,
            ];
        })->all();

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'footer' => [
                'totalRecord' => $total,
                'totalInitialCost' => $totalInitial,
                'totalInstallCost' => $totalInstall,
            ],
        ]);
    }

    private function baseQuery(Request $request): QueryBuilder
    {
        $b = DB::connection(self::CONN)
            ->table('asset_inventory_main as aim')
            ->leftJoin('organization_unit as ou', 'aim.oun_code_current', '=', 'ou.oun_code')
            ->leftJoin('cost_centre as cc', 'aim.ccr_costcentre_current', '=', 'cc.ccr_costcentre')
            ->leftJoin('fund_type as ft', 'aim.fty_fund_type', '=', 'ft.fty_fund_type')
            ->leftJoin('activity as ac', 'aim.at_activity_code', '=', 'ac.at_activity_code')
            ->leftJoin('account_main as am', 'aim.acm_acct_code', '=', 'am.acm_acct_code');

        $assetCode = trim((string) $request->input('aim_asset_code', ''));
        $serialNo = trim((string) $request->input('aim_serial_no', ''));
        $brand = trim((string) $request->input('aim_brand_name', ''));
        $assetType = trim((string) $request->input('aim_asset_type', ''));
        $category = trim((string) $request->input('aim_category', ''));
        $status = trim((string) $request->input('aim_status', ''));
        $regSource = trim((string) $request->input('aim_reg_source', ''));
        $acqStart = trim((string) $request->input('aim_acq_date_start', ''));
        $acqEnd = trim((string) $request->input('aim_acq_date_end', ''));
        $regYear = trim((string) $request->input('aim_registered_date', ''));
        $costMin = trim((string) $request->input('aim_initial_cost_min', ''));
        $costMax = trim((string) $request->input('aim_initial_cost_max', ''));
        $oun = trim((string) $request->input('oun_code', ''));
        $costCentre = trim((string) $request->input('ccr_costcentre', ''));
        $fundType = trim((string) $request->input('fty_fund_type', ''));
        $activity = trim((string) $request->input('at_activity_code', ''));
        $acctCode = trim((string) $request->input('acm_acct_code', ''));
        $project = trim((string) $request->input('cpa_project_no', ''));

        if ($assetCode !== '') {
            $b->where('aim.aim_asset_code', 'like', $this->likeEscape($assetCode));
        }
        if ($serialNo !== '') {
            $b->where('aim.aim_serial_no', 'like', $this->likeEscape($serialNo));
        }
        if ($brand !== '') {
            $b->where('aim.aim_brand_name', 'like', $this->likeEscape($brand));
        }
        if ($assetType !== '') {
            $b->where('aim.aim_asset_type', $assetType);
        }
        if ($category !== '') {
            $b->where('aim.aim_category', $category);
        }
        if ($status !== '') {
            $b->where('aim.aim_status', $status);
        }
        if ($regSource !== '') {
            $b->where('aim.aim_reg_source', $regSource);
        }
        if ($acqStart !== '') {
            $b->whereRaw("aim.aim_acq_date >= STR_TO_DATE(?, '%d/%m/%Y')", [$acqStart]);
        }
        if ($acqEnd !== '') {
            $b->whereRaw("aim.aim_acq_date <= STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')", [$acqEnd.' 23:59:59']);
        }
        if ($regYear !== '' && ctype_digit($regYear)) {
            $b->whereRaw('YEAR(aim.aim_registered_date) = ?', [(int) $regYear]);
        }
        if ($costMin !== '' && is_numeric(str_replace(',', '', $costMin))) {
            $b->where('aim.aim_initial_cost', '>=', (float) str_replace(',', '', $costMin));
        }
        if ($costMax !== '' && is_numeric(str_replace(',', '', $costMax))) {
            $b->where('aim.aim_initial_cost', '<=', (float) str_replace(',', '', $costMax));
        }
        if ($oun !== '') {
            $b->where('aim.oun_code_current', $oun);
        }
        if ($costCentre !== '') {
            $b->where('aim.ccr_costcentre_current', $costCentre);
        }
        if ($fundType !== '') {
            $b->where('aim.fty_fund_type', $fundType);
        }
        if ($activity !== '') {
            $b->where('aim.at_activity_code', $activity);
        }
        if ($acctCode !== '') {
            $b->where('aim.acm_acct_code', $acctCode);
        }
        if ($project !== '') {
            $b->where('aim.cpa_project_no', $project);
        }

        return $b;
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
