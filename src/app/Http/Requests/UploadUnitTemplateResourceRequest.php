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
        return ['copyrights' => ['nullable', 'string', 'max:1000'], 'resource' => [
            'required',
            'file',
            'max:20480',
            function (string $attribute, mixed $file, \Closure $fail): void {
                $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'txt', 'md', 'wscdoc'];
                if (! in_array(strtolower($file->getClientOriginalExtension()), $allowed, true)) {
                    $fail('Dieser Dateityp ist nicht zugelassen.');
                }
            },
        ]];
    }
}
