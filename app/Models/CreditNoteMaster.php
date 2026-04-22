<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FIMS `credit_note_master` — master row for AR Credit Notes.
 *
 * Source: BL `DT_AR_CREDIT_NOTE_LIST` + `DT_AR_CREDIT_NOTE_FORM`. Notes carry a
 * `cnm_system_id` sentinel (='AR_CN' for this screen) alongside a JSON
 * `cnm_extended_field` holding descriptive mirrors of the coded columns
 * (status, customer type, invoice no etc.). DB_SECOND_DATABASE.
 */
class CreditNoteMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'credit_note_master';

    protected $primaryKey = 'cnm_credit_note_master_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cnm_credit_note_master_id',
        'cnm_crnote_no',
        'cim_invoice_no',
        'cnm_cust_id',
        'cnm_cust_type',
        'cnm_cust_name',
        'cnm_crnote_desc',
        'cnm_crnote_date',
        'cnm_cn_total_amount',
        'cnm_status_cd',
        'cnm_system_id',
        'cnm_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(CreditNoteDetails::class, 'cnm_credit_note_master_id', 'cnm_credit_note_master_id');
    }
}
