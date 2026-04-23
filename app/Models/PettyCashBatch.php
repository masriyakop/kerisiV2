<?php

namespace App\Models;

use App\Http\Controllers\Api\PettyCashRecoupController;
use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS petty_cash_batch (Petty Cash Recoup list, PAGEID 1255 / MENUID 1532).
 * {@see PettyCashRecoupController}
 */
class PettyCashBatch extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'petty_cash_batch';

    protected $primaryKey = 'pcb_id';

    public $timestamps = false;

    protected $fillable = [
        'pcb_batch_id',
        'pcb_trans_no',
        'pcb_batch_amt',
        'pcb_status',
        'vma_voucher_id',
        'pcb_balance_before',
        'pcb_receiveamt',
        'pcb_balance_inhand',
    ];
}
