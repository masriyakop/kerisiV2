<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS student (Student master). Used by Account Bank by Payee — Student
 * variant. DB_SECOND_DATABASE; columns derived from BL
 * AS_BL_AP_ACCOUNTBANKBPAYEE.
 */
class Student extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'student';

    protected $primaryKey = 'std_student_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'std_student_id',
        'std_student_name',
        'std_status',
    ];
}
