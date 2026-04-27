<?php

namespace App\Http\Requests;

/**
 * Validation for POST /student-finance/invoice-generation/export/csv
 * and POST /student-finance/invoice-generation/export/match-csv.
 *
 * Both legacy export branches (`?csv=1` and `?match=1` on the BL
 * `CALL_PROC_STUDENT_INVOICE`) read from rows seeded into
 * `temp_stud_listing_match` by the previous `find=1` call, scoped by
 * the `unique_key` that was returned. Description fields are
 * accepted purely for the CSV header; the data set is driven by
 * `unique_key`.
 */
class ExportStudentInvoiceCsvRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unique_key' => 'required|string|max:64',
            'semester_desc' => 'nullable|string|max:200',
            'program_level_desc' => 'nullable|string|max:200',
            'student_type_desc' => 'nullable|string|max:200',
            'fee_type_desc' => 'nullable|string|max:200',
            'intake_case_desc' => 'nullable|string|max:200',
        ];
    }
}
