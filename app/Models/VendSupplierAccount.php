<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS vend_supplier_account (vendor bank accounts). Used by Account Bank by
 * Payee (vendor variant). DB_SECOND_DATABASE; columns derived from BL
 * AS_BL_AP_ACCOUNTBANKBPAYEE.
 */
class VendSupplierAccount extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'vend_supplier_account';

    protected $primaryKey = 'vsa_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'vsa_id',
        'vcs_vendor_code',
        'vsa_vendor_bank',
        'vsa_bank_accno',
        'vsa_status',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
