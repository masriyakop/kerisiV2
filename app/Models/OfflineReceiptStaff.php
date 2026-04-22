<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS offline_receipt_staff — join table linking receipts to the issuing
 * staff. Used by AR > Cashbook PTJ listing (BL `MZ_BL_AR_CASHBOOK_LISTING`)
 * for the preprinted-receipt branch.
 */
class OfflineReceiptStaff extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'offline_receipt_staff';

    protected $primaryKey = 'ors_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ors_id',
        'ore_offline_receipt_id',
        'are_authorized_receipting_id',
        'ors_staff_id',
        'ors_contact_no',
        'ors_fax_no',
        'ors_email',
        'sts_jobcode',
        'sts_job_desc',
        'ors_position',
        'ors_position_desc',
        'ors_process_flag',
        'ors_reason',
        'ors_reference_no',
        'ors_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
