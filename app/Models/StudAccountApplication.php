<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS stud_account_application — student bank account application requests.
 * Used by Student Finance > Bank Account Update (PAGEID 977 / MENUID 1081,
 * legacy BL DT_BANK_ACC_UPDATE). DB_SECOND_DATABASE.
 *
 * Columns verified against the legacy SQL:
 *   saa_application_id (PK), saa_application_no, std_student_id,
 *   bnm_bank_code, saa_bank_acc_no, saa_apply_date, saa_approved_date,
 *   saa_status ('APPROVE' is displayed as 'APPROVED').
 */
class StudAccountApplication extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'stud_account_application';

    protected $primaryKey = 'saa_application_id';

    public $timestamps = false;

    protected $fillable = [
        'saa_application_id',
        'saa_application_no',
        'std_student_id',
        'bnm_bank_code',
        'saa_bank_acc_no',
        'saa_apply_date',
        'saa_approved_date',
        'saa_status',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'saa_apply_date' => 'datetime',
            'saa_approved_date' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'std_student_id', 'std_student_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankMaster::class, 'bnm_bank_code', 'bnm_bank_code');
    }
}
