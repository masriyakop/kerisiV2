<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `int_costcentre` (DB_SECOND_DATABASE) — staging table for cost centre
 * codes pulled from the integration source before they are promoted into the
 * production `costcentre` table.
 *
 * Migrated as part of:
 *   - Setup and Maintenance / Integration / Integration - Cost center
 *     (MENUID 2278).
 *
 * Legacy BL: `AS_BL_SM_INTEGRATIONCOSTCENTRE` (datatable + form CRUD).
 */
class IntCostCentre extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'int_costcentre';

    protected $primaryKey = 'ics_costcentre_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ics_costcentre_id',
        'ics_costcentre',
        'ics_costcentre_desc',
        'ics_hostel_code',
        'ics_status',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'ics_costcentre_id' => 'integer',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }
}
