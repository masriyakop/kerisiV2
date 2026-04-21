<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Legacy FIMS table: budget_movement_master (kerisiv2 schema).
 *
 * Maps the header row of Budget Increment / Decrement / Virement applications.
 * The legacy PHP BL keys off `bmm_trans_type` ∈ {INCREMENT, DECREMENT, VIREMENT}.
 *
 * Tables / columns derived from:
 *   - docs/migration/fims-budget/PAGE_1273.json (Budget Increment BL)
 *   - docs/migration/fims-budget/PAGE_1274.json (Budget Decrement BL)
 *   - docs/migration/fims-budget/PAGE_1275.json (Budget Virement BL)
 */
class BudgetMovementMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'budget_movement_master';

    protected $primaryKey = 'bmm_budget_movement_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'bmm_budget_movement_id',
        'bmm_budget_movement_no',
        'bmm_year',
        'qbu_quarter_id',
        'bmm_trans_type',
        'bmm_movement_type',
        'bmm_total_amt',
        'bmm_status',
        'bmm_reason',
        'bmm_description',
        'bmm_endorse_doc',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'bmm_total_amt' => 'decimal:2',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(BudgetMovementDetl::class, 'bmm_budget_movement_id', 'bmm_budget_movement_id');
    }
}
