<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityGroup extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'activity_group';

    protected $primaryKey = 'activity_group_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'activity_group_code',
        'activity_group_desc',
        'activity_group_flag_kodso',
        'createddate',
        'updateddate',
    ];
}
