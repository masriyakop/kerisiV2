<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'activity_type';

    protected $primaryKey = 'at_activity_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'at_activity_id',
        'activity_group_code',
        'activity_subgroup_code',
        'activity_subsiri_code',
        'at_activity_code',
        'at_activity_description_bm',
        'at_activity_description_en',
        'at_status',
        'createddate',
        'updateddate',
    ];
}
