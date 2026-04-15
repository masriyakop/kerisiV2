<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountMain extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'account_main';

    protected $primaryKey = 'acm_acct_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'acm_acct_code',
        'acm_acct_desc',
        'acm_acct_desc_eng',
        'acm_acct_activity',
        'acm_acct_status',
        'acm_acct_group',
        'acm_acct_level',
        'acm_acct_parent',
        'createddate',
        'updateddate',
    ];
}
