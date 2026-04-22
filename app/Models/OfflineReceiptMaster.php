<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS offline_receipt_master — offline (cashier) receipts. Used by AR >
 * Cashbook PTJ listing (BL `MZ_BL_AR_CASHBOOK_LISTING`). DB_SECOND_DATABASE.
 * Columns scoped to what the listing query references; extend as needed when
 * more features are ported.
 */
class OfflineReceiptMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'offline_receipt_master';

    protected $primaryKey = 'orm_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'orm_id',
        'orm_counter_no',
        'orm_total_amt',
        'orm_status',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
