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
            'items' => ['present', 'array'],
            'items.*.expectation_id' => ['required', 'integer', 'exists:assessment_task_expectations,id'],
            'items.*.occurrence' => ['required', 'integer', 'min:1'],
            'items.*.awarded_points' => ['required', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
            'options' => ['sometimes', 'array'],
            'options.*.id' => ['required', 'string', 'max:100'],
            'options.*.selected' => ['required', 'boolean'],
            'sorting_sequence' => ['sometimes', 'array'],
            'sorting_sequence.*' => ['nullable', 'integer'],
            'extra_points' => ['present', 'nullable', 'integer'],
            'extra_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
