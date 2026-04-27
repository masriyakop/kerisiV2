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
    use Auditable, HasFactory;

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
        'vcs_fax_no',
        'vcs_email_address',
        'vcs_contact_person',
        'vcs_ic_no',
        'vcs_registration_no',
        'vcs_reg_date',
        'vcs_reg_exp_date',
        'vcs_kk_regno',
        'vcs_kk_expired_date',
        'vcs_tax_regno',
        'vcs_vendor_status',
        'vcs_iscreditor',
        'vcs_isdebtor',
        'vcs_bumi_status',
        'vcs_company_category',
        'vcs_authorize_capital',
        'vcs_paid_up_capital',
        'vcs_unv_reg_date',
        'vcs_unv_req_exp_date',
        'vcs_epf_no',
        'vcs_socso_no',
        'vcs_reg_no_kpm',
        'vcs_reg_date_kpm',
        'vcs_reg_expdate_kpm',
        'vcs_ros_no',
        'vcs_temp_code',
        'vcs_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
