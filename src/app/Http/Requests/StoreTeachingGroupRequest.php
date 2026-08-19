<?php

namespace App\Http\Requests;

use App\Models\TeachingGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeachingGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TeachingGroup::class);
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'name' => ['required', 'string', 'max:255'],
            'aktenzeichen' => ['nullable', 'string', 'in:62.53,62.54,62.55,62.56,62.57,62.58'],
            'notes' => ['nullable', 'string'],
            'grade_levels' => ['required', 'array', 'min:1'],
            'grade_levels.*' => ['required', 'string', 'max:30', 'distinct'],
            'periods' => ['sometimes', 'array'],
            'periods.*.school_period_id' => ['required', 'integer', 'exists:school_periods,id'],
            'periods.*.weekday' => ['required', 'integer', 'between:1,5'],
        ];
    }
}
