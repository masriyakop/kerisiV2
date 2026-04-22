<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setup Budget Structure Search types (`setup_budget_structure_search`).
 *
 * Backs PAGEID 2664 / MENUID 3224. Each row represents a budget-structure
 * search variant (e.g. SEMISTRICT) and carries optional column/level
 * selections used by the Semi-Strict form on the same page.
 */
class SetupBudgetStructureSearch extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'setup_budget_structure_search';

    protected $primaryKey = 'sbss_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'sbss_id',
        'sbss_type',
        'sbss_status',
        'sbss_column_selection',
        'sbss_level_selection',
        'createdby',
        'updatedby',
        'createddate',
        'updateddate',
    ];
}
