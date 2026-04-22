<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LookupParameterMain extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'lookup_parameter_main';

    // The legacy `lookup_parameter_main` table has a composite natural key
    // (`lpm_code`, `lpm_value`) and no auto-increment ID column. Eloquent
    // single-column primary key is insufficient for update() on rows that
    // share the same `lpm_code` (e.g. every Letter Phrase row has
    // lpm_code = 'PHRASE'). Controllers must scope update() calls with
    // both columns via ->where(...)->where(...).
    protected $primaryKey = 'lpm_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'lpm_code',
        'lpm_value',
        'lpm_value_desc',
        'lpm_value_desc_bm',
        'lpm_remark',
        'lpm_status',
        'lpm_extended_field',
        'createdby',
        'updatedby',
        'createddate',
        'updateddate',
    ];
}
