<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS purchase_order_master (Purchasing > Status PO & PR — PAGEID 1520 /
 * MENUID 1841). Lives in DB_SECOND_DATABASE. Columns derived from legacy BL
 * ZR_PURCHASING_STATUSPOPR_API: `pom_order_id` (PK), `pom_order_no`,
 * `pom_order_status`, `pom_description`, `pom_request_date`,
 * `vcs_vendor_code` and `oun_code`.
 */
class PurchaseOrderMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'purchase_order_master';

    protected $primaryKey = 'pom_order_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'pom_order_id',
        'pom_order_no',
        'pom_order_status',
        'pom_description',
        'pom_request_date',
        'vcs_vendor_code',
        'oun_code',
    ];
}
