<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS vend_customer_supplier (Account Payable > Payee Registration / Utility
 * Registration). Lives in DB_SECOND_DATABASE; columns derived from BL
 * NF_BL_AP_PAY_REGISTRATION + SNA_API_AP_UTILITYREGISTRATION.
 */
class VendCustomerSupplier extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'vend_customer_supplier';

    protected $primaryKey = 'vcs_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'vcs_id',
        'vcs_vendor_code',
        'vcs_vendor_name',
        'vcs_addr1',
        'vcs_addr2',
        'vcs_addr3',
        'vcs_town',
        'vcs_state',
        'vcs_vendor_bank',
        'vcs_bank_accno',
        'vcs_biller_code',
        'vcs_tel_no',
        'vcs_email_address',
        'vcs_contact_person',
        'vcs_ic_no',
        'vcs_registration_no',
        'vcs_vendor_status',
        'vcs_iscreditor',
        'vcs_isdebtor',
        'vcs_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
