<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrgUnitCostCentre extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'org_unit_costcentre';

    protected $primaryKey = 'ouc_ounit_costcentre_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ouc_ounit_costcentre_id',
        'fty_fund_type',
        'at_activity_code',
        'oun_code',
        'ccr_costcentre',
        'ouc_status',
        'createddate',
        'updateddate',
        'createdby',
        'updatedby',
    ];
}
