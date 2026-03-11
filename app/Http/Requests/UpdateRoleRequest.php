<?php

namespace App\Http\Requests;

class UpdateRoleRequest extends BaseFormRequest
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
        $roleId = $this->route('id') ?? $this->route('role');

        return [
            'name'          => 'sometimes|required|string|min:1|unique:roles,name,' . $roleId,
            'description'   => 'nullable|string',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string',
        ];
    }
}
