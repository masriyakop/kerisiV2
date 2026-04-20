<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Yearly quarter-calendar rows used by the Initial / Closing flows.
 *
 * Legacy BL references (see docs/migration/fims-budget/PAGE_1264.json smart
 * filter and TRIGGER_1953.json process flow):
 *   SELECT DISTINCT qbu_year, qbu_quarter_id, qbu_description, qbu_start_date,
 *                   qbu_end_date FROM fims_usr.quarter_budget
 *
 * Kept read-only by the controllers — this table is maintained by the
 * Budget Planning Schedule / Closing processes in the legacy application.
 */
class QuarterBudget extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'quarter_budget';

    protected $primaryKey = 'qbu_quarter_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'qbu_quarter_id',
        'qbu_year',
        'qbu_description',
        'qbu_start_date',
        'qbu_end_date',
        'qbu_status',
    ];

    protected function casts(): array
    {
        return [
            'qbu_start_date' => 'date',
            'qbu_end_date' => 'date',
        ];
    }
}
