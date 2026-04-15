<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LookupDetail extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'lookup_details';

    protected $primaryKey = 'lde_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'lde_id',
        'lma_code_name',
        'lde_value',
        'lde_description',
        'lde_description2',
        'lde_sorting',
        'lde_status',
        'createddate',
        'updateddate',
    ];
}
