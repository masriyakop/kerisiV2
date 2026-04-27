<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS `currency_details` (DB_SECOND_DATABASE) — daily/monthly exchange-rate
 * lines per currency. Migrated as part of:
 *   - Setup and Maintenance / Global / AG Rate (MENUID 3199).
 *
 * Legacy BL: `QLA_API_GLOBAL_UPLOADCURRENCY` (list grouped by year/month,
 * delete by year/month, manual entry, and CSV upload).
 */
class CurrencyDetail extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'currency_details';

    protected $primaryKey = 'cyd_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'cyd_id',
        'cym_currency_code',
        'cyd_year',
        'cyd_month',
        'cyd_start_date',
        'cyd_end_date',
        'cyd_exchange_type_code',
        'cyd_conversation_rate',
        'cyd_file_name',
        'cyd_unit',
        'cyd_status',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'cyd_id' => 'integer',
            'cyd_year' => 'integer',
            'cyd_month' => 'integer',
            'cyd_conversation_rate' => 'decimal:6',
            'cyd_unit' => 'decimal:4',
            'cyd_start_date' => 'datetime',
            'cyd_end_date' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(CurrencyMaster::class, 'cym_currency_code', 'cym_currency_code');
    }
}
