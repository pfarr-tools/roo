<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeachingGroupMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->organization_id !== null;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['nullable', 'integer', 'exists:students,id', 'required_without:student_ids'],
            'student_ids' => ['nullable', 'array', 'min:1', 'required_without:student_id'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
