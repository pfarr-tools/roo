<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentTaskReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.expectation_id' => ['required', 'integer', 'exists:assessment_task_expectations,id'],
            'items.*.occurrence' => ['required', 'integer', 'min:1'],
            'items.*.awarded_points' => ['required', 'numeric'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
            'extra_points' => ['present', 'nullable', 'numeric'],
            'extra_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
