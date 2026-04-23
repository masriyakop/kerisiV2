<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FIMS ptptn_data_master (Student Finance > PTPTN Data — PAGEID 857 /
 * MENUID 1031). Lives in DB_SECOND_DATABASE. Columns verified against the
 * live `kerisiv2` schema; legacy dt_keys map as follows:
 *
 *   dt_refNo        -> pdm_reference_no
 *   dt_date         -> pdm_date
 *   dt_fileName     -> pdm_file_name
 *   source          -> ptptn_source
 *   dt_totalStudent -> pdm_total_stud
 *   dt_totalWarrant -> pdm_warrant_amt
 *   dt_deductAmt    -> pdm_deduction_amt
 *   dt_balanceAmt   -> pdm_balance_amt
 *   isProcessed     -> pdm_is_process_complete  (legacy JS uses this to
 *                                                enable/disable the Delete
 *                                                button — only 'N' rows are
 *                                                deletable)
 */
class PtptnDataMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'ptptn_data_master';

    protected $primaryKey = 'pdm_ptptn_data_master_id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'pdm_reference_no',
        'pdm_date',
        'pdm_file_name',
        'pdm_total_stud',
        'pdm_warrant_amt',
        'pdm_deduction_amt',
        'pdm_balance_amt',
        'pdm_is_process_complete',
        'pdm_is_inv_gen_complete',
        'pdm_is_export_complete',
        'pdm_extended_field',
        'ptptn_source',
        'pdm_is_cerdited',
    ];

    protected function casts(): array
    {
        return [
            'pdm_date' => 'datetime',
            'pdm_warrant_amt' => 'decimal:2',
            'pdm_deduction_amt' => 'decimal:2',
            'pdm_balance_amt' => 'decimal:2',
            'pdm_extended_field' => 'array',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(
            PtptnDataDetl::class,
            'pdm_ptptn_data_master_id',
            'pdm_ptptn_data_master_id',
        );
    }
}
