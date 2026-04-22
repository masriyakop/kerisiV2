<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS `deposit_details` — per-line transaction rows under `deposit_master`.
 *
 * `ddt_type` is 'DT' (debit) or 'CR' (credit). Legacy lists surface signed
 * amounts — CR rows are negated in the SELECT. `ddt_line_no=1` represents the
 * master's own reference-note row (see if(dd.ddt_line_no not in (1),
 * ifnull(ddt_description, ddt_transaction_ref), dpm_ref_no_note) …).
 */
class DepositDetails extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'deposit_details';

    protected $primaryKey = 'ddt_deposit_detl_id';

    public $timestamps = false;

    protected $fillable = [
        'dpm_deposit_master_id',
        'ddt_line_no',
        'ddt_doc_no',
        'oun_code',
        'ccr_costcentre',
        'acm_acct_code',
        'ddt_amt',
        'ddt_tra_amt',
        'ddt_tra_complete',
        'ddt_type',
        'fty_fund_type',
        'at_activity_code',
        'cpa_project_no',
        'ddt_extended_field',
        'ddt_transaction_date',
        'ddt_transaction_ref',
        'ddt_description',
        'ddt_conversion_rate',
        'ddt_currency_unit',
        'ddt_currency_code',
        'ddt_rate_type',
        'ddt_ent_amt',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'ddt_amt' => 'decimal:2',
            'ddt_ent_amt' => 'decimal:4',
            'ddt_conversion_rate' => 'decimal:4',
            'ddt_currency_unit' => 'decimal:2',
            'ddt_transaction_date' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
            'ddt_extended_field' => 'array',
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(DepositMaster::class, 'dpm_deposit_master_id', 'dpm_deposit_master_id');
    }
}
