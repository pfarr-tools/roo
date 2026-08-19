<?php

namespace App\Http\Controllers;

use App\Models\EducationPlanCompetency;
use App\Models\LessonTemplate;
use App\Models\MaterialItem;
use App\Models\PhaseTemplate;
use App\Models\ResourceLink;
use App\Models\ScheduleSlot;
use App\Models\SocialForm;
use App\Http\Requests\UpdateLessonExecutionRequest;
use App\Models\ScheduledLesson;
use App\Services\CompetencyResolver;
use App\Services\WscDocInspector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LessonWorkspaceController extends Controller
{
    public function show(Request $request, ScheduleSlot $scheduleSlot, CompetencyResolver $competencyResolver, WscDocInspector $inspector): Response
    {
        $group = $scheduleSlot->group;
        $this->authorize('view', $group);
        $scheduleSlot->load([
            'group.school:id,name,short_name',
            'group.schoolYear:id,name',
            'scheduledLesson.lesson.unit.resources.lesson',
            'scheduledLesson.lesson.unit.materialItems',
            'scheduledLesson.lesson.resources',
            'scheduledLesson.lesson.materialItems',
            'scheduledLesson.lesson.unit.competencies.educationPlanCompetency.area',
            'scheduledLesson.lesson.unit.competencies.educationPlanCompetency.variants',
            'scheduledLesson.lesson.unit.competencies.curriculumCompetency',
            'scheduledLesson.lesson.phases.socialForm',
            'scheduledLesson.lesson.phases.resources',
            'scheduledLesson.lesson.phases.resourceLinks',
            'scheduledLesson.lesson.phases.materialItems',
            'scheduledLesson.lesson.competencies.educationPlanCompetency.area',
            'scheduledLesson.lesson.competencies.curriculumCompetency',
        ]);
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson, 404, 'Für diesen Termin ist keine Unterrichtsstunde eingeplant.');
        $lesson->unit->competencies->each(fn ($competency) => $competency->setAttribute('competency_presentation', $competencyResolver->present($competency)));
        $lesson->resources->each(function ($resource) use ($lesson, $inspector): void {
            if ($resource->page_count === null && strtolower(pathinfo($resource->original_name, PATHINFO_EXTENSION)) === 'wscdoc') {
                $resource->page_count = $inspector->pageCount(Storage::disk('local')->path($resource->storage_path));
            }
            $resource->setAttribute('display_name', $this->resourceFilename($lesson->unit, $resource));
        });
        $lesson->phases->each(function ($phase): void {
            $phase->setAttribute('resource_ids', $phase->resources->pluck('id')->values());
            $phase->setAttribute('resource_link_ids', $phase->resourceLinks->pluck('id')->values());
            $phase->setAttribute('material_item_ids', $phase->materialItems->pluck('id')->values());
        });
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
            'materialItems' => $lesson->unit->materialItems->merge($lesson->materialItems)->unique('id')->values(),
            'resourceLinks' => ResourceLink::where('organization_id', $request->user()->organization_id)->where(function ($query) use ($lesson): void {
                $query->where('teaching_unit_id', $lesson->teaching_unit_id)->orWhere('lesson_id', $lesson->id);
            })->orderBy('title')->get(['id', 'teaching_unit_id', 'lesson_id', 'title', 'url', 'description']),
            'lessonTemplates' => LessonTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'competencyOptions' => EducationPlanCompetency::query()->whereIn('id', $lesson->unit->competencies->pluck('education_plan_competency_id')->filter())->with(['area:id,kind', 'variants:id,education_plan_competency_id,text,position'])->get()->each(fn ($competency) => $competency->setAttribute('competency_presentation', $competencyResolver->present($competency))),
            'targetCompetencies' => ['process' => $targetCompetencies['process'] ?? [], 'content' => $targetCompetencies['content'] ?? []],
        ]);
    }

    private function resourceFilename($unit, $resource): string
    {
        $group = $unit->group()->with('gradeLevels')->first();
        $grade = $this->filenamePart($group?->gradeLevels->pluck('grade_level')->implode('-') ?: '');
        $aktenzeichen = $this->filenamePart($group?->aktenzeichen ?: '');
        $keyword = $this->filenamePart($unit->keyword ?: '');
        $original = pathinfo($resource->original_name ?: 'Datei', PATHINFO_FILENAME);
        $extension = pathinfo($resource->original_name ?: '', PATHINFO_EXTENSION);
        $original = preg_replace('/^\d+(?:\.\d+)*_[^\s]+\s+/u', '', $original) ?: $original;
        $prefix = $aktenzeichen !== '' ? $aktenzeichen.'_'.$grade : $grade;
        $lessonPart = $resource->lesson?->position ? str_pad((string) $resource->lesson->position, 2, '0', STR_PAD_LEFT) : null;

        return trim(collect([$prefix, $keyword, $lessonPart, $this->filenamePart($original).($extension ? '.'.$this->filenamePart($extension) : '')])->filter()->implode(' '));
    }

    private function filenamePart(string $value): string
    {
        return trim((string) preg_replace(['/[^\pL\pN._ -]+/u', '/\s+/u', '/\.{2,}/'], ['-', ' ', '.'], $value), " .-");
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
