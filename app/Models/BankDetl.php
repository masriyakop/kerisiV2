<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS bank_detl (Cashbook > Bank Account, PAGEID 1736 / MENUID 2097).
 * Lives in DB_SECOND_DATABASE; columns derived from BL
 * SNA_API_CASHBOOK_BANKACCOUNT.
 */
class BankDetl extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'bank_detl';

    protected $primaryKey = 'bnd_bank_detl_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'bnd_bank_detl_id',
        'bnm_bank_id',
        'bnd_bank_acctno',
        'acm_acct_code',
        'oun_code',
        'bnd_status',
        'bnd_is_bank_main',
        'bnd_currency_code',
        'bnd_extended_field',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];
}
