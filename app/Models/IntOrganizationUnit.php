<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS `int_organization_unit` (DB_SECOND_DATABASE) — staging table that
 * mirrors org units from the integration source (HRMIS / SAP) before they
 * are promoted into the production `organization_unit` table.
 *
 * Migrated as part of:
 *   - Setup and Maintenance / Integration / Integration - PTJ (MENUID 2277).
 *
 * Legacy BL: `AS_BL_SM_INTEGRATIONPTJ` (datatable + popup-modal CRUD).
 */
class IntOrganizationUnit extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'int_organization_unit';

    protected $primaryKey = 'iou_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'iou_id',
        'iou_code',
        'iou_code_persis',
        'iou_desc',
        'iou_bursar_flag',
        'org_code',
        'org_desc',
        'iou_address',
        'iou_tel_no',
        'iou_fax_no',
        'iou_status',
        'iou_level',
        'iou_code_parent',
        'iou_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'iou_id' => 'integer',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'iou_code', 'oun_code');
    }
}
