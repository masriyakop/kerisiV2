<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS petty_cash_master (applications). Used by List of Petty Cash Application
 * (PAGEID 1217 / MENUID 1490).
 */
class PettyCashMaster extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'petty_cash_master';

    protected $primaryKey = 'pms_id';

    public $timestamps = false;

    protected $fillable = [
        'pms_id',
        'pms_application_no',
        'pms_request_by',
        'pms_request_by_desc',
        'pms_pay_to_id',
        'pms_request_date',
        'pms_total_amt',
        'pms_return_amt',
        'pms_status',
        'pms_extended_field',
        'createdby',
        'createddate',
        'updatedby',
        'updateddate',
    ];

    protected function casts(): array
    {
        return [
            'pms_request_date' => 'datetime',
            'pms_extended_field' => 'array',
        ];
    }
}
