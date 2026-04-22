<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS online_payment (Vendor Portal > Online Registration Fee History).
 * Lives in DB_SECOND_DATABASE; schema confirmed via SHOW COLUMNS.
 *
 * The legacy BL `NF_BL_VENDOR_ONLINE_PAYMENT` is not present in the
 * available source JSON; this model reflects only the columns exposed
 * in that page's datatable spec + the commented-out SQL block embedded
 * inside BL NF_BL_PURCHASING_VENDOR_PORTAL_TENDER (PAGEID 2278). The
 * Portal listing is read-only — the mutating tokens (downloadReceipt /
 * confirmNotPending) and external payment-gateway re-query are out of
 * scope until that BL is supplied.
 */
class OnlinePayment extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'online_payment';

    protected $primaryKey = 'opa_online_payment_id';

    public $timestamps = false;

    protected $fillable = [
        'opa_reference_no',
        'opa_status',
        'opa_source_code',
        'opa_source',
        'opa_desc',
        'opa_amount',
        'opa_payment_id',
        'opa_extended_field',
        'opa_checkout_time',
        'opa_transaction_time',
        'opa_transaction_id',
        'opa_transaction_amount',
        'opa_payee_id',
        'opa_payee_type',
        'opa_payee_name',
        'opa_receipt_master_id',
        'opa_receipt_no',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'opa_amount' => 'decimal:2',
            'opa_checkout_time' => 'datetime',
            'opa_transaction_time' => 'datetime',
            'opa_extended_field' => 'array',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }
}
