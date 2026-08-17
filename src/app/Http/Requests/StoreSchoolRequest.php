<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', School::class);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'short_name' => ['nullable', 'string', 'max:50'], 'school_type' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string']];
    }
}
