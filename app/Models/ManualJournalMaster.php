<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FIMS manual_journal_master (General Ledger > Journal Listing —
 * PAGEID 1700 / MENUID 2056). Lives in DB_SECOND_DATABASE. Column names
 * and types verified against the live schema.
 *
 * Legacy details fan-out uses `manual_journal_details.mjm_journal_no`
 * (an int, confusingly named — it stores the master's `mjm_journal_id`),
 * which the `details()` relationship mirrors below.
 */
class ManualJournalMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'manual_journal_master';

    protected $primaryKey = 'mjm_journal_id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'mjm_journal_no',
        'mjm_journal_desc',
        'mjm_typeofjournal',
        'mjm_system_id',
        'mjm_total_amt',
        'mjm_enterdate',
        'mjm_enterby',
        'mjm_status',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
        'org_code',
    ];

    protected function casts(): array
    {
        return [
            'mjm_total_amt' => 'decimal:2',
            'mjm_ent_amt' => 'decimal:4',
            'mjm_conversion_rate' => 'decimal:4',
            'mjm_currency_unit' => 'decimal:2',
            'mjm_enterdate' => 'datetime',
            'mjm_approvedate' => 'datetime',
            'mjm_canceldate' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
            'mjm_extended_field' => 'array',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(
            ManualJournalDetl::class,
            'mjm_journal_no',
            'mjm_journal_id',
        );
    }
}
