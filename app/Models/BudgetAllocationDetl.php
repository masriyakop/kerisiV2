<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy `budget_allocation_detl` table — line items under a
 * `BudgetAllocationMaster` (one master → many detail rows).
 *
 * Only modelled to support the Initial V2 editor/view pages when they are
 * migrated later; the list endpoint does not use this relationship yet.
 */
class BudgetAllocationDetl extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'budget_allocation_detl';

    protected $primaryKey = 'bad_detl_id';

    public $timestamps = false;

    protected $fillable = [
        'bad_master_id',
        'bad_sbg_id',
        'oun_code',
        'fty_fund_type',
        'at_activity_code',
        'ccr_costcentre',
        'cpa_project_no',
        'budget_code',
        'initial_amt',
        'bdg_budget_id',
        'bad_extended_field',
        'bad_status',
        'cascading_old',
        'cpa_project_no_old',
        'budget_code_old',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'initial_amt' => 'decimal:2',
            'bad_extended_field' => 'array',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocationMaster::class, 'bad_master_id', 'bam_id');
    }
}
