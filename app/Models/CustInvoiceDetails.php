<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `cust_invoice_details` — line items of a customer invoice. Used by
 * AR Credit Note / Debit Note forms to populate the "pick which invoice
 * lines this note applies to" tables (action=temp / action=tempCredit in
 * legacy BL). Columns mirror the legacy SELECT list.
 *
 * Sources: BL `DT_AR_CREDIT_NOTE_FORM` + `DT_AR_DEBIT_NOTE_FORM`.
 * DB_SECOND_DATABASE; read-only lookup from this module's perspective.
 */
class CustInvoiceDetails extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'cust_invoice_details';

    protected $primaryKey = 'cid_cust_invoice_detl_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cid_cust_invoice_detl_id',
        'cim_cust_invoice_id',
        'cii_item_category',
        'cii_item_code',
        'fty_fund_type',
        'at_activity_code',
        'oun_code',
        'ccr_costcentre',
        'cpa_project_no',
        'acm_acct_code',
        'cid_taxcode',
        'cid_taxamt',
        'cid_total_amt',
        'cid_crnote_amt',
        'cid_dnnote_amt',
        'cid_dcnote_amt',
        'cid_nett_amt',
        'cid_bal_amt',
        'cid_transaction_type',
        'cid_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
