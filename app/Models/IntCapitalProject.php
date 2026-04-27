<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `int_capital_project` (DB_SECOND_DATABASE) — staging mirror of
 * capital projects fed by the upstream integration source (HRMIS / Project
 * Profile system).
 *
 * Migrated as part of:
 *   - Setup and Maintenance / Integration / Integration - Profile
 *     (MENUID 2443).
 *
 * Legacy BL: `SNA_API_SM_INTEGRATION_PROFILE`. The list view is restricted
 * to records that have not yet been pushed to the production `capital_project`
 * table (`icp_send_date IS NULL`) and whose project status is anything other
 * than `OPEN`.
 */
class IntCapitalProject extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'int_capital_project';

    protected $primaryKey = 'icp_project_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'icp_project_id',
        'icp_project_no',
        'icp_subsystem_id',
        'subsystemcode',
        'fty_fund_type',
        'lat_activity_code',
        'ccr_costcentre',
        'oun_code',
        'icp_so_code',
        'icp_start_date',
        'icp_end_date',
        'icp_yearnum',
        'icp_project_type',
        'icp_project_desc',
        'icp_period',
        'icp_project_status',
        'icp_send_date',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'icp_project_id' => 'integer',
            'icp_yearnum' => 'integer',
            'icp_period' => 'integer',
            'icp_start_date' => 'datetime',
            'icp_end_date' => 'datetime',
            'icp_send_date' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }
}
