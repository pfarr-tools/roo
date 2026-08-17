<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('schoolYear'));
    }

    public function rules(): array
    {
        return ['date' => ['required', 'date'], 'kind' => ['required', 'in:instruction,holiday,no_instruction'], 'label' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:10000'], 'change_reason' => ['nullable', 'string', 'max:1000']];
    }
}
