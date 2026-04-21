<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS vend_category (links a vend_customer_supplier to one or more category
 * codes such as 'G' (Other Payee) or 'U' (Utility)). DB_SECOND_DATABASE;
 * columns derived from BL NF_BL_AP_PAY_REGISTRATION +
 * SNA_API_AP_UTILITYREGISTRATION.
 */
class VendCategory extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'vend_category';

    protected $primaryKey = 'vc_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'vc_id',
        'vcs_vendor_code',
        'vc_category_code',
        'vc_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];
}
