<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `credit_note_details` — line items for AR Credit Notes. One master
 * has many detail rows split by `cnd_transaction_type` ('CR' or 'DT').
 *
 * Source: BL `DT_AR_CREDIT_NOTE_LIST` (delete cascade) +
 * `DT_AR_CREDIT_NOTE_FORM` (insert/replace on saveCrNote / submitCrNote).
 */
class CreditNoteDetails extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'credit_note_details';

    protected $primaryKey = 'cnd_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cnd_id',
        'cnm_credit_note_master_id',
        'cnd_line_no',
        'cnd_item_category',
        'cii_item_code',
        'cnd_detail_desc',
        'fty_fund_type',
        'at_activity_code',
        'oun_code',
        'ccr_costcentre',
        'cpa_project_no',
        'acm_acct_code',
        'cnd_taxcode',
        'cnd_invoice_amt',
        'cnd_crnote_amt',
        'cnd_cn_taxamt',
        'cnd_bal_amt',
        'cnd_status',
        'cnd_cust_invoice_detl_id',
        'cnd_transaction_type',
        'cnd_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
