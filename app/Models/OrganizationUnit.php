<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationUnit extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'organization_unit';

    protected $primaryKey = 'oun_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'oun_id',
        'oun_code',
        'oun_desc',
        'oun_desc_bi',
        'org_code',
        'org_desc',
        'oun_address',
        'oun_state',
        'st_staff_id_head',
        'st_staff_id_superior',
        'oun_tel_no',
        'oun_fax_no',
        'oun_code_parent',
        'oun_level',
        'oun_status',
        'tanggung_start_date',
        'tanggung_end_date',
        'oun_shortname',
        'oun_region',
        'cny_country_code',
        'createddate',
        'updateddate',
    ];
}
