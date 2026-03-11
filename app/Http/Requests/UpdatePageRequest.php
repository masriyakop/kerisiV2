<?php

namespace App\Http\Requests;

class UpdatePageRequest extends BaseFormRequest
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
        return [
            'title'             => 'sometimes|required|string|min:1',
            'slug'              => 'nullable|string',
            'content'           => 'sometimes|required|string|min:1',
            'status'            => 'nullable|in:draft,published,archived',
            'featured_image_id' => 'nullable|integer|exists:media,id',
        ];
    }
}
