<?php

namespace App\Http\Controllers;

use App\Models\CompetenceEvidence;
use App\Models\StudentEvaluation;
use App\Models\TeachingGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EvaluationController extends Controller
{
    public function createPeriod(TeachingGroup $teachingGroup)
    {
        $this->authorize('update', $teachingGroup);

        return Inertia::render('Evaluations/PeriodForm', ['group' => $teachingGroup]);
    }

    public function storePeriod(Request $request, TeachingGroup $teachingGroup)
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['label' => ['required', 'string', 'max:100'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on']]);
        DB::transaction(function () use ($data, $teachingGroup): void {
            $period = $teachingGroup->reportPeriods()->create([...$data, 'organization_id' => $teachingGroup->organization_id]);
            $teachingGroup->students()->get()->each(fn ($student) => $period->evaluations()->create(['student_id' => $student->id]));
        });

        return to_route('teaching-groups.show', $teachingGroup)->with('success', 'Bewertungszeitraum wurde angelegt.');
    }

    public function edit(TeachingGroup $teachingGroup, StudentEvaluation $evaluation)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($evaluation->period->teaching_group_id === $teachingGroup->id, 404);

        $evaluation->load('student', 'period', 'observationScales');
        $customProcessCompetences = $teachingGroup->grading_model === 'observation_scales'
            ? $teachingGroup->school->customProcessCompetences()->where('is_active', true)->get(['id', 'text', 'position'])
            : collect();
        $averages = CompetenceEvidence::query()
            ->selectRaw('custom_process_competence_id, AVG(custom_scale_level) AS average')
            ->where('student_id', $evaluation->student_id)
            ->whereNotNull('custom_process_competence_id')
            ->whereNotNull('custom_scale_level')
            ->whereHas('scheduledLesson.slot', function ($query) use ($teachingGroup, $evaluation): void {
                $query->where('teaching_group_id', $teachingGroup->id)
                    ->whereBetween('date', [$evaluation->period->starts_on, $evaluation->period->ends_on]);
            })
            ->groupBy('custom_process_competence_id')
            ->get()
            ->keyBy('custom_process_competence_id');
        $competenceAverages = $customProcessCompetences->map(function ($competence) use ($averages, $teachingGroup): array {
            $average = $averages->get($competence->id)?->average;

            return [
                'custom_process_competence_id' => $competence->id,
                'average' => $average !== null ? round((float) $average, 2) : null,
                'rounded_level' => $average !== null ? (int) round((float) $average) : null,
                'interval_count' => $teachingGroup->school->observation_scale_interval_count,
            ];
        })->values();

        return Inertia::render('Evaluations/Edit', [
            'group' => $teachingGroup,
            'evaluation' => $evaluation,
            'customProcessCompetences' => $customProcessCompetences,
            'customProcessCompetenceScaleIntervalCount' => $teachingGroup->school->observation_scale_interval_count,
            'competenceAverages' => $competenceAverages,
        ]);
    }

    public function update(Request $request, TeachingGroup $teachingGroup, StudentEvaluation $evaluation)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($evaluation->period->teaching_group_id === $teachingGroup->id, 404);
        abort_if($evaluation->status === 'confirmed', 422, 'Eine bestätigte Bewertung kann nicht mehr geändert werden.');
        $data = $request->validate(['draft_text' => ['nullable', 'string', 'max:10000'], 'teacher_note' => ['nullable', 'string', 'max:5000'], 'status' => ['required', 'in:draft,confirmed'], 'observation_scales' => ['sometimes', 'array'], 'observation_scales.*.custom_process_competence_id' => ['required', 'integer'], 'observation_scales.*.custom_scale_level' => ['nullable', 'integer'], 'observation_scales.*.custom_scale_status' => ['nullable', 'in:ne']]);
        $customCompetences = $teachingGroup->school->customProcessCompetences()->where('is_active', true)->get(['id', 'text', 'position']);
        $submittedScales = collect($data['observation_scales'] ?? []);
        if ($teachingGroup->grading_model === 'observation_scales') {
            foreach ($submittedScales as $scale) {
                $competence = $customCompetences->firstWhere('id', $scale['custom_process_competence_id']);
                abort_unless($competence, 422);
                $hasLevel = filled($scale['custom_scale_level'] ?? null);
                $hasStatus = ($scale['custom_scale_status'] ?? null) === 'ne';
                abort_unless($hasLevel xor $hasStatus, 422);
                abort_unless(! $hasLevel || ((int) $scale['custom_scale_level'] >= 1 && (int) $scale['custom_scale_level'] <= $teachingGroup->school->observation_scale_interval_count), 422);
            }
        } else {
            $submittedScales = collect();
        }
        DB::transaction(function () use ($data, $evaluation, $teachingGroup, $customCompetences, $submittedScales): void {
            $evaluation->update([...collect($data)->only(['draft_text', 'teacher_note', 'status'])->all(), 'confirmed_at' => $data['status'] === 'confirmed' ? now() : null]);
            $evaluation->observationScales()->delete();
            foreach ($submittedScales as $scale) {
                $competence = $customCompetences->firstWhere('id', $scale['custom_process_competence_id']);
                $evaluation->observationScales()->create(['custom_process_competence_id' => $competence->id, 'competence_text_snapshot' => $competence->text, 'position_snapshot' => $competence->position, 'interval_count_snapshot' => $teachingGroup->school->observation_scale_interval_count, 'custom_scale_level' => $scale['custom_scale_level'] ?? null, 'custom_scale_status' => $scale['custom_scale_status'] ?? null]);
            }
        });

        return back()->with('success', 'Bewertungsentwurf wurde gespeichert.');
    }
}
