<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Legacy `budget_allocation_master` table — rows shown on the Budget Initial
 * V2 datatable (PAGEID 1264 / MENUID 1541 / api/SWS_DT_BUDGET_INITIAL_V2).
 *
 * Column mapping used by the Initial V2 datatable (dt_key → column):
 *   ID          → bam_id
 *   YEARS       → bam_year
 *   DESCR       → quarter_budget.qbu_description (join via bam_quarter_id)
 *   ALLOCATE_NO → bam_allocation_no
 *   ENDORSE     → bam_endorse_doc
 *   AMT         → bam_total
 *   STAT        → bam_status_cd  (DRAFT / APPROVE / REJECT)
 *   date        → createddate
 */
class BudgetAllocationMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'budget_allocation_master';

    protected $primaryKey = 'bam_id';

    public $timestamps = false;

    protected $fillable = [
        'bam_allocation_no',
        'bam_year',
        'bam_quarter_id',
        'bam_endorse_doc',
        'bam_file_name',
        'bam_total',
        'bam_status_cd',
        'bam_extended_field',
        'bam_cancel_remark',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'bam_total' => 'decimal:2',
            'bam_extended_field' => 'array',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(QuarterBudget::class, 'bam_quarter_id', 'qbu_quarter_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(
            BudgetAllocationDetl::class,
            'bad_master_id',
            'bam_id',
        );
    }
}
