<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `discount_note_details` — line items for AR Discount Notes. Source:
 * BL idx-35 in ACCOUNT_RECEIVABLE_BL.json (delete cascade) +
 * `DT_AR_DISCOUNT_NOTE_FORM`.
 */
class DiscountNoteDetails extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'discount_note_details';

    protected $primaryKey = 'dcd_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'dcd_id',
        'dcm_discount_note_master_id',
        'dcd_line_no',
        'dcd_item_category',
        'cii_item_code',
        'dcd_detail_desc',
        'fty_fund_type',
        'at_activity_code',
        'oun_code',
        'ccr_costcentre',
        'cpa_project_no',
        'acm_acct_code',
        'dcd_taxcode',
        'dcd_taxamt',
        'dcd_invoice_amt',
        'dcd_invoice_line_no',
        'dcd_dcnote_amt',
        'dcd_dc_taxamt',
        'dcd_bal_amt',
        'dcd_status',
        'dcd_cust_invoice_detl_id',
        'dcd_transaction_type',
        'dcd_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
