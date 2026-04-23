<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS manual_journal_details. Lives in DB_SECOND_DATABASE.
 *
 * NOTE: `mjm_journal_no` on this table is an INT that stores the parent's
 * `mjm_journal_id` (not the varchar `mjm_journal_no` on the master). This
 * is how the legacy BL queries it (`WHERE mjm_journal_no = ?` bound to
 * `$masterid`), so we preserve the same foreign-key wiring here.
 *
 * `mjd_trans_type` is the DR/CR discriminator ('DT' or 'CR').
 */
class ManualJournalDetl extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'manual_journal_details';

    protected $primaryKey = 'mjd_journal_detl_id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'mjm_journal_no',
        'mjd_source',
        'mjd_reference',
        'fty_fund_type',
        'ft_fund_desc',
        'at_activity_code',
        'oun_code',
        'oun_desc',
        'ccr_costcentre',
        'ccr_costcentre_desc',
        'code_so',
        'acm_acct_code',
        'acm_acct_desc',
        'mjd_trans_amt',
        'mjd_trans_type',
        'mjd_document_no',
        'mjd_payto_type',
        'mjd_payto_id',
        'mjd_payto_name',
        'mjd_status',
        'cpa_project_no',
        'mjd_taxcode',
    ];

    protected function casts(): array
    {
        return [
            'mjd_trans_amt' => 'decimal:2',
            'mjd_total_payment' => 'decimal:2',
            'mjd_rounding' => 'decimal:2',
            'mjd_ent_amt' => 'decimal:4',
            'mjd_taxpct' => 'decimal:2',
            'mjd_taxamt' => 'decimal:2',
            'mjd_sponsored_amt' => 'decimal:2',
            'mjd_invoice_amt' => 'decimal:2',
            'mjd_paid_amt' => 'decimal:2',
            'mjd_trans_date' => 'datetime',
            'mjd_statement_date' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
            'mjd_extended_field' => 'array',
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(
            ManualJournalMaster::class,
            'mjm_journal_no',
            'mjm_journal_id',
        );
    }
}
