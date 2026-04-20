<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy FIMS table: structure_budget (kerisiv2 schema).
 *
 * Represents the budget tree — one row per (year × ptj × costcentre × fund × activity)
 * slot. Referenced by budget_movement_detl via sbg_budget_id.
 *
 * Only the columns that surface in the legacy Budget list/monitoring BL are mapped
 * here; the real table has many more attributes (e.g. sbg_budget_amt, etc.) that can
 * be added as fillable entries when the corresponding Budget screens land.
 */
class StructureBudget extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'structure_budget';

    protected $primaryKey = 'sbg_budget_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'sbg_budget_id',
        'oun_code',
        'ccr_costcentre',
        'fty_fund_type',
        'at_activity_code',
        'lbc_budget_code',
    ];
}
