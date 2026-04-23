<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS voucher_master (Account Payable voucher header). Used by Account
 * Bank Updated (PAGEID 1719 / MENUID 2078) to join against voucher_details
 * for bank account comparison. DB_SECOND_DATABASE; columns derived from BL
 * SNA_API_AP_ACCOUNTBANKUPDATED.
 */
class VoucherMaster extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'voucher_master';

    protected $primaryKey = 'vma_voucher_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'vma_voucher_id',
        'vma_voucher_no',
        'vma_vch_status',
        'vma_vch_description',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
