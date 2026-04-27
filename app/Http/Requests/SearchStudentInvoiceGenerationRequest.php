<?php

namespace App\Http\Requests;

/**
 * Validation for POST /student-finance/invoice-generation/search.
 *
 * Mirrors the legacy `CALL_PROC_STUDENT_INVOICE?find=1` form payload from
 * MENUID 1231. The "Semester", "Program Level" and "Structure Type"
 * dropdowns carry the `required` flag in the legacy spec; the rest are
 * optional filters that simply append to the WHERE the SP builds
 * downstream against `temp_stud_listing_match`.
 *
 * Pagination + search inputs (`page`, `limit`, `q`, `sort_by`,
 * `sort_dir`) follow the same envelope as every other migrated CRUD
 * controller (per project conventions in CLAUDE.md).
 */
class SearchStudentInvoiceGenerationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'semester' => 'required|string|max:50',
            'program_level' => 'required|string|max:50',
            'fee_type' => 'required|string|max:50',
            'student_type' => 'nullable|string|max:50',
            'intake_case' => 'nullable|string|max:50',
            'matric_no' => 'nullable|string|max:50',

            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'q' => 'nullable|string|max:200',
            'sort_by' => 'nullable|string|max:50',
            'sort_dir' => 'nullable|in:asc,desc',
        ];
    }
}
