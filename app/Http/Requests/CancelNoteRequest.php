<?php

namespace App\Http\Requests;

/**
 * Validates the cancel payload for AR Credit / Debit Note forms.
 *
 * Source: BL `DT_AR_CREDIT_NOTE_FORM` (`$_GET['cancelcr']`) and
 * `DT_AR_DEBIT_NOTE_FORM` (`$_GET['canceldn']`). Legacy BL requires a
 * reason plus the note number; we capture the cancel_reason and flip the
 * master status to `CANCELLED` in the controller.
 */
class CancelNoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => 'required|string|min:3|max:500',
        ];
    }
}
