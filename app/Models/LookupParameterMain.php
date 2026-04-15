<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LookupParameterMain extends Model
{
    protected $connection = 'mysql_secondary';

    protected $table = 'lookup_parameter_main';

    protected $primaryKey = 'lpm_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;
}
