<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS offline_receipt_rate — rate lines for preprinted receipts, used to
 * join preprinted_receipt_stock_details to the authoriser (AR > Cashbook PTJ).
 */
class OfflineReceiptRate extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'offline_receipt_rate';

    protected $primaryKey = 'orr_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'orr_id',
        'ore_offline_receipt_id',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
