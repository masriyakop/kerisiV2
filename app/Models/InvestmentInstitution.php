<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS investment_institution (Account Bank by Payee — Investment variant).
 * DB_SECOND_DATABASE; columns derived from BL AS_BL_AP_ACCOUNTBANKBPAYEE.
 */
class InvestmentInstitution extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'investment_institution';

    protected $primaryKey = 'iit_inst_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'iit_inst_code',
        'iit_inst_name',
        'bnm_bank_code',
        'bnm_shortname',
        'iit_bank_branch',
        'iit_address1',
        'iit_address2',
        'iit_address3',
        'iit_pcode',
        'iit_city',
        'iit_state',
        'iit_country',
        'itt_status',
    ];
}
