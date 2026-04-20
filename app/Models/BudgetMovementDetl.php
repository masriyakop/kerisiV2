<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy FIMS table: budget_movement_detl (kerisiv2 schema).
 *
 * One row per source / destination budget impacted by a movement header.
 * - Increment / Decrement write to sbg_budget_id_to only.
 * - Virement writes both sbg_budget_id_from (source) and sbg_budget_id_to (destination).
 */
class BudgetMovementDetl extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'budget_movement_detl';

    protected $primaryKey = 'bmd_bgt_movement_detl_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'bmd_bgt_movement_detl_id',
        'bmm_budget_movement_id',
        'sbg_budget_id_from',
        'sbg_budget_id_to',
        'qbu_quarter_id',
        'bmd_mvt_amt',
    ];

    protected function casts(): array
    {
        return [
            'bmd_mvt_amt' => 'decimal:2',
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(BudgetMovementMaster::class, 'bmm_budget_movement_id', 'bmm_budget_movement_id');
    }

    public function sourceBudget(): BelongsTo
    {
        return $this->belongsTo(StructureBudget::class, 'sbg_budget_id_from', 'sbg_budget_id');
    }

    public function destinationBudget(): BelongsTo
    {
        return $this->belongsTo(StructureBudget::class, 'sbg_budget_id_to', 'sbg_budget_id');
    }
}
