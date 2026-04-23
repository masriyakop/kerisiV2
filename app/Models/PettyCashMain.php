<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS petty_cash_main — holder / balance for petty cash accounts.
 */
class PettyCashMain extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'petty_cash_main';

    protected $primaryKey = 'pcm_id';

    public $timestamps = false;

    protected $fillable = [
        'pcm_balance',
        'pcm_holder_id',
    ];
}
