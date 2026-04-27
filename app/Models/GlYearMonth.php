<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS gl_year_month (General Ledger > List of Year and Month —
 * PAGEID 2721 / MENUID 3287). Lives in DB_SECOND_DATABASE.
 *
 * Column + type names verified against the live schema. `gym_id` is
 * NOT auto-increment in the legacy MySQL definition — legacy BL uses
 * `getSeqNo('gl_year_month')` and explicitly sets the PK before insert,
 * so we mirror that behaviour from the controller (see `store`).
 *
 * Legacy BL: `MZ_BL_GL_LIST_YEAR_MONTH` with endpoints `dtListing`,
 * `save` (upsert via `expressDML`), `viewDetails`, `download` (CSV/PDF).
 * No delete endpoint exists on the BL.
 */
class GlYearMonth extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'gl_year_month';

    protected $primaryKey = 'gym_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'gym_id',
        'gym_year',
        'gym_month',
        'gym_status',
        'gym_remark',
        'gym_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'gym_id' => 'integer',
            'gym_extended_field' => 'array',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }
}
