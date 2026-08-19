<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeachingGroupRitualsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['phase_template_ids' => ['array'], 'phase_template_ids.*' => ['integer']];
    }
}
