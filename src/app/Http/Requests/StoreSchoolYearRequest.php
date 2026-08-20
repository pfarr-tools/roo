<?php

namespace App\Http\Requests;

use App\Models\SchoolYear;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SchoolYear::class);
    }

    public function rules(): array
    {
        return ['school_id' => [$this->isMethod('post') ? 'required' : 'sometimes', 'integer', 'exists:schools,id'], 'name' => ['sometimes', 'nullable', 'string', 'max:100'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on'], 'second_half_start_on' => ['nullable', 'date', 'after:starts_on', 'before_or_equal:ends_on'], 'timezone' => ['required', 'timezone']];
    }
}
