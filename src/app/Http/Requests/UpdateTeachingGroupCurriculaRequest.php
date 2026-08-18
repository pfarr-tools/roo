<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeachingGroupCurriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->organization_id !== null;
    }

    public function rules(): array
    {
        return ['curriculum_assignments' => ['nullable', 'array'], 'curriculum_assignments.*.curriculum_id' => ['required', 'integer', 'distinct', 'exists:curricula,id'], 'curriculum_assignments.*.role' => ['required', 'in:primary,additional']];
    }
}
