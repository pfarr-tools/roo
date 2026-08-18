<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolPeriodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('school'));
    }

    public function rules(): array
    {
        return [
            'periods' => ['present', 'array'],
            'periods.*.id' => ['nullable', 'integer', 'exists:school_periods,id'],
            'periods.*.period_number' => ['required', 'integer', 'between:1,12'],
            'periods.*.starts_at' => ['required', 'date_format:H:i'],
        ];
    }
}
