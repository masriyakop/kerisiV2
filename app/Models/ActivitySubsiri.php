<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivitySubsiri extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'activity_subsiri';

    protected $primaryKey = 'activity_subsiri_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'activity_group',
        'activity_subgroup_code',
        'activity_subsiri_code',
        'activity_subsiri_desc',
        'activity_subsiri_desc_eng',
        'createddate',
        'updateddate',
    ];
}
