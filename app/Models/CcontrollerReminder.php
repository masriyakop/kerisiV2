<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `ccontroller_reminder` — individual reminder notice sent to a debtor /
 * creditor, scoped by the master row (`cm_id`). Used by the Debtor Portal
 * reminder listing (MENUID 2584) and by legacy credit-control reports.
 */
class CcontrollerReminder extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';
    protected $table = 'ccontroller_reminder';
    protected $primaryKey = 'crm_id';
    public $timestamps = false;

    protected $fillable = [
        'cm_id', 'crm_debtor_id', 'crm_debtor_name', 'crm_invoice_no',
        'crm_amount_inv', 'crm_amt_outstanding', 'crm_reminder_bil',
        'crm_reminder_date', 'crm_address_type', 'crm_address1',
        'crm_address2', 'crm_address3', 'crm_pcode', 'crm_city',
        'crm_state', 'crm_country', 'crm_handphone_no', 'crm_email_addr',
        'crm_reference', 'crm_notification_methd', 'crm_extended_field',
        'crm_confirm_status', 'crm_notification_methodold',
        'crm_sent_date', 'crm_sent_by', 'crm_remark',
        'crm_amount_salarydeduct',
        'createddate', 'createdby', 'updateddate', 'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'crm_reminder_date' => 'date',
            'crm_sent_date' => 'datetime',
            'crm_extended_field' => 'array',
            'crm_amount_salarydeduct' => 'decimal:2',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(CcontrollerMaster::class, 'cm_id', 'cm_id');
    }
}
