<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS workflow process master (read-only).
 *
 * Used by PAGEID 2664 / MENUID 3224 to populate the "Sequence Selected"
 * dropdown on the Bill Setup Custom WF Posting form. The Kerisi export
 * only reads from this table; keeping it lookup-only avoids accidental
 * writes from the CMS.
 */
class WfProcess extends Model
{
    use HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'wf_process';

    protected $primaryKey = 'wfp_id';

    public $incrementing = false;

    public $timestamps = false;
}
