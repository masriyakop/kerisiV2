<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Budget ledger row on the secondary FIMS database.
 *
 * One row per (sbg_budget_id, bdg_year) combination; the joined
 * structure_budget row supplies the fund / activity / PTJ / cost-centre /
 * budget-code dimensions.
 *
 * Fields are populated by legacy stored procedures (update_budget, etc.)
 * and by the Initial / Increment / Decrement / Virement / Closing flows,
 * so there is no "create" path from this application today. The model is
 * only used for read queries by BudgetMonitoringController.
 */
class Budget extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'budget';

    protected $primaryKey = 'bdg_budget_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'bdg_budget_id',
        'sbg_budget_id',
        'bdg_year',
        'bdg_status',
        'bdg_bal_carryforward',
        'bdg_topup_amt',
        'bdg_initial_amt',
        'bdg_additional_amt',
        'bdg_virement_amt',
        'bdg_allocated_amt',
        'bdg_lock_amt',
        'bdg_pre_request_amt',
        'bdg_request_amt',
        'bdg_commit_amt',
        'bdg_expenses_amt',
        'bdg_balance_amt',
        'bdg_closing',
        'bdg_closing_by',
        'bdg_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'bdg_bal_carryforward' => 'decimal:2',
            'bdg_topup_amt' => 'decimal:2',
            'bdg_initial_amt' => 'decimal:2',
            'bdg_additional_amt' => 'decimal:2',
            'bdg_virement_amt' => 'decimal:2',
            'bdg_allocated_amt' => 'decimal:2',
            'bdg_lock_amt' => 'decimal:2',
            'bdg_pre_request_amt' => 'decimal:2',
            'bdg_request_amt' => 'decimal:2',
            'bdg_commit_amt' => 'decimal:2',
            'bdg_expenses_amt' => 'decimal:2',
            'bdg_balance_amt' => 'decimal:2',
            'bdg_extended_field' => 'array',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureBudget::class, 'sbg_budget_id', 'sbg_budget_id');
    }
}
