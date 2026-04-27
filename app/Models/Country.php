<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `country` (DB_SECOND_DATABASE) reference table. Used by:
 *   - List of Currency (MENUID 3198) — country code dropdown.
 */
class Country extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'country';

    protected $primaryKey = 'cny_country_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cny_country_code',
        'cny_country_desc',
        'cny_status',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }
}
