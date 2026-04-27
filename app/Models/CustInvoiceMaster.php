<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS cust_invoice_master — customer invoices. Used by:
 *  - AR > Debtor to compute the outstanding balance sub-query
 *    (SUM(cim_bal_amt) WHERE cim_status='APPROVE' AND cim_cust_id=debtor
 *     AND cim_bal_amt > 0 AND cim_system_id IN ('AR_INV')).
 *  - Student Finance > Manual Invoice Listing (PAGEID 2389 / MENUID 2897)
 *    where rows are scoped to cim_system_id='STUD_INV' AND cim_invoice_type='12'
 *    (legacy BL DT_SF_MANUAL_INV_LISTING).
 * DB_SECOND_DATABASE.
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
        'cim_cust_name',
        'cim_cust_type',
        'cim_semester_id',
        'cim_invoice_type',
        'cim_bal_amt',
        'cim_total_amt',
        'cim_crnote_amt',
        'cim_dnnote_amt',
        'cim_dcnote_amt',
        'cim_paid_amt',
        'cim_status',
        'cim_system_id',
        'cim_invoice_date',
        'cim_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'cim_invoice_date' => 'datetime',
            'cim_total_amt' => 'decimal:2',
            'cim_bal_amt' => 'decimal:2',
            'cim_crnote_amt' => 'decimal:2',
            'cim_dnnote_amt' => 'decimal:2',
            'cim_dcnote_amt' => 'decimal:2',
            'cim_paid_amt' => 'decimal:2',
        ];
    }
}
