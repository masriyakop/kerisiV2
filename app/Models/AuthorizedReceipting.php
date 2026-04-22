<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `authorized_receipting` — applications that authorize a staff member
 * to issue official receipts for a given PTJ / event / position during a
 * date range. Status transitions: DRAFT → ENTRY → ENDORSED → APPROVE (or
 * RETURN back to DRAFT).
 *
 * Source: BL `V2_AUTHORIZED_RECEIPTING_API` (list + delete) and
 * `V2_AUTHORIZED_RECEIPTING_FORM_API` (details + workflow submit).
 * DB_SECOND_DATABASE.
 */
class AuthorizedReceipting extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'authorized_receipting';

    protected $primaryKey = 'are_authorized_receipting_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'are_authorized_receipting_id',
        'are_application_no',
        'stf_staff_id',
        'oun_code_ptj',
        'are_position_code',
        'are_event_code',
        'are_reason',
        'are_purposed_code',
        'are_employment_code',
        'are_duration_from',
        'are_duration_to',
        'are_status',
        'are_receipt_type',
        'are_counter_no',
        'are_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
