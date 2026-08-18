<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadUnitTemplateResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['resource' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png,txt,md']];
    }
}
