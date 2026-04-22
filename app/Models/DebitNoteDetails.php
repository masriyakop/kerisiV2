<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `debit_note_details` — line items for AR Debit Notes. Source:
 * `DT_AR_DEBIT_NOTE_LIST` (delete cascade) + `DT_AR_DEBIT_NOTE_FORM`.
 */
class DebitNoteDetails extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'debit_note_details';

    protected $primaryKey = 'dnd_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'dnd_id',
        'dnm_debit_note_master_id',
        'dnd_line_no',
        'dnd_item_category',
        'cii_item_code',
        'cnd_detail_desc',
        'fty_fund_type',
        'at_activity_code',
        'oun_code',
        'ccr_costcentre',
        'cpa_project_no',
        'acm_acct_code',
        'dnd_taxcode',
        'dnd_invoice_amt',
        'dnd_dnnote_amt',
        'dnd_dn_taxamt',
        'dnd_bal_amt',
        'dnd_status',
        'dnd_cust_invoice_detl_id',
        'dnd_transaction_type',
        'dnd_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
