<?php

namespace App\Http\Controllers;

use App\Models\EducationPlanCompetency;
use App\Models\LessonTemplate;
use App\Models\PhaseTemplate;
use App\Models\ScheduleSlot;
use App\Models\SocialForm;
use App\Http\Requests\UpdateLessonExecutionRequest;
use App\Models\ScheduledLesson;
use App\Services\CompetencyResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonWorkspaceController extends Controller
{
    public function show(Request $request, ScheduleSlot $scheduleSlot, CompetencyResolver $competencyResolver): Response
    {
        $group = $scheduleSlot->group;
        $this->authorize('view', $group);
        $scheduleSlot->load([
            'group.school:id,name,short_name',
            'group.schoolYear:id,name',
            'scheduledLesson.lesson.unit.resources',
            'scheduledLesson.lesson.unit.competencies.educationPlanCompetency.area',
            'scheduledLesson.lesson.unit.competencies.educationPlanCompetency.variants',
            'scheduledLesson.lesson.unit.competencies.curriculumCompetency',
            'scheduledLesson.lesson.phases.socialForm',
            'scheduledLesson.lesson.competencies.educationPlanCompetency.area',
            'scheduledLesson.lesson.competencies.curriculumCompetency',
        ]);
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson, 404, 'Für diesen Termin ist keine Unterrichtsstunde eingeplant.');
        $lesson->unit->competencies->each(fn ($competency) => $competency->setAttribute('competency_presentation', $competencyResolver->present($competency)));
        $targetCompetencies = $lesson->competencies
            ->map(fn ($competency) => $competencyResolver->present($competency))
            ->groupBy('kind')
            ->map(fn ($competencies) => $competencies->values())
            ->all();

        return Inertia::render('Lessons/Show', [
            'slot' => $scheduleSlot,
            'group' => $group,
            'lesson' => $lesson,
            'unit' => $lesson->unit,
            'phaseTemplates' => PhaseTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->with('socialForm:id,name')->orderBy('position')->orderBy('title')->get(['id', 'title', 'duration_minutes', 'social_form_id', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'material', 'media']),
            'socialForms' => SocialForm::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(['id', 'name']),
            'lessonTemplates' => LessonTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'competencyOptions' => EducationPlanCompetency::query()->whereIn('id', $lesson->unit->competencies->pluck('education_plan_competency_id')->filter())->with(['area:id,kind', 'variants:id,education_plan_competency_id,text,position'])->get()->each(fn ($competency) => $competency->setAttribute('competency_presentation', $competencyResolver->present($competency))),
            'targetCompetencies' => ['process' => $targetCompetencies['process'] ?? [], 'content' => $targetCompetencies['content'] ?? []],
        ]);
    }

    public function updateExecution(UpdateLessonExecutionRequest $request, ScheduleSlot $scheduleSlot): \Illuminate\Http\RedirectResponse
    {
        $group = $scheduleSlot->group;
        $this->authorize('update', $group);
        $scheduledLesson = $scheduleSlot->scheduledLesson;
        abort_unless($scheduledLesson, 404);
        $scheduledLesson->update($request->validated());

        return back()->with('success', 'Durchführung wurde gespeichert.');
    }
}
