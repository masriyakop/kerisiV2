<?php

namespace App\Http\Requests;

class StorePostRequest extends BaseFormRequest
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
            'title'             => 'required|string|min:1',
            'slug'              => 'nullable|string',
            'excerpt'           => 'nullable|string',
            'content'           => 'required|string|min:1',
            'status'            => 'nullable|in:draft,published,archived',
            'featured_image_id' => 'nullable|integer|exists:media,id',
            'category_ids'      => 'nullable|array',
            'category_ids.*'    => 'integer|exists:categories,id',
        ];
    }
}
