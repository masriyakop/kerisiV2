<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS vendor_address. Address lines attached to vend_customer_supplier by
 * vcs_vendor_code. Referenced by Account Receivable > Debtor delete cascade
 * (BL `DT_AR_DEBTOR` ?delete=1). DB_SECOND_DATABASE.
 */
class VendorAddress extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'vendor_address';

    protected $primaryKey = 'vdd_address_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'vdd_address_id',
        'vcs_vendor_code',
        'vdd_address_type',
        'vdd_address1',
        'vdd_address2',
        'vdd_address3',
        'vdd_pcode',
        'vdd_city',
        'vdd_state',
        'vdd_country',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
