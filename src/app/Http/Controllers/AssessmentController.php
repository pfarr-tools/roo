<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\StudentAssessmentResult;
use App\Models\TeachingGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AssessmentController extends Controller
{
    public function create(TeachingGroup $teachingGroup)
    {
        $this->authorize('update', $teachingGroup);

        return Inertia::render('Assessments/Form', $this->formProps($teachingGroup));
    }

    public function edit(TeachingGroup $teachingGroup, Assessment $assessment)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);

        return Inertia::render('Assessments/Form', $this->formProps($teachingGroup, $assessment->load('tasks.levels', 'tasks.competency')));
    }

    public function store(Request $request, TeachingGroup $teachingGroup)
    {
        $this->authorize('update', $teachingGroup);
        $data = $this->validatedAssessment($request);
        DB::transaction(function () use ($data, $teachingGroup): void {
            $assessment = $teachingGroup->assessments()->create(['organization_id' => $teachingGroup->organization_id, 'report_period_id' => $data['report_period_id'] ?? null, 'title' => $data['title'], 'assessed_on' => $data['assessed_on'] ?? null, 'notes' => $data['notes'] ?? null]);
            $this->syncTasks($assessment, $teachingGroup, $data['tasks']);
        });

        return to_route('teaching-groups.show', $teachingGroup)->with('success', 'Lernstandserhebung wurde angelegt.');
    }

    public function update(Request $request, TeachingGroup $teachingGroup, Assessment $assessment)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        $data = $this->validatedAssessment($request);
        DB::transaction(function () use ($data, $assessment, $teachingGroup): void {
            $assessment->update(collect($data)->only(['report_period_id', 'title', 'assessed_on', 'notes'])->all());
            $assessment->tasks()->sync([]);
            $this->syncTasks($assessment, $teachingGroup, $data['tasks']);
        });

        return to_route('teaching-groups.show', $teachingGroup)->with('success', 'Lernstandserhebung wurde gespeichert.');
    }

    public function updateResult(Request $request, TeachingGroup $teachingGroup, AssessmentTask $assessmentTask)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessmentTask->assessments()->where('teaching_group_id', $teachingGroup->id)->exists(), 404);
        $data = $request->validate(['student_id' => ['required', 'integer'], 'points' => ['nullable', 'integer', 'min:0'], 'level' => ['nullable', 'in:G,M,E'], 'numeric_grade' => ['nullable', 'regex:/^[1-6](?:[+-])?$/'], 'note' => ['nullable', 'string', 'max:2000']]);
        abort_unless($teachingGroup->students()->whereKey($data['student_id'])->exists(), 422);
        StudentAssessmentResult::updateOrCreate(['assessment_task_id' => $assessmentTask->id, 'student_id' => $data['student_id']], collect($data)->except('student_id')->all());

        return back()->with('success', 'Ergebnis wurde gespeichert.');
    }

    private function formProps(TeachingGroup $teachingGroup, ?Assessment $assessment = null): array
    {
        return ['group' => $teachingGroup, 'assessment' => $assessment, 'reportPeriods' => $teachingGroup->reportPeriods()->orderBy('starts_on')->get(), 'competencies' => $this->competencies($teachingGroup), 'assessmentTasks' => $this->assessmentTasks($teachingGroup), 'differentiated' => $this->isDifferentiated($teachingGroup)];
    }

    private function competencies(TeachingGroup $teachingGroup)
    {
        return $teachingGroup->teachingUnits()->with('competencies')->get()->flatMap->competencies->values();
    }

    private function assessmentTasks(TeachingGroup $teachingGroup)
    {
        return AssessmentTask::where('organization_id', $teachingGroup->organization_id)->with(['competency', 'levels'])->orderBy('title')->get();
    }

    private function validatedAssessment(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:255'], 'report_period_id' => ['nullable', 'integer'], 'assessed_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string'], 'tasks' => ['required', 'array', 'min:1'], 'tasks.*.task_id' => ['nullable', 'integer'], 'tasks.*.title' => ['nullable', 'string', 'max:255'], 'tasks.*.solution' => ['nullable', 'string'], 'tasks.*.max_points' => ['nullable', 'integer', 'min:1'], 'tasks.*.competency_id' => ['nullable', 'integer'], 'tasks.*.level' => ['nullable', 'in:G,M,E'], 'tasks.*.levels' => ['sometimes', 'array'], 'tasks.*.levels.*' => ['in:G,M,E']]);
    }

    private function syncTasks(Assessment $assessment, TeachingGroup $teachingGroup, array $tasks): void
    {
        $competencyIds = $teachingGroup->teachingUnits()->with('competencies:id,teaching_unit_id')->get()->flatMap->competencies->pluck('id');
        $attach = [];
        foreach ($tasks as $position => $task) {
            $levels = collect($task['levels'] ?? ($task['level'] ? [$task['level']] : []))->unique()->values();
            if ($this->isDifferentiated($teachingGroup)) {
                abort_unless($levels->isNotEmpty(), 422, 'Für differenzierte Gruppen muss mindestens ein G/M/E-Niveau gewählt werden.');
            }
            if (! empty($task['task_id'])) {
                $model = AssessmentTask::where('organization_id', $teachingGroup->organization_id)->whereKey($task['task_id'])->firstOrFail();
                abort_unless($competencyIds->contains($model->teaching_unit_competency_id), 422);
                if ($levels->isNotEmpty()) {
                    $model->levels()->delete();
                    $model->levels()->createMany($levels->map(fn ($level) => ['level' => $level])->all());
                    $model->update(['level' => $levels->first()]);
                }
            } else {
                abort_unless(! empty($task['competency_id']) && $competencyIds->contains($task['competency_id']), 422);
                $model = AssessmentTask::create(['organization_id' => $teachingGroup->organization_id, 'teaching_unit_competency_id' => $task['competency_id'], 'title' => $task['title'], 'solution' => $task['solution'] ?? null, 'max_points' => $task['max_points'] ?? null, 'level' => $levels->first()]);
                $model->levels()->delete();
                $model->levels()->createMany($levels->map(fn ($level) => ['level' => $level])->all());
            }
            $attach[$model->id] = ['position' => $position + 1];
        }
        $assessment->tasks()->sync($attach);
    }

    private function isDifferentiated(TeachingGroup $teachingGroup): bool
    {
        return $teachingGroup->gradeLevels()->pluck('grade_level')->contains(fn ($level) => preg_match('/(?:^|[\s\/])(?:G|M|E)(?:$|[\s\/])/', strtoupper((string) $level)) === 1);
    }
}
