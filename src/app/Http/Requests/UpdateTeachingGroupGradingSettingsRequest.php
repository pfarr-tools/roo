<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTeachingGroupGradingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('teachingGroup'));
    }

    public function rules(): array
    {
        return [
            'grading_model' => ['required', 'string', 'in:observation_scales,competency_texts_and_grades,grades_only'],
            'numeric_grades_enabled' => ['boolean'],
            'components' => ['sometimes', 'array', 'min:2'],
            'components.*.type' => ['required', 'string', 'in:observations,written_assessments,custom'],
            'components.*.label' => ['required_if:components.*.type,custom', 'nullable', 'string', 'max:100'],
            'components.*.percentage' => ['required', 'integer', 'between:0,100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $gradesEnabled = $this->input('grading_model') === 'grades_only'
                || ($this->input('grading_model') === 'competency_texts_and_grades' && $this->boolean('numeric_grades_enabled'));
            if (! $gradesEnabled) {
                return;
            }
            $components = collect($this->input('components', []));
            if ($components->whereIn('type', ['observations', 'written_assessments'])->pluck('type')->duplicates()->isNotEmpty()) {
                $validator->errors()->add('components', 'Die Bestandteile dürfen nicht doppelt vorkommen.');
            }
            if ($components->where('type', 'observations')->isEmpty() || $components->where('type', 'written_assessments')->isEmpty()) {
                $validator->errors()->add('components', 'Beobachtungen im Unterricht und Schriftliche Leistungen sind erforderlich.');
            }
            if ($components->sum(fn (array $component): int => (int) ($component['percentage'] ?? 0)) !== 100) {
                $validator->errors()->add('components', 'Die Gewichtung muss insgesamt 100 Prozent ergeben.');
            }
        });
    }
}
