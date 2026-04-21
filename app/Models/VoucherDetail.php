<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS voucher_details (Account Payable voucher line items). Used by
 * Account Bank Updated (PAGEID 1719 / MENUID 2078) for bank comparison &
 * bulk update. DB_SECOND_DATABASE; columns derived from BL
 * SNA_API_AP_ACCOUNTBANKUPDATED.
 */
class VoucherDetail extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'voucher_details';

    protected $primaryKey = 'vma_voucher_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'vma_voucher_id',
        'vde_trans_type',
        'vde_payto_type',
        'vde_payto_id',
        'vde_payto_name',
        'vde_bank_name',
        'vde_bank_acctno',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
