<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS staff (HR master). Used by Account Bank by Payee — Staff variant.
 * DB_SECOND_DATABASE; columns derived from BL AS_BL_AP_ACCOUNTBANKBPAYEE.
 */
class Staff extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'staff';

    protected $primaryKey = 'stf_staff_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'stf_staff_id',
        'stf_staff_name',
        'stf_staff_status',
    ];
}
