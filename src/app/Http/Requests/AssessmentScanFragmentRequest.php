<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentScanFragmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fragment' => ['required', 'file', 'mimes:png', 'max:10240'],
            'page' => ['required', 'integer', 'min:1'],
            'booklet' => ['required', 'integer', 'min:1'],
            'task_id' => ['required', 'string', 'max:100'],
            'start_y_cm' => ['required', 'numeric', 'min:0'],
            'end_y_cm' => ['required', 'numeric', 'gt:start_y_cm'],
        ];
    }
}
