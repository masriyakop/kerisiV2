<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS staff_account (staff bank accounts). Used by Account Bank by Payee.
 * DB_SECOND_DATABASE; columns derived from BL AS_BL_AP_ACCOUNTBANKBPAYEE.
 */
class StaffAccount extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'staff_account';

    protected $primaryKey = 'sta_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'sta_id',
        'stf_staff_id',
        'sta_acct_code',
        'sta_acct_no',
        'sta_status',
        'sta_salary_bank',
    ];
}
