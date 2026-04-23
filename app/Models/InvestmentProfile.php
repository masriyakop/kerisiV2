<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FIMS investment_profile (DB_SECOND_DATABASE).
 *
 * Fillable list and casts derived from legacy BL
 * `API_LIST_OF_ACCRUAL` / `API_LIST_OF_NEW_INVESTMENT` which read and
 * update this table. Only columns actually touched by the migrated
 * read paths are declared; extend cautiously as write flows land.
 */
class InvestmentProfile extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'investment_profile';

    protected $primaryKey = 'ipf_investment_id';

    public $timestamps = false;

    protected $fillable = [
        'ipf_investment_id',
        'ipf_batch_no',
        'iit_inst_code',
        'ipf_investment_no',
        'ipf_certifcate_no',
        'ipf_tenure_code',
        'ipf_estimated_period',
        'ipf_start_date',
        'ipf_end_date',
        'ipf_principal_amt',
        'ipf_rate',
        'ipf_receipt_withdraw',
        'ipf_receipt_date_withdraw',
        'ipf_status',
        'ipf_extended_field',
        // Classification columns surfaced by the Summary List page
        // (MENUID 2808) and used for both display and smart filters.
        'ivt_type_code',
        'fty_fund_type',
        'at_activity_code',
        // Columns surfaced by the List of Investments page (MENUID 1448):
        // - apply_date for audit context, withdrawal_date to compute the
        //   "Withdrawal Type" column (PREMATURED / UPON MATURITY),
        // - mjm_journal_no to join manual_journal_master for Journal
        //   No/Status column, ipf_ref_investment_no for the cancel flow.
        'ipf_apply_date',
        'ipf_withdrawal_date',
        'mjm_journal_no',
        'ipf_ref_investment_no',
        // Columns written by the Investment to be Withdrawn page
        // (MENUID 3485). `ipf_status_withdraw` flips to 'APPROVE' when a
        // user marks an investment as withdrawn; `ipf_batch_no_wdraw`
        // tags the batch used (legacy sets it to the literal 'SYSTEM').
        'ipf_status_withdraw',
        'ipf_batch_no_wdraw',
        // Downstream doc links (voucher / bills). Kept for traceability;
        // the Summary List page does not display them.
        'vma_voucher_no',
        'bim_bills_no',
        'ipf_remark_cancel',
        'ipf_cancel_date',
        'ipf_cancel_by',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'ipf_start_date' => 'date',
            'ipf_end_date' => 'date',
            'ipf_apply_date' => 'date',
            'ipf_withdrawal_date' => 'date',
            'ipf_receipt_date_withdraw' => 'date',
            'ipf_cancel_date' => 'datetime',
            'ipf_principal_amt' => 'decimal:2',
            'ipf_rate' => 'decimal:4',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(InvestmentInstitution::class, 'iit_inst_code', 'iit_inst_code');
    }
}
