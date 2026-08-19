<?php

namespace App\Http\Controllers;

use App\Http\Requests\InterruptPlannedUnitRequest;
use App\Http\Requests\SplitPlannedUnitRequest;
use App\Http\Requests\StorePlannedUnitRequest;
use App\Http\Requests\UpdateLessonOccurrenceRequest;
use App\Models\CurriculumTopic;
use App\Models\GroupYearPlan;
use App\Models\Lesson;
use App\Models\LessonOccurrence;
use App\Models\LessonPhase;
use App\Models\PlannedUnit;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\UnitTemplate;
use App\Services\YearPlanningWorkspace;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class YearPlanController extends Controller
{
    public function index(): Response
    {
        $organizationId = auth()->user()->organization_id;

        return Inertia::render('YearPlans/Index', [
            'groups' => TeachingGroup::where('organization_id', $organizationId)->with(['school:id,name', 'schoolYear:id,name'])->with('yearPlan')->orderBy('name')->get(),
        ]);
    }

    public function show(TeachingGroup $teachingGroup, YearPlanningWorkspace $workspace): Response
    {
        $this->authorize('view', $teachingGroup);
        $plan = $this->planFor($teachingGroup)->load(['units.template:id,title', 'units.curriculumTopic:id,title', 'units.lessons.occurrences', 'revisions.user:id,name']);

        $workspace->syncSlots($teachingGroup);

        return Inertia::render('YearPlans/Show', [
            'group' => $teachingGroup->load(['school:id,name', 'schoolYear:id,name,starts_on,ends_on', 'schoolYear.days', 'timetableSlots']),
            'plan' => $plan,
            'unitTemplates' => UnitTemplate::where('organization_id', auth()->user()->organization_id)->where('is_active', true)->orderBy('title')->get(['id', 'title', 'expected_hours']),
            'checks' => $this->checks($teachingGroup, $plan),
            'calendar' => $this->calendar($teachingGroup),
            'holidayPeriods' => $teachingGroup->schoolYear->holidayPeriods()->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on', 'name']),
            'workspace' => [
                'units' => $teachingGroup->teachingUnits()->with(['sourceCurriculumTopic:id,title', 'competencies.educationPlanCompetency:id,external_identifier,number,text', 'competencies.curriculumCompetency', 'lessons.competencies', 'lessons.phases', 'lessons.scheduledLessons.slot'])->orderBy('position')->get(),
                'curricula' => $teachingGroup->curricula()->with(['versions.topics.competencies.educationPlanCompetency:id,text'])->get(),
                'slots' => $teachingGroup->scheduleSlots()->with('scheduledLesson.lesson.unit')->orderBy('date')->orderBy('period_number')->get(),
                'coverage' => $workspace->coverage($teachingGroup),
            ],
            'groupOptions' => TeachingGroup::where('organization_id', auth()->user()->organization_id)->with('schoolYear:id,name')->orderBy('name')->get(['id', 'name', 'school_year_id']),
        ]);
    }

    public function takeCurriculumUnit(Request $request, TeachingGroup $teachingGroup, CurriculumTopic $topic, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $curriculumIds = $teachingGroup->curricula()->pluck('curricula.id');
        abort_unless(CurriculumTopic::whereKey($topic->id)->whereHas('version', fn ($query) => $query->whereIn('curriculum_id', $curriculumIds))->exists(), 422, 'Das Curriculumthema gehört nicht zu dieser Unterrichtsgruppe.');
        $unit = $workspace->importCurriculumUnit($teachingGroup, $topic->load('competencies'));
        $plan = $this->planFor($teachingGroup);
        $this->revise($plan, $request->user()->id, 'teaching_unit_imported', 'Curriculum-UE „'.$unit->title.'“ als eigene UE übernommen.');

        return back()->with('success', 'Die Curriculum-UE wurde als eigene Unterrichtseinheit übernommen.');
    }

    public function storeTeachingUnit(Request $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string']]);
        $teachingGroup->teachingUnits()->create($data + ['organization_id' => $teachingGroup->organization_id, 'position' => ($teachingGroup->teachingUnits()->max('position') ?? 0) + 1]);

        return back()->with('success', 'Eigene Unterrichtseinheit wurde angelegt.');
    }

    public function updateTeachingUnit(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $teachingUnit->update($request->validate(['title' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string']]));

        return back()->with('success', 'Unterrichtseinheit wurde gespeichert.');
    }

    public function storeLesson(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'duration' => ['required', 'integer', 'min:1', 'max:12']]);
        $teachingUnit->lessons()->create($data + ['position' => ($teachingUnit->lessons()->max('position') ?? 0) + 1]);

        return back()->with('success', 'Stunde wurde angelegt.');
    }

    public function updateLesson(Request $request, TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $lesson->update($request->validate(['title' => ['required', 'string', 'max:255'], 'duration' => ['required', 'integer', 'min:1', 'max:12'], 'learning_goals' => ['nullable', 'string'], 'materials' => ['nullable', 'string'], 'homework' => ['nullable', 'string'], 'assessment_note' => ['nullable', 'string'], 'notes' => ['nullable', 'string']]));

        return back()->with('success', 'Stunde wurde gespeichert.');
    }

    public function updateLessonCompetencies(Request $request, TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['competency_ids' => ['array'], 'competency_ids.*' => ['integer']]);
        $validIds = $lesson->unit->competencies()->whereIn('id', $data['competency_ids'] ?? [])->pluck('id');
        abort_unless($validIds->count() === count($data['competency_ids'] ?? []), 422, 'Eine Kompetenz gehört nicht zu dieser Unterrichtseinheit.');
        $lesson->competencies()->sync($validIds);

        return back()->with('success', 'Kompetenzen der Stunde wurden gespeichert.');
    }

    public function updatePhase(Request $request, TeachingGroup $teachingGroup, LessonPhase $phase): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($phase->lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $phase->update($request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'materials' => ['nullable', 'string']]));

        return back()->with('success', 'Phase wurde gespeichert.');
    }

    public function reorderLessons(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['lesson_ids' => ['required', 'array'], 'lesson_ids.*' => ['integer']]);
        $allowed = $teachingUnit->lessons()->pluck('id')->sort()->values()->all();
        abort_unless($allowed === collect($data['lesson_ids'])->sort()->values()->all(), 422, 'Die Stunden gehören nicht vollständig zu dieser Unterrichtseinheit.');
        foreach ($data['lesson_ids'] as $position => $lessonId) {
            $teachingUnit->lessons()->whereKey($lessonId)->update(['position' => $position + 1]);
        }

        return back()->with('success', 'Reihenfolge der Stunden wurde gespeichert.');
    }

    public function undoLastReflow(TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $revision = $this->planFor($teachingGroup)->revisions()->where('action', 'slot_reflow')->latest()->first();
        abort_unless($revision && $revision->payload, 422, 'Keine rückgängig machbare Verschiebung vorhanden.');
        foreach ($revision->payload['assignments'] as $assignment) {
            ScheduledLesson::where('lesson_id', $assignment['lesson_id'])->delete();
            ScheduledLesson::create(['lesson_id' => $assignment['lesson_id'], 'schedule_slot_id' => $assignment['schedule_slot_id']]);
        }
        ScheduleSlot::whereKey($revision->payload['blocked_slot_id'])->update(['status' => $revision->payload['previous_status']]);
        $revision->update(['action' => 'slot_reflow_undone']);

        return back()->with('success', 'Die letzte Verschiebung wurde rückgängig gemacht.');
    }

    public function scheduleLesson(Request $request, TeachingGroup $teachingGroup, Lesson $lesson, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['schedule_slot_id' => ['nullable', 'integer']]);
        $slot = ! empty($data['schedule_slot_id']) ? ScheduleSlot::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['schedule_slot_id']) : null;
        $result = $workspace->scheduleLesson($teachingGroup, $lesson, $slot);

        return back()->with($result['overflow'] ? 'warning' : 'success', $result['overflow'] ? $result['overflow'].' Schulstunde(n) passen nicht mehr in verfügbare Termine.' : 'Stunde wurde eingeplant.');
    }

    public function scheduleUnit(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['schedule_slot_id' => ['nullable', 'integer']]);
        $slot = ! empty($data['schedule_slot_id']) ? ScheduleSlot::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['schedule_slot_id']) : null;
        $result = $workspace->scheduleUnit($teachingGroup, $teachingUnit, $slot);

        return back()->with($result['overflow'] ? 'warning' : 'success', $result['overflow'] ? $result['overflow'].' Schulstunde(n) passen nicht mehr in verfügbare Termine.' : 'Unterrichtseinheit wurde eingeplant.');
    }

    public function updateSlot(Request $request, TeachingGroup $teachingGroup, ScheduleSlot $slot): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($slot->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['status' => ['required', 'in:free,buffer,absent,cancelled,blocked'], 'label' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string']]);
        if ($data['status'] !== 'free' && $slot->scheduledLesson()->exists()) {
            $assignments = $slot->scheduledLesson->lesson->scheduledLessons()->get(['schedule_slot_id', 'lesson_id'])->map(fn ($assignment) => $assignment->only(['schedule_slot_id', 'lesson_id']))->all();
            $result = app(YearPlanningWorkspace::class)->blockAndReflow($teachingGroup, $slot, $data['status']);
            $slot->update(['label' => $data['label'] ?? null, 'notes' => $data['notes'] ?? null]);
            $this->revise($this->planFor($teachingGroup), auth()->id(), 'slot_reflow', 'Ausgefallener Termin wurde automatisch verschoben.', ['assignments' => $assignments, 'blocked_slot_id' => $slot->id, 'previous_status' => 'free']);

            return back()->with($result['overflow'] ? 'warning' : 'success', $result['overflow'] ? $result['overflow'].' Schulstunde(n) passen nicht mehr in verfügbare Termine.' : 'Ausfall gespeichert und nachfolgende Stunde verschoben.');
        }
        $slot->update($data);

        return back()->with('success', 'Terminstatus wurde gespeichert.');
    }

    public function storeUnit(StorePlannedUnitRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validated();
        abort_unless($this->dateInYear($teachingGroup, $data['starts_on']) && $this->dateInYear($teachingGroup, $data['ends_on']), 422, 'Die Planung muss innerhalb des Schuljahres liegen.');
        if (! empty($data['unit_template_id'])) {
            abort_unless(UnitTemplate::where('id', $data['unit_template_id'])->where('organization_id', $teachingGroup->organization_id)->exists(), 422);
        }
        $this->validateTopicScope($teachingGroup, $data['curriculum_topic_id'] ?? null);
        $plan = $this->planFor($teachingGroup);
        DB::transaction(function () use ($plan, $data, $request): void {
            $unit = $plan->units()->create($data + ['position' => $plan->units()->max('position') + 1]);
            $this->revise($plan, $request->user()->id, 'unit_created', 'Unterrichtseinheit „'.$unit->title.'“ eingeplant.');
        });

        return back()->with('success', 'Unterrichtseinheit wurde eingeplant.');
    }

    public function updateUnit(StorePlannedUnitRequest $request, TeachingGroup $teachingGroup, PlannedUnit $plannedUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($plannedUnit->plan->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validated();
        abort_unless($this->dateInYear($teachingGroup, $data['starts_on']) && $this->dateInYear($teachingGroup, $data['ends_on']), 422);
        $this->validateTopicScope($teachingGroup, $data['curriculum_topic_id'] ?? null);
        DB::transaction(function () use ($plannedUnit, $data, $request): void {
            $plannedUnit->update($data);
            $this->revise($plannedUnit->plan, $request->user()->id, 'unit_updated', 'Unterrichtseinheit „'.$plannedUnit->title.'“ geändert.');
        });

        return back()->with('success', 'Planung wurde gespeichert.');
    }

    public function destroyUnit(TeachingGroup $teachingGroup, PlannedUnit $plannedUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($plannedUnit->plan->teaching_group_id === $teachingGroup->id, 404);
        $plan = $plannedUnit->plan;
        $title = $plannedUnit->title;
        DB::transaction(function () use ($plannedUnit, $plan, $title): void {
            $plannedUnit->delete();
            $this->revise($plan, auth()->id(), 'unit_deleted', 'Unterrichtseinheit „'.$title.'“ entfernt.');
        });

        return back()->with('success', 'Unterrichtseinheit wurde entfernt.');
    }

    public function splitUnit(SplitPlannedUnitRequest $request, TeachingGroup $teachingGroup, PlannedUnit $plannedUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($plannedUnit->plan->teaching_group_id === $teachingGroup->id, 404);
        $splitOn = $request->validated()['split_on'];
        abort_unless($splitOn > $plannedUnit->starts_on->toDateString() && $splitOn <= $plannedUnit->ends_on->toDateString(), 422, 'Der Teilungstag muss innerhalb der Einheit liegen.');
        $plan = $plannedUnit->plan;
        $originalEnd = $plannedUnit->ends_on->toDateString();
        DB::transaction(function () use ($plannedUnit, $splitOn, $plan, $originalEnd): void {
            $oldEnd = now()->parse($splitOn)->subDay()->toDateString();
            $remainingHours = max(1, (int) floor($plannedUnit->planned_hours / 2));
            $newHours = max(1, $plannedUnit->planned_hours - $remainingHours);
            $plannedUnit->update(['ends_on' => $oldEnd, 'planned_hours' => $remainingHours, 'is_interrupted' => true]);
            $plan->units()->create([
                'unit_template_id' => $plannedUnit->unit_template_id,
                'curriculum_topic_id' => $plannedUnit->curriculum_topic_id,
                'title' => $plannedUnit->title.' (Teil 2)',
                'starts_on' => $splitOn,
                'ends_on' => $originalEnd,
                'planned_hours' => $newHours,
                'position' => $plan->units()->max('position') + 1,
                'is_interrupted' => true,
                'notes' => $plannedUnit->notes,
            ]);
            $this->revise($plan, auth()->id(), 'unit_split', 'Unterrichtseinheit wurde geteilt.');
        });

        return back()->with('success', 'Unterrichtseinheit wurde geteilt.');
    }

    public function interruptUnit(InterruptPlannedUnitRequest $request, TeachingGroup $teachingGroup, PlannedUnit $plannedUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($plannedUnit->plan->teaching_group_id === $teachingGroup->id, 404);
        $plannedUnit->update($request->validated());
        $this->revise($plannedUnit->plan, auth()->id(), 'unit_interrupted', $plannedUnit->is_interrupted ? 'Unterrichtseinheit unterbrochen.' : 'Unterbrechung der Unterrichtseinheit aufgehoben.');

        return back()->with('success', 'Unterbrechung wurde gespeichert.');
    }

    public function generateLessons(TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $plan = $this->planFor($teachingGroup)->load('units');
        $dates = $this->instructionDates($teachingGroup);
        $periods = $teachingGroup->schoolPeriods()->get(['school_periods.id', 'period_number'])->mapWithKeys(fn ($period) => [$period->pivot->weekday.'-'.$period->period_number => true]);
        $available = collect($dates)->filter(fn ($date) => $periods->keys()->contains(fn ($key) => (int) explode('-', $key)[0] === $date->dayOfWeekIso));
        DB::transaction(function () use ($plan, $available): void {
            foreach ($plan->units as $unit) {
                $unitDates = $available->filter(fn ($date) => $date->toDateString() >= $unit->starts_on->toDateString() && $date->toDateString() <= $unit->ends_on->toDateString())->values();
                foreach ($unitDates->take($unit->planned_hours) as $position => $date) {
                    $lesson = $unit->lessons()->firstOrCreate(['position' => $position + 1], ['title' => $unit->title.' – '.($position + 1).'. Stunde']);
                    $lesson->occurrences()->firstOrCreate(['planned_on' => $date->toDateString()]);
                }
            }
            $this->revise($plan, auth()->id(), 'lessons_generated', 'Stunden aus dem Stundenplan erzeugt.');
        });

        return back()->with('success', 'Stunden wurden aus dem Stundenplan erzeugt.');
    }

    public function updateOccurrence(UpdateLessonOccurrenceRequest $request, TeachingGroup $teachingGroup, LessonOccurrence $occurrence): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($occurrence->plannedLesson->unit->plan->teaching_group_id === $teachingGroup->id, 404);
        $occurrence->update($request->validated());
        $this->revise($occurrence->plannedLesson->unit->plan, auth()->id(), 'occurrence_updated', 'Stundenstatus geändert.');

        return back()->with('success', 'Stundenstatus wurde gespeichert.');
    }

    private function planFor(TeachingGroup $group): GroupYearPlan
    {
        return GroupYearPlan::firstOrCreate(['teaching_group_id' => $group->id], ['organization_id' => $group->organization_id, 'school_year_id' => $group->school_year_id]);
    }

    private function dateInYear(TeachingGroup $group, string $date): bool
    {
        return $date >= $group->schoolYear->starts_on->toDateString() && $date <= $group->schoolYear->ends_on->toDateString();
    }

    private function instructionDates(TeachingGroup $group): array
    {
        $year = $group->schoolYear;
        $blocked = $year->days()->whereIn('kind', ['no_instruction', 'holiday'])->pluck('date')->map(fn ($date) => (string) $date)->all();
        $blocked = array_merge($blocked, $year->holidayPeriods()->get()->flatMap(fn ($holiday) => collect(CarbonPeriod::create($holiday->starts_on, $holiday->ends_on))->map->toDateString())->all());
        $blocked = array_merge($blocked, $year->calendarExceptions()->whereIn('kind', ['no_instruction', 'holiday'])->pluck('date')->map(fn ($date) => (string) $date)->all());

        return collect(CarbonPeriod::create($group->schoolYear->starts_on, $group->schoolYear->ends_on))
            ->filter(fn ($date) => $date->isWeekday() && ! in_array($date->toDateString(), $blocked, true))->values()->all();
    }

    private function checks(TeachingGroup $group, GroupYearPlan $plan): array
    {
        $units = $plan->units()->with('curriculumTopic.competencies')->get();
        $topics = $group->curricula()->with('versions.topics.competencies')->get()->flatMap(fn ($curriculum) => $curriculum->versions->flatMap->topics);
        $plannedTopicIds = $units->pluck('curriculum_topic_id')->filter();

        return [
            'available_hours' => $this->availableHours($group),
            'units_without_competencies' => $units->filter(fn ($unit) => ! $unit->curriculum_topic_id || $unit->curriculumTopic->competencies->isEmpty())->pluck('title')->values(),
            'topics_without_planned_unit' => $topics->whereNotIn('id', $plannedTopicIds)->pluck('title')->values(),
            'planned_competence_count' => $units->flatMap(fn ($unit) => $unit->curriculumTopic?->competencies ?? collect())->unique('id')->count(),
            'total_competence_count' => $topics->flatMap->competencies->unique('id')->count(),
            'uncovered_competencies' => $topics->whereNotIn('id', $plannedTopicIds)->flatMap->competencies->pluck('display')->filter()->unique()->values(),
        ];
    }

    private function calendar(TeachingGroup $group): array
    {
        return $group->schoolYear->days()->orderBy('date')->get(['date', 'kind', 'label'])->map(fn ($day) => [
            'date' => $day->date->toDateString(),
            'kind' => $day->kind,
            'label' => $day->label,
        ])->all();
    }

    private function validateTopicScope(TeachingGroup $group, ?int $topicId): void
    {
        if ($topicId === null) {
            return;
        }
        $curriculumIds = $group->curricula()->pluck('curricula.id');
        abort_unless(CurriculumTopic::whereKey($topicId)->whereHas('version', fn ($query) => $query->whereIn('curriculum_id', $curriculumIds))->exists(), 422, 'Das Curriculumthema gehört nicht zu dieser Unterrichtsgruppe.');
    }

    private function availableHours(TeachingGroup $group): int
    {
        $weekdays = $group->schoolPeriods()->get()->pluck('pivot.weekday')->unique()->all();

        return collect($this->instructionDates($group))->filter(fn ($date) => in_array($date->dayOfWeekIso, $weekdays, true))->count();
    }

    private function revise(GroupYearPlan $plan, int $userId, string $action, string $description, ?array $payload = null): void
    {
        $plan->increment('revision');
        $plan->revisions()->create(['user_id' => $userId, 'revision' => $plan->fresh()->revision, 'action' => $action, 'description' => $description, 'payload' => $payload]);
    }
}
