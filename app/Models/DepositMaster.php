<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FIMS `deposit_master` — Credit Control deposit headers.
 *
 * Source BLs: ZR_CREDITCONTROL_DEPOSIT_API (MENUID 1809 list),
 * SNA_API_CC_LISTOFDEPOSIT (MENUID 3066 list), NAD_API_CC_DEPOSIT_DETAILS
 * (MENUID 3397 detail + inline edit). All listings join `deposit_details`
 * and `deposit_category`; MENUID 3066/3397 additionally filter on
 * `account_main.acm_flag_subsidiary='Y' AND acm_flag_deposit='Y'`.
 */
class DepositMaster extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'deposit_master';

    protected $primaryKey = 'dpm_deposit_master_id';

    public $timestamps = false;

    protected $fillable = [
        'dpm_deposit_no',
        'dpm_deposit_category',
        'vcs_vendor_code',
        'dpm_contract_no',
        'dpm_start_date',
        'dpm_end_date',
        'dpm_registration_no',
        'dpm_status',
        'dpm_vendor_name',
        'ccr_costcentre',
        'fty_fund_type',
        'dpm_payto_type',
        'dpm_ref_no',
        'dpm_ref_no_note',
        'dpm_extended_field',
        'dpm_source',
        'dpm_sponsor_code',
        'dpm_sponsor_deposit_ref_no',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'dpm_start_date' => 'datetime',
            'dpm_end_date' => 'datetime',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
            'dpm_extended_field' => 'array',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(DepositDetails::class, 'dpm_deposit_master_id', 'dpm_deposit_master_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DepositCategory::class, 'dpm_deposit_category', 'dct_category_code');
    }
}
