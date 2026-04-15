<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivitySubgroup extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'activity_subgroup';

    protected $primaryKey = 'activity_subgroup_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'activity_group_code',
        'activity_subgroup_code',
        'activity_subgroup_desc',
        'createddate',
        'updateddate',
    ];
}
