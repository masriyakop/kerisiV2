<?php

namespace App\Models;

use App\Http\Controllers\Api\AuthorizedReceiptingFormController;
use App\Http\Controllers\Api\GeneralLedgerListingController;
use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `capital_project` master (DB_SECOND_DATABASE). Exposed on:
 *
 *   - Project Monitoring / List of Project    (MENUID 1544)
 *   - Project Monitoring / Updated Balance    (MENUID 2065)
 *
 * Columns used by the migrated list endpoints match those already
 * referenced elsewhere in this codebase
 * (see {@see AuthorizedReceiptingFormController::searchEvents}
 * and {@see GeneralLedgerListingController} for
 * `cp.*` joins). The `fty_fund_type`, `lat_activity_code`, `ccr_costcentre`,
 * `so_code` and `cpa_project_type` columns are denormalized onto
 * `capital_project` itself (see legacy BL `ANIS_LIST_OF_PROJECT`’s primary
 * SELECT) and are needed by the Updated Balance form (Information card).
 */
class CapitalProject extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'capital_project';

    protected $primaryKey = 'cpa_project_id';

    public $timestamps = false;

    protected $fillable = [
        'cpa_project_id',
        'cpa_project_no',
        'cpa_project_desc',
        'cpa_project_type',
        'fty_fund_type',
        'lat_activity_code',
        'oun_code',
        'ccr_costcentre',
        'so_code',
        'cpa_start_date',
        'cpa_end_date',
        'cpa_source',
        'cpa_project_status',
        'cpa_ytd_balance_amt',
        'updateddate',
        'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'cpa_project_id' => 'integer',
            'cpa_start_date' => 'datetime',
            'cpa_end_date' => 'datetime',
            'cpa_ytd_balance_amt' => 'decimal:2',
            'updateddate' => 'datetime',
        ];
    }
}
