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
    public function index(TeachingGroup $teachingGroup)
    {
        $this->authorize('view', $teachingGroup);
        return Inertia::render('Assessments/Index', [
            'group' => $teachingGroup,
            'assessments' => $teachingGroup->assessments()->with('tasks.competency')->latest('assessed_on')->latest()->get(),
            'students' => $teachingGroup->students()->orderBy('last_name')->orderBy('first_name')->get(['students.id', 'first_name', 'last_name']),
            'competencies' => $teachingGroup->teachingUnits()->with('competencies')->get()->flatMap->competencies->values(),
        ]);
    }

    public function create(TeachingGroup $teachingGroup)
    {
        $this->authorize('update', $teachingGroup);
        return Inertia::render('Assessments/Form', ['group' => $teachingGroup, 'competencies' => $this->competencies($teachingGroup)]);
    }

    public function edit(TeachingGroup $teachingGroup, Assessment $assessment)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        return Inertia::render('Assessments/Form', ['group' => $teachingGroup, 'assessment' => $assessment->load('tasks'), 'competencies' => $this->competencies($teachingGroup)]);
    }

    public function store(Request $request, TeachingGroup $teachingGroup)
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'assessed_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string'], 'tasks' => ['required', 'array', 'min:1'], 'tasks.*.title' => ['required', 'string', 'max:255'], 'tasks.*.solution' => ['nullable', 'string'], 'tasks.*.max_points' => ['nullable', 'integer', 'min:1'], 'tasks.*.level' => ['nullable', 'in:G,M,E'], 'tasks.*.competency_id' => ['nullable', 'integer']]);
        $competencyIds = $teachingGroup->teachingUnits()->with('competencies:id,teaching_unit_id')->get()->flatMap->competencies->pluck('id');
        abort_unless(collect($data['tasks'])->pluck('competency_id')->filter()->diff($competencyIds)->isEmpty(), 422);
        DB::transaction(function () use ($data, $teachingGroup): void {
            $assessment = $teachingGroup->assessments()->create(['organization_id' => $teachingGroup->organization_id, 'title' => $data['title'], 'assessed_on' => $data['assessed_on'] ?? null, 'notes' => $data['notes'] ?? null]);
            foreach ($data['tasks'] as $position => $task) $assessment->tasks()->create(['teaching_unit_competency_id' => $task['competency_id'] ?? null, 'title' => $task['title'], 'solution' => $task['solution'] ?? null, 'max_points' => $task['max_points'] ?? null, 'level' => $task['level'] ?? null, 'position' => $position + 1]);
        });
        return to_route('assessments.index', $teachingGroup)->with('success', 'Lernstandserhebung wurde angelegt.');
    }

    public function update(Request $request, TeachingGroup $teachingGroup, Assessment $assessment)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'assessed_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string'], 'tasks' => ['required', 'array', 'min:1'], 'tasks.*.id' => ['nullable', 'integer'], 'tasks.*.title' => ['required', 'string', 'max:255'], 'tasks.*.solution' => ['nullable', 'string'], 'tasks.*.max_points' => ['nullable', 'integer', 'min:1'], 'tasks.*.level' => ['nullable', 'in:G,M,E'], 'tasks.*.competency_id' => ['nullable', 'integer']]);
        $competencyIds = $teachingGroup->teachingUnits()->with('competencies:id,teaching_unit_id')->get()->flatMap->competencies->pluck('id');
        abort_unless(collect($data['tasks'])->pluck('competency_id')->filter()->diff($competencyIds)->isEmpty(), 422);
        DB::transaction(function () use ($data, $assessment): void {
            $assessment->update(collect($data)->only(['title', 'assessed_on', 'notes'])->all());
            $kept = [];
            foreach ($data['tasks'] as $position => $task) {
                $model = $assessment->tasks()->updateOrCreate(['id' => $task['id'] ?? null], ['teaching_unit_competency_id' => $task['competency_id'] ?? null, 'title' => $task['title'], 'solution' => $task['solution'] ?? null, 'max_points' => $task['max_points'] ?? null, 'level' => $task['level'] ?? null, 'position' => $position + 1]);
                $kept[] = $model->id;
            }
            $assessment->tasks()->whereNotIn('id', $kept)->whereDoesntHave('results')->delete();
        });
        return to_route('assessments.index', $teachingGroup)->with('success', 'Lernstandserhebung wurde gespeichert.');
    }

    private function competencies(TeachingGroup $teachingGroup)
    {
        return $teachingGroup->teachingUnits()->with('competencies')->get()->flatMap->competencies->values();
    }

    public function updateResult(Request $request, TeachingGroup $teachingGroup, AssessmentTask $assessmentTask)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessmentTask->assessment->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['student_id' => ['required', 'integer'], 'points' => ['nullable', 'integer', 'min:0'], 'level' => ['nullable', 'in:G,M,E'], 'numeric_grade' => ['nullable', 'regex:/^[1-6](?:[+-])?$/'], 'note' => ['nullable', 'string', 'max:2000']]);
        abort_unless($teachingGroup->students()->whereKey($data['student_id'])->exists(), 422);
        StudentAssessmentResult::updateOrCreate(['assessment_task_id' => $assessmentTask->id, 'student_id' => $data['student_id']], collect($data)->except('student_id')->all());
        return back()->with('success', 'Ergebnis wurde gespeichert.');
    }
}
