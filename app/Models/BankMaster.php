<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS bank_master (Cashbook > Bank Master, PAGEID 1682 / MENUID 2036).
 * Lives in DB_SECOND_DATABASE; columns derived from BL
 * ZR_MODUL_SETUP_BANKMASTER_API.
 */
class BankMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'bank_master';

    protected $primaryKey = 'bnm_bank_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'bnm_bank_id',
        'bnm_bank_code',
        'bnm_bank_code_main',
        'bnm_bank_desc',
        'bnm_shortname',
        'bnm_bank_address',
        'bnm_address_country',
        'bnm_address_postcode',
        'bnm_address_city',
        'bnm_contact_person',
        'bnm_branch_name',
        'bnm_office_telno',
        'bnm_office_faxno',
        'bnm_url_address',
        'bnm_swift_code',
        'bnm_business_nature',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];
}
