<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS bills setup configuration (`bills_setup`).
 *
 * Backs PAGEID 2664 / MENUID 3224 — the "Bill Setup" datatable and the
 * "Bill Setup Custom WF Posting" form that edits the `bis_sequence_level`
 * for the `CustomWF` row.
 */
class BillsSetup extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'bills_setup';

    protected $primaryKey = 'bis_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'bis_id',
        'bis_type',
        'bis_status',
        'bis_sequence_level',
        'createdby',
        'updatedby',
        'createddate',
        'updateddate',
    ];
}
