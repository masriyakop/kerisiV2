<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `offered_student` master (Student Finance > List of Offered —
 * PAGEID 2181 / MENUID 2636). Lives in DB_SECOND_DATABASE.
 *
 * Read-only listing source for the legacy BL `MZ_BL_SF_OFFEREDLIST`. The
 * legacy SQL joins this against `receipt_master`, `receipt_batch_detl`,
 * `receipt_batch_master` and `manual_journal_master` to reveal whichever
 * of the three payment channels (auto-receipt / batch knockoff /
 * manual-unidentified journal) actually settled the offer fee.
 *
 * Mass assignment is intentionally not exposed (no migrated write paths
 * touch this table); `$fillable` is left empty so accidental writes fail
 * loudly rather than corrupt legacy data.
 */
class OfferedStudent extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'offered_student';

    protected $primaryKey = 'ost_student_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [];
}
