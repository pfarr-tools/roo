<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlannedUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'planned_hours' => ['required', 'integer', 'min:1', 'max:200'],
            'unit_template_id' => ['nullable', 'integer'],
            'curriculum_topic_id' => ['nullable', 'integer'],
            'is_interrupted' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
