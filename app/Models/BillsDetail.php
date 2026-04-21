<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS bills_details (Account Payable bills line items). Used by Account
 * Bank Updated (PAGEID 1719 / MENUID 2078) for bank comparison & bulk update.
 * DB_SECOND_DATABASE; columns derived from BL SNA_API_AP_ACCOUNTBANKUPDATED.
 * The legacy schema does not expose a single-column PK on this table; keep a
 * nominal `bim_bills_id` + `bid_trans_type` access pattern and rely on the
 * query builder for bulk operations.
 */
class BillsDetail extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'bills_details';

    protected $primaryKey = 'bim_bills_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'bim_bills_id',
        'bid_trans_type',
        'bid_payto_type',
        'bid_payto_id',
        'bid_payto_name',
        'vsa_vendor_bank',
        'vsa_bank_accno',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
