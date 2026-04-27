<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS academic_calendar — semester calendar used across Student Finance
 * screens. Joined against Student (`std_current_sem = acl_semester_code`)
 * by Bank Account Update (PAGEID 977 / MENUID 1081) to render the
 * "Current Semester" column as `(code) name`.
 *
 * DB_SECOND_DATABASE; column list derived from legacy BL
 * DT_BANK_ACC_UPDATE.
 */
class AcademicCalendar extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'academic_calendar';

    // The legacy table is keyed on acl_semester_code but the primary-key
    // column name isn't present in every installation; treat it as non-
    // incrementing string so Eloquent stays safe even if the PK is
    // actually a separate surrogate column.
    protected $primaryKey = 'acl_semester_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'acl_semester_code',
        'acl_semester_name',
        'acl_start_date',
        'acl_end_date',
    ];

    protected function casts(): array
    {
        return [
            'acl_start_date' => 'date',
            'acl_end_date' => 'date',
        ];
    }
}
