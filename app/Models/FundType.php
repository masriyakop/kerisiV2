<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundType extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'fund_type';

    protected $primaryKey = 'fty_fund_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'fty_fund_id',
        'fty_fund_type',
        'fty_fund_desc',
        'fty_fund_desc_eng',
        'fty_basis',
        'fty_status',
        'fty_remark',
        'fty_extended_field',
        'createdby',
        'updatedby',
    ];
}
