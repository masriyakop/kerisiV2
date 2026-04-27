<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS investment_type (DB_SECOND_DATABASE).
 *
 * Lookup table that investment_profile.ivt_type_code references. Used
 * by MENUID 1475 (Generate Schedule) to surface `ivt_description` on
 * the listing and by MENUID 2808 (Summary List) for the Investment
 * Type dropdown. Only the columns actually read by migrated pages are
 * declared fillable; add more as future flows land.
 */
class InvestmentType extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'mysql_secondary';

    protected $table = 'investment_type';

    protected $primaryKey = 'ivt_type_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ivt_type_code',
        'ivt_description',
    ];
}
