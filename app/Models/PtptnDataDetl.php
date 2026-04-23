<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS ptptn_data_detl (line items for PTPTN Data — Student Finance /
 * PAGEID 857 / MENUID 1031). Lives in DB_SECOND_DATABASE. Columns verified
 * against the live `kerisiv2` schema. Used by the View modal so users can
 * inspect a file's parsed lines without navigating to a separate edit page.
 */
class PtptnDataDetl extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'ptptn_data_detl';

    protected $primaryKey = 'pdd_ptptn_data_detl_id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'pdm_ptptn_data_master_id',
        'pdd_student_status',
        'pdd_ptptn_file_no',
        'pdd_uni_code',
        'pdd_studentgrp',
        'pdd_warrant_no',
        'std_student_id',
        'pdd_student_ic',
        'pdd_student_name',
        'pdd_warrant_amt',
        'pdd_statusptptn',
        'pdd_deduction_amt',
        'pdd_balance_amt',
        'pdd_paydate',
        'pdd_warrantexpirydate',
        'pdd_bank_account_no',
        'pdd_invoice_amt',
        'pdd_invoice_no',
        'rbm_reference_no',
        'pdd_semester_id',
        'cim_sponsor_invoice_no',
        'pdd_row_data',
        'pdd_extended_field',
        'pdd_advance_amt',
        'pdd_credit_status',
    ];

    protected function casts(): array
    {
        return [
            'pdd_paydate' => 'date',
            'pdd_warrantexpirydate' => 'date',
            'pdd_warrant_amt' => 'decimal:2',
            'pdd_deduction_amt' => 'decimal:2',
            'pdd_balance_amt' => 'decimal:2',
            'pdd_invoice_amt' => 'decimal:2',
            'pdd_advance_amt' => 'decimal:2',
            'pdd_extended_field' => 'array',
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(
            PtptnDataMaster::class,
            'pdm_ptptn_data_master_id',
            'pdm_ptptn_data_master_id',
        );
    }
}
