<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS goods_receive_master (Goods Received Note header). Used by the
 * Vendor Portal > Purchase Order Status page (PAGEID 1664 / MENUID 2015)
 * to surface the "GRN" badges per purchase order. Lives in
 * DB_SECOND_DATABASE.
 *
 * Source: legacy BL `NF_BL_VENDOR_PO_STATUS` joins
 * `goods_receive_master grm ON grm.pom_order_no = pom.pom_order_no` and
 * displays `grm_receive_no` + `grm_total_amt` per row.
 */
class GoodsReceiveMaster extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'goods_receive_master';

    protected $primaryKey = 'grm_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'grm_id',
        'grm_receive_no',
        'grm_total_amt',
        'pom_order_no',
    ];
}
