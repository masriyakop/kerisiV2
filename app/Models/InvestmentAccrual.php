<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS investment_accrual (DB_SECOND_DATABASE).
 *
 * Fillable list derived from legacy BL `API_INVESTMENT_ACCRUAL` and
 * `INSERT_UPDATE_INVESTMENT_ACCRUAL`. Only columns touched by the
 * migrated read path (Investment / Accrual — MENUID 1446) and the
 * soon-to-be-migrated Post-to-TB write flow are declared; extend
 * cautiously once additional flows land.
 */
class InvestmentAccrual extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'investment_accrual';

    protected $primaryKey = 'iac_id';

    public $timestamps = false;

    protected $fillable = [
        'iac_id',
        'ipf_investment_no',
        'iac_start_date',
        'iac_end_date',
        'iac_amount',
        'ipf_rate',
        'ipf_amt_per_day',
        'ipf_no_of_days',
        'pmt_posting_no',
        'pmt_posteddate',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'iac_start_date' => 'date',
            'iac_end_date' => 'date',
            'pmt_posteddate' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
            'iac_amount' => 'decimal:2',
            'ipf_amt_per_day' => 'decimal:4',
            'ipf_rate' => 'decimal:4',
            'ipf_no_of_days' => 'integer',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(
            InvestmentProfile::class,
            'ipf_investment_no',
            'ipf_investment_no',
        );
    }
}
