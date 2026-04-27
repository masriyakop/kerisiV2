<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS `int_activity_type` (DB_SECOND_DATABASE) — staging table for activity
 * codes received from the integration source. The legacy listing is filtered
 * to records that have not yet been promoted into the production
 * `activity_type` table (`at_activity_code IS NULL`).
 *
 * Migrated as part of:
 *   - Setup and Maintenance / Integration / Integration - Activity
 *     (MENUID 2444).
 *
 * Legacy BL: `SNA_API_SM_INTEGRATION_ACTIVITY` (datatable + read-only modal).
 */
class IntActivityType extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'int_activity_type';

    protected $primaryKey = 'iat_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'iat_id',
        'iat_activity_code',
        'iat_activity_description_bm',
        'iat_activity_code_parent',
        'iat_activity_group_code',
        'iat_activity_subgroup_code',
        'iat_activity_subsiri_code',
        'iat_status',
        'iat_source',
        'iat_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'iat_id' => 'integer',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'iat_activity_code', 'at_activity_code');
    }
}
