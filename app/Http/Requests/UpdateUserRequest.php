<?php

namespace App\Http\Requests;

class UpdateUserRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('id') ?? $this->route('user');

        return [
            'name'      => 'sometimes|required|string|min:1',
            'email'     => 'sometimes|required|email|unique:users,email,' . $userId,
            'password'  => 'nullable|string|min:6',
            'role'      => 'nullable|string|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }
}
