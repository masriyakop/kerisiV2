<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS cashbook_details_recon (Cashbook > List Of CashBook Daily/Monthly,
 * PAGEID 1397/2024 / MENUID 1702/2471). Lives in DB_SECOND_DATABASE; columns
 * derived from BL NF_BL_CC_CASHBOOK. Read-only — the migrated UI does not
 * expose any mutating actions.
 */
class CashbookDetailsRecon extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'cashbook_details_recon';

    protected $primaryKey = 'cbk_ref_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cbk_ref_id',
        'cbk_type',
        'cbk_trans_period',
        'cbk_trans_ref',
        'cbk_trans_date',
        'cbk_debit_amt',
        'cbk_credit_amt',
        'cbk_payto_id',
        'cbk_payto_name',
        'cbk_recon_status',
        'cbk_recon_flag',
        'cbk_subsystem_id',
        'acm_acct_code_bank',
    ];

    protected function casts(): array
    {
        return [
            'cbk_trans_date' => 'date',
        ];
    }
}
