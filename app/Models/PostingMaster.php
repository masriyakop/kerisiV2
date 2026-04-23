<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FIMS posting_master (General Ledger > Posting to GL (TB) —
 * PAGEID 1139 / MENUID 1409). Lives in DB_SECOND_DATABASE.
 *
 * Column names verified against the live schema. Referenced by legacy BL
 * `POSTING_TO_TB` (listing/master display/DR drilldown/CR drilldown) and
 * `ZR_GL_LISTINGPOSTINGTOGL_BL` (sibling listing for MENUID 2519).
 */
class PostingMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'posting_master';

    protected $primaryKey = 'pmt_posting_id';

    public $timestamps = false;

    protected $fillable = [
        'pmt_posting_no',
        'pmt_system_id',
        'pmt_posting_desc',
        'org_code',
        'cym_currency_code',
        'pmt_currency_unit',
        'pmt_total_amt',
        'pmt_total_amt_enter',
        'pmt_posteddate',
        'pmt_postedby',
        'ch_exchange_type_code',
        'ch_conversation_rate',
        'pmt_status',
        'pmt_page_id',
        'pmt_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'pmt_posting_id' => 'integer',
            'pmt_page_id' => 'integer',
            'pmt_total_amt' => 'decimal:2',
            'pmt_total_amt_enter' => 'decimal:2',
            'pmt_currency_unit' => 'decimal:2',
            'pmt_posteddate' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
            'pmt_extended_field' => 'array',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(PostingDetail::class, 'pmt_posting_id', 'pmt_posting_id');
    }
}
