<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `budget_transaction` (DB_SECOND_DATABASE) — ledger of approved
 * postings that have already been deducted against budget. Referenced by:
 *   - General Ledger / Budget Not Exists (MENUID 2657) — to identify
 *     postings whose `pde_document_no` does NOT yet exist in
 *     `budget_transaction.bgt_ref`.
 */
class BudgetTransaction extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'budget_transaction';

    protected $primaryKey = 'bgt_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'bgt_id',
        'bgt_ref',
        'bgt_amount',
        'bgt_trans_date',
        'bgt_status',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'bgt_id' => 'integer',
            'bgt_amount' => 'decimal:2',
            'bgt_trans_date' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }
}
