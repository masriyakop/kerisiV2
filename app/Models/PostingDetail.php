<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS posting_details (General Ledger > Posting to GL (TB) / General
 * Ledger Listing). Lives in DB_SECOND_DATABASE.
 *
 * Legacy BLs: `POSTING_TO_TB` (DR/CR drilldowns) and
 * `ZR_GL_LISTINGPOSTINGTOGL_BL` / `NAD_API_GL_LISTINGPOSTINGTOGL`
 * (line-level listing joined with lookup tables).
 */
class PostingDetail extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'posting_details';

    protected $primaryKey = 'pde_posting_detl_id';

    public $timestamps = false;

    protected $fillable = [
        'pmt_posting_id',
        'oun_code',
        'fty_fund_type',
        'at_activity_code',
        'ccr_costcentre',
        'acm_acct_code',
        'pde_ent_amt',
        'cpa_project_no',
        'pde_document_no',
        'pde_reference',
        'pde_reference1',
        'pde_reference2',
        'pde_item_lineno',
        'pde_trans_type',
        'pde_trans_amt',
        'pde_trans_amt_enter',
        'pde_trans_date',
        'pde_taxcode',
        'pde_taxpct',
        'pde_taxamt',
        'pde_payto_type',
        'pde_payto_id',
        'pde_payto_name',
        'pde_status',
        'pde_batch_no',
        'pde_extended_field',
        'pde_doc_description',
        'cascading_old',
        'acm_acct_code_old',
        'cpa_project_no_old',
        'pde_structure_budget_id',
        'pde_item_code',
        'pde_item_category',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'pde_posting_detl_id' => 'integer',
            'pmt_posting_id' => 'integer',
            'pde_item_lineno' => 'integer',
            'pde_trans_amt' => 'decimal:2',
            'pde_trans_amt_enter' => 'decimal:2',
            'pde_ent_amt' => 'decimal:4',
            'pde_taxpct' => 'decimal:2',
            'pde_taxamt' => 'decimal:2',
            'pde_trans_date' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
            'pde_extended_field' => 'array',
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(PostingMaster::class, 'pmt_posting_id', 'pmt_posting_id');
    }
}
