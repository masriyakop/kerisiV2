<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS offline_receipt_authorize — receipt authorisation header (counter,
 * PTJ, purpose/remark, status). Used by AR > Cashbook PTJ listing
 * (BL `MZ_BL_AR_CASHBOOK_LISTING`). DB_SECOND_DATABASE.
 */
class OfflineReceiptAuthorize extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'offline_receipt_authorize';

    protected $primaryKey = 'ore_offline_receipt_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ore_offline_receipt_id',
        'ore_counter_no',
        'oun_code_ptj',
        'ore_remark',
        'ore_extended_field',
        'ore_status',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
