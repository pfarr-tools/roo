<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SplitPlannedUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['split_on' => ['required', 'date']];
    }
}
