<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonPhaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'phase_template_id' => ['nullable', 'integer', 'exists:phase_templates,id'],
            'title' => ['required_without:phase_template_id', 'nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:999'],
            'social_form' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'materials' => ['nullable', 'string'],
        ];
    }
}
