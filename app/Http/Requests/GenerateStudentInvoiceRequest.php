<?php

namespace App\Http\Requests;

/**
 * Validation for POST /student-finance/invoice-generation/generate.
 *
 * Mirrors the legacy `CALL_PROC_STUDENT_INVOICE?generate=1` form payload
 * from MENUID 1231. The legacy flow uses the same parameters as `find=1`
 * plus the `unique_key` that was returned by the previous search call
 * (so that the SP `invoiceCreationByBatch` only generates invoices for
 * the exact roster the user just inspected).
 */
class GenerateStudentInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unique_key' => 'required|string|max:64',
            'semester' => 'required|string|max:50',
            'program_level' => 'required|string|max:50',
            'fee_type' => 'required|string|max:50',
            'student_type' => 'nullable|string|max:50',
            'intake_case' => 'nullable|string|max:50',
            'matric_no' => 'nullable|string|max:50',
        ];
    }
}
