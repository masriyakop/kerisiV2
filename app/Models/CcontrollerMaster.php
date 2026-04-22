<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * `ccontroller_master` — legacy Credit Control reminder master row.
 * Each row represents a business document (invoice, loan, etc.) tracked
 * through the reminder workflow and is referenced from
 * `ccontroller_reminder` via `cm_id`.
 */
class CcontrollerMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';
    protected $table = 'ccontroller_master';
    protected $primaryKey = 'cm_id';
    public $timestamps = false;

    protected $fillable = [
        'cm_no', 'cm_type', 'cm_debtor_creditor', 'cm_business_type',
        'cm_status', 'cm_contact_person', 'cm_total_outstanding',
        'cm_duration_outstanding', 'cm_particular_outstanding',
        'cm_collected_amt', 'cm_extended_field',
        'createddate', 'createdby', 'updateddate', 'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'cm_total_outstanding' => 'decimal:2',
            'cm_particular_outstanding' => 'decimal:2',
            'cm_collected_amt' => 'decimal:2',
            'cm_extended_field' => 'array',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }
}
