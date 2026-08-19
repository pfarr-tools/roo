<?php

namespace App\Http\Controllers;

use App\Models\EducationPlanCompetency;
use App\Models\LessonTemplate;
use App\Models\PhaseTemplate;
use App\Models\ScheduleSlot;
use App\Models\SocialForm;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonWorkspaceController extends Controller
{
    public function show(Request $request, ScheduleSlot $scheduleSlot): Response
    {
        $group = $scheduleSlot->group;
        $this->authorize('view', $group);
        $scheduleSlot->load([
            'group.school:id,name,short_name',
            'group.schoolYear:id,name',
            'scheduledLesson.lesson.unit.resources',
            'scheduledLesson.lesson.phases.socialForm',
            'scheduledLesson.lesson.competencies',
        ]);
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson, 404, 'Für diesen Termin ist keine Unterrichtsstunde eingeplant.');

        return Inertia::render('Lessons/Show', [
            'slot' => $scheduleSlot,
            'group' => $group,
            'lesson' => $lesson,
            'unit' => $lesson->unit,
            'phaseTemplates' => PhaseTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->with('socialForm:id,name')->orderBy('position')->orderBy('title')->get(['id', 'title', 'duration_minutes', 'social_form_id', 'description', 'material']),
            'socialForms' => SocialForm::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(['id', 'name']),
            'lessonTemplates' => LessonTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'competencyOptions' => EducationPlanCompetency::query()->whereIn('id', $lesson->unit->competencies->pluck('education_plan_competency_id')->filter())->with(['area:id,kind', 'variants:id,education_plan_competency_id,text,position'])->get(),
        ]);
    }
}
