<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CostCentre extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'costcentre';

    protected $primaryKey = 'ccr_costcentre_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ccr_costcentre_id',
        'ccr_costcentre',
        'ccr_costcentre_desc',
        'ccr_costcentre_desc_eng',
        'oun_code',
        'ccr_address',
        'ccr_hostel_code',
        'ccr_status',
        'ccr_flag_salary',
        'createddate',
        'updateddate',
        'createdby',
        'updatedby',
    ];
}
