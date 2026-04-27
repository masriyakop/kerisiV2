<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS `currency_master` (DB_SECOND_DATABASE) — list of currencies recognised
 * by the system. Migrated as part of:
 *   - Setup and Maintenance / Global / List of Currency (MENUID 3198).
 *
 * Legacy BL: `QLA_API_GLOBAL_LISTOFCURRENCY` (full CRUD).
 */
class CurrencyMaster extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'currency_master';

    protected $primaryKey = 'cym_currency_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cym_currency_id',
        'cym_currency_code',
        'cym_currency_desc',
        'cyd_unit',
        'cny_country_code',
        'cym_enabled',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'cym_currency_id' => 'integer',
            'cyd_unit' => 'decimal:4',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'cny_country_code', 'cny_country_code');
    }
}
