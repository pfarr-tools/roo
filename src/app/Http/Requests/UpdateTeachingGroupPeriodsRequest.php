<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeachingGroupPeriodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->organization_id !== null;
    }

    public function rules(): array
    {
        return ['periods' => ['present', 'array'], 'periods.*.school_period_id' => ['required', 'integer', 'exists:school_periods,id'], 'periods.*.weekday' => ['required', 'integer', 'between:1,5']];
    }
}
