<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'         => ['sometimes', 'required', 'string', 'max:255'],
            'content'       => ['sometimes', 'required', 'string'],
            'status'        => ['sometimes', 'required', 'in:draft,scheduled,published'],
            'published_at'  => ['nullable', 'date', 'required_if:status,scheduled'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'published_at.required_if' => 'The published_at timestamp is required when scheduling a post.',
        ];
    }
}
