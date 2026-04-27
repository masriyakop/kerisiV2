<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS asset_inventory_main (asset/inventory master record). Used by the
 * Asset > List of Asset page (PAGEID 1271 / MENUID 1548). Lives in
 * DB_SECOND_DATABASE.
 *
 * Source: legacy BL `API_ASSET_INVENTORY_LISTOFASSET`. Columns referenced
 * by the migrated read-only listing:
 *   aim_asset_id (PK), aim_asset_code, aim_gasset_code, aim_asset_type,
 *   aim_category, aim_asset_desc, aim_asset_detail_1, aim_serial_no,
 *   aim_brand_name, aim_initial_cost, aim_install_cost, aim_status,
 *   aim_acq_date, aim_registered_date, aim_reg_source,
 *   oun_code_current, ccr_costcentre_current, fty_fund_type,
 *   at_activity_code, acm_acct_code, cpa_project_no, mjm_journal_no,
 *   bim_bills_no, vma_voucher_no, grm_receive_no, aim_order_no.
 *
 * The legacy BL has a much wider column set (depreciation, building /
 * room joins, autosuggest endpoints, RBAC scoping via
 * organization_authorization). Those are out of scope here — see
 * AssetInventoryListController doc comment.
 */
class AssetInventoryMain extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'asset_inventory_main';

    protected $primaryKey = 'aim_asset_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'aim_asset_id',
        'aim_asset_code',
        'aim_gasset_code',
        'aim_asset_type',
        'aim_category',
        'aim_asset_desc',
        'aim_asset_detail_1',
        'aim_serial_no',
        'aim_brand_name',
        'aim_initial_cost',
        'aim_install_cost',
        'aim_status',
        'aim_acq_date',
        'aim_registered_date',
        'aim_reg_source',
        'oun_code_current',
        'ccr_costcentre_current',
        'fty_fund_type',
        'at_activity_code',
        'acm_acct_code',
        'cpa_project_no',
        'mjm_journal_no',
        'bim_bills_no',
        'vma_voucher_no',
        'grm_receive_no',
        'aim_order_no',
    ];
}
