<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', School::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'short_name' => ['nullable', 'string', 'max:50'], 'city' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'],
            'curriculum_assignments' => ['nullable', 'array'],
            'curriculum_assignments.*.curriculum_id' => ['required', 'integer', 'exists:curricula,id'],
            'curriculum_assignments.*.valid_from' => ['nullable', 'date'],
            'curriculum_assignments.*.valid_until' => ['nullable', 'date'],
            'curriculum_assignments.*.school_type' => ['nullable', 'string', 'max:50'],
            'curriculum_assignments.*.grades' => ['nullable', 'array'],
            'curriculum_assignments.*.grades.*' => ['integer', 'min:1', 'max:13'],
            'curriculum_assignments.*.notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
