<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS `deposit_category` — code table used by the Credit Control Deposit
 * filter (Deposit Category dropdown) and joined on `dpm_deposit_category` in
 * the MENUID 1809 listing BL.
 */
class DepositCategory extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'deposit_category';

    protected $primaryKey = 'dct_category_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'dct_category_code',
        'dct_category_desc',
        'dct_status',
        'dct_extended_field',
        'createddate',
        'createdby',
        'updateddate',
        'updatedby',
    ];

    protected function casts(): array
    {
        return [
            'dct_extended_field' => 'array',
            'createddate' => 'datetime',
            'updateddate' => 'datetime',
        ];
    }
}
