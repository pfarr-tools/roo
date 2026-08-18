<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePhaseTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lesson_template_id' => ['required', 'integer', 'exists:lesson_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:999'],
            'social_form' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:20000'],
            'material' => ['nullable', 'string', 'max:20000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
