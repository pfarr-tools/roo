<?php

namespace App\Http\Requests;

use App\Models\ScheduledLesson;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:'.implode(',', ScheduledLesson::statuses())],
            'actual_on' => ['nullable', 'date'],
            'execution_notes' => ['nullable', 'string'],
        ];
    }
}
