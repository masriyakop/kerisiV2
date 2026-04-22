<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS tender_master (Purchasing > Vendor Portal > Tender/Quotation List).
 * Lives in DB_SECOND_DATABASE; columns derived from BL
 * NF_BL_PURCHASING_VENDOR_PORTAL_TENDER (?ListOfTender=1) in PAGEID 2278.
 *
 * Fillable is limited to the safe projection used by the vendor-facing
 * listing — this model is read-only from the Portal module. Mutating code
 * in the purchasing module (if migrated later) can widen $fillable.
 */
class TenderMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'tender_master';

    protected $primaryKey = 'tdm_tender_id';

    public $timestamps = false;

    protected $fillable = [
        'tdm_tender_no',
        'tdm_tender_type',
        'tdm_tender_method',
        'tdm_title',
        'tdm_status',
        'tdm_start_date',
        'tdm_end_date',
        'tdm_briefing_ref_no',
        'tdm_briefing_start_peti',
        'tdm_briefing_close_peti',
        'tdm_tender_open_start',
        'tdm_tender_open_close',
        'tdm_estimated_amount',
        'tdm_amount_doc',
        'tdm_extended_field',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'tdm_start_date' => 'datetime',
            'tdm_end_date' => 'datetime',
            'tdm_briefing_start_peti' => 'datetime',
            'tdm_briefing_close_peti' => 'datetime',
            'tdm_tender_open_start' => 'datetime',
            'tdm_tender_open_close' => 'datetime',
            'tdm_estimated_amount' => 'decimal:2',
            'tdm_amount_doc' => 'decimal:2',
            'tdm_extended_field' => 'array',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }
}
