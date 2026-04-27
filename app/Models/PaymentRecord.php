<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS payment_record (vendor payments / EP / cheque ledger). Used by the
 * Vendor Portal > Financial Status page (PAGEID 1714 / MENUID 2072) for
 * the "paymentInfo" datatable and joined against voucher_master /
 * voucher_details. Lives in DB_SECOND_DATABASE.
 *
 * Source: legacy BL `NF_BL_PURCHASING_FINANCIAL_STATUS`.
 */
class PaymentRecord extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'payment_record';

    protected $primaryKey = 'pre_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'pre_id',
        'pre_voucher_no',
        'pre_payment_no',
        'pre_mod_type',
        'pre_total_amt_rm',
        'pre_sign_date',
        'pre_bankin_date',
        'pre_collect_mode',
        'pre_status',
        'pre_payto_id',
    ];
}
