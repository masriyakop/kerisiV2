<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS preprinted_receipt_stock_master — preprinted receipt booklet header.
 * Used by AR > Cashbook PTJ listing (BL `MZ_BL_AR_CASHBOOK_LISTING`).
 */
class PreprintedReceiptStockMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'preprinted_receipt_stock_master';

    protected $primaryKey = 'prsm_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'prsm_id',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
