<?php

namespace App\Http\Requests;

use App\Students\PronounSets\PronounSets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('student'));
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'class_name' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'receives_grades' => ['sometimes', 'boolean'],
            'pronoun_set' => ['sometimes', Rule::in(PronounSets::keys())],
        ];
    }
}
