<?php

namespace App\Models;

use App\Http\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIMS lv_sequence_letter (issued sponsor letters per student per year).
 * Used by the Portal > List of Letter page (PAGEID 2330 / MENUID 2823)
 * to list a student's downloaded sponsor letters. Lives in
 * DB_SECOND_DATABASE.
 *
 * Source: legacy BL `IKA_LETTER_LIST_API` (?action=listHistory). The
 * page is read-only in this migration; the legacy `download` flow that
 * inserts new rows into `lv_sequence_letter` and renders sponsor PDFs is
 * out of scope (see SponsorLetterController doc comment).
 */
class LvSequenceLetter extends Model
{
    use Auditable, HasFactory;

    protected $connection = 'mysql_secondary';

    protected $table = 'lv_sequence_letter';

    protected $primaryKey = 'lvs_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'lvs_id',
        'lvs_no',
        'lvs_prefix_code',
        'lvs_matric_no',
        'lvs_action_date',
        'lvs_extended_field',
    ];
}
