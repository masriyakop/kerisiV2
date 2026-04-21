<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS sponsor (Account Bank by Payee — Sponsor variant).
 * DB_SECOND_DATABASE; columns derived from BL AS_BL_AP_ACCOUNTBANKBPAYEE.
 */
class Sponsor extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'sponsor';

    protected $primaryKey = 'spn_sponsor_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'spn_sponsor_code',
        'spn_sponsor_name',
        'spn_bank_acc_no',
        'spn_bank_name_cd',
        'spn_address1',
        'spn_address2',
        'spn_city',
        'spn_postcode',
        'spn_state',
        'spn_contact_person',
        'spn_contact_no',
        'spn_status_cd',
    ];
}
