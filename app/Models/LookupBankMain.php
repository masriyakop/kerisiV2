<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS lookup_bank_main (Cashbook > Bank Setup, PAGEID 2680 / MENUID 3246).
 * Lives in DB_SECOND_DATABASE; no Laravel migration is shipped — schema is
 * owned by the legacy FIMS database. Columns mirror BL
 * SNA_API_CASHBOOK_SETUPBANKMAIN.
 */
class LookupBankMain extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'lookup_bank_main';

    protected $primaryKey = 'lbm_bank_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'lbm_bank_code',
        'lbm_bank_name',
        'isBankMain',
        'lbm_status',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];
}
