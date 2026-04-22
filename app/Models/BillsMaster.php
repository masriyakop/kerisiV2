<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS bills_master (Account Payable bills header). Used by Account Bank
 * Updated (PAGEID 1719 / MENUID 2078) to join against bills_details for bank
 * account comparison. DB_SECOND_DATABASE; columns derived from BL
 * SNA_API_AP_ACCOUNTBANKUPDATED.
 */
class BillsMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'bills_master';

    protected $primaryKey = 'bim_bills_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'bim_bills_id',
        'bim_bills_no',
        'bim_bills_desc',
        'bim_voucher_no',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
