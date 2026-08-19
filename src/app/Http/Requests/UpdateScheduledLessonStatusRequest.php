<?php

namespace App\Http\Requests;

use App\Models\ScheduledLesson;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduledLessonStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', 'in:'.implode(',', ScheduledLesson::statuses())]];
    }
}
