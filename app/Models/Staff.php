<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS staff master (read-only).
 *
 * Used as a lookup by PAGEID 1715 (HOD, VC & TNC) and PAGEID 2664
 * (Setup Carian Structure Budget) — the Kerisi export never wrote to this
 * table. Kept minimal (read-only, no fillable) to avoid accidentally
 * persisting to it from the CMS.
 */
class Staff extends Model
{
    use HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'staff';

    protected $primaryKey = 'stf_staff_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;
}
