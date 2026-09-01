<?php

namespace App\Http\Requests;

use App\Models\CustomProcessCompetence;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolObservationScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('school'));
    }

    public function rules(): array
    {
        return [
            'observation_scale_interval_count' => ['required', 'integer', 'between:2,6'],
            'competences' => ['required', 'array'],
            'competences.*.id' => ['nullable', 'integer'],
            'competences.*.text' => ['required', 'string', 'max:2000'],
            'competences.*.position' => ['required', 'integer', 'min:1'],
            'competences.*.is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $school = $this->route('school');
            $competences = collect($this->input('competences', []));

            if ($competences->pluck('position')->duplicates()->isNotEmpty()) {
                $validator->errors()->add('competences', 'Jede Position darf nur einmal vorkommen.');
            }

            $foreignIds = $competences->pluck('id')->filter()->diff(
                CustomProcessCompetence::query()->where('school_id', $school->id)->whereKey($competences->pluck('id')->filter())->pluck('id')
            );

            if ($foreignIds->isNotEmpty()) {
                $validator->errors()->add('competences', 'Eine Prozesskompetenz gehört nicht zu dieser Schule.');
            }
        });
    }
}
