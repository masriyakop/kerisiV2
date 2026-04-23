<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS petty_cash_details — links petty_cash_batch to petty_cash_main.
 */
class PettyCashDetail extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'petty_cash_details';

    protected $primaryKey = 'pcd_id';

    public $timestamps = false;

    protected $fillable = [
        'pcd_id',
        'pms_application_no',
        'pcd_receipt_no',
        'pcd_trans_desc',
        'pcd_trans_amt',
        'pcd_status',
        'pcd_paid_date',
        'fty_fund_type',
        'at_activity_code',
        'oun_code',
        'ccr_costcentre',
        'cpa_project_no',
        'so_code',
        'acm_acct_code',
        'pcm_id',
        'pcb_batch_id',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    public function main(): BelongsTo
    {
        return $this->belongsTo(PettyCashMain::class, 'pcm_id', 'pcm_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PettyCashBatch::class, 'pcb_batch_id', 'pcb_batch_id');
    }
}
