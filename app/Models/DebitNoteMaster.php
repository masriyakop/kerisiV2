<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FIMS `debit_note_master` — master row for AR Debit Notes. `dnm_system_id`
 * = 'AR_DN' identifies Debit Notes; JSON `dnm_extended_field` mirrors coded
 * columns (status, customer type) for display.
 *
 * Source: BL `DT_AR_DEBIT_NOTE_LIST` + `DT_AR_DEBIT_NOTE_FORM`.
 */
class DebitNoteMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'debit_note_master';

    protected $primaryKey = 'dnm_debit_note_master_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'dnm_debit_note_master_id',
        'dnm_dnnote_no',
        'cim_invoice_no',
        'dnm_cust_id',
        'dnm_cust_type',
        'dnm_cust_name',
        'dnm_dnnote_desc',
        'dnm_dnnote_date',
        'dnm_dn_total_amount',
        'dnm_status_cd',
        'dnm_system_id',
        'dnm_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(DebitNoteDetails::class, 'dnm_debit_note_master_id', 'dnm_debit_note_master_id');
    }
}
