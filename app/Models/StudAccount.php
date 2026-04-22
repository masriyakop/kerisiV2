<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS stud_account (student bank accounts). Used by Account Bank by Payee.
 * DB_SECOND_DATABASE; columns derived from BL AS_BL_AP_ACCOUNTBANKBPAYEE.
 */
class StudAccount extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'stud_account';

    protected $primaryKey = 'sac_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'sac_id',
        'std_student_id',
        'sac_bank_code',
        'sac_bank_acc_no',
        'sac_status',
    ];
}
