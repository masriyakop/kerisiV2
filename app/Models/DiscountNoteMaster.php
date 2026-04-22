<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FIMS `discount_note_master` — master row for AR Discount Notes.
 * `dcm_system_id` = 'AR_DC' identifies Discount Notes; `dcm_extended_field`
 * JSON mirrors coded columns.
 *
 * Source: BL idx-35 in ACCOUNT_RECEIVABLE_BL.json (list body; title column was
 * empty in the export but the code operates exclusively on discount_note_*
 * tables) + `DT_AR_DISCOUNT_NOTE_FORM`.
 */
class DiscountNoteMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'discount_note_master';

    protected $primaryKey = 'dcm_discount_note_master_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'dcm_discount_note_master_id',
        'dcm_dcnote_no',
        'cim_invoice_no',
        'dcm_cust_id',
        'dcm_cust_type',
        'dcm_cust_name',
        'dcm_dcnote_desc',
        'dcm_dcnote_date',
        'dcm_dc_total_amount',
        'dcm_status_dc',
        'dcm_status_cd',
        'dcm_system_id',
        'dcp_dc_policy_id',
        'dcm_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(DiscountNoteDetails::class, 'dcm_discount_note_master_id', 'dcm_discount_note_master_id');
    }
}
