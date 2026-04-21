<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountMainFund extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'account_main_fund';

    public $timestamps = false;

    protected $fillable = [
        'acm_acct_code',
        'fty_fund_type',
    ];
}
