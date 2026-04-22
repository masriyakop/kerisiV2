<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS preprinted_receipt_stock_details — per-ticket preprinted receipt line
 * (value, counter). Used by AR > Cashbook PTJ listing.
 */
class PreprintedReceiptStockDetails extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'preprinted_receipt_stock_details';

    protected $primaryKey = 'prsd_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'prsd_id',
        'prsm_id',
        'prsd_counter_no',
        'prsd_value',
        'orr_id',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
