<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS student (Student master). Used by:
 *  - Account Bank by Payee (Student variant) — BL AS_BL_AP_ACCOUNTBANKBPAYEE.
 *  - Student Finance > Student Profile or Ledger (PAGEID 1232 / MENUID 1509)
 *    — legacy BL V2_SFSP_LEDGER_API; depends on std_extended_field JSON
 *    fields (std_status_desc / std_program_level_desc).
 *  - Student Finance > Bank Account Update (PAGEID 977 / MENUID 1081) —
 *    joined with stud_account_application + bank_master + academic_calendar.
 * DB_SECOND_DATABASE.
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
        'std_ic_no',
        'std_passport',
        'std_current_sem',
        'std_sem_level',
        'std_program_level',
        'std_program',
        'std_faculty_code',
        'std_college_code',
        'std_gs_code',
        'std_study_center',
        'std_mode_study',
        'std_method_study',
        'std_citizenship_country',
        'std_citizenship_status',
        'std_intake_case',
        'std_outstanding_amt',
        'std_extended_field',
        'std_status',
    ];

    protected function casts(): array
    {
        return [
            'std_outstanding_amt' => 'decimal:2',
        ];
    }
}
