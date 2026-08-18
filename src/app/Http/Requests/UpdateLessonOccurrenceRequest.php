<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonOccurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:planned,prepared,conducted,cancelled,postponed'],
            'actual_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
