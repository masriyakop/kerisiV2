<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS cust_invoice_master — customer invoices. Used by AR > Debtor to compute
 * the outstanding balance sub-query (SUM(cim_bal_amt) WHERE cim_status='APPROVE'
 * AND cim_cust_id=debtor AND cim_bal_amt > 0 AND cim_system_id IN ('AR_INV')).
 * DB_SECOND_DATABASE; only the columns the listing needs are declared here.
 */
class CustInvoiceMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'cust_invoice_master';

    protected $primaryKey = 'cim_cust_invoice_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cim_cust_invoice_id',
        'cim_invoice_no',
        'cim_cust_id',
        'cim_cust_type',
        'cim_bal_amt',
        'cim_total_amt',
        'cim_status',
        'cim_system_id',
        'cim_invoice_date',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
