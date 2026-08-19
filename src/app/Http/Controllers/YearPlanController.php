<?php

namespace App\Http\Controllers;

use App\Http\Requests\SplitPlannedUnitRequest;
use App\Http\Requests\StorePlannedUnitRequest;
use App\Http\Requests\UpdateLessonOccurrenceRequest;
use App\Models\GroupYearPlan;
use App\Models\LessonOccurrence;
use App\Models\PlannedUnit;
use App\Models\TeachingGroup;
use App\Models\UnitTemplate;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
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

    public function show(TeachingGroup $teachingGroup): Response
    {
        $this->authorize('view', $teachingGroup);
        $plan = $this->planFor($teachingGroup)->load(['units.template:id,title', 'units.curriculumTopic:id,title', 'units.lessons.occurrences', 'revisions.user:id,name']);

        return Inertia::render('YearPlans/Show', [
            'group' => $teachingGroup->load(['school:id,name', 'schoolYear:id,name,starts_on,ends_on', 'schoolYear.days', 'timetableSlots']),
            'plan' => $plan,
            'unitTemplates' => UnitTemplate::where('organization_id', auth()->user()->organization_id)->where('is_active', true)->orderBy('title')->get(['id', 'title', 'expected_hours']),
            'checks' => $this->checks($teachingGroup, $plan),
        ]);
    }

    public function storeUnit(StorePlannedUnitRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validated();
        abort_unless($this->dateInYear($teachingGroup, $data['starts_on']) && $this->dateInYear($teachingGroup, $data['ends_on']), 422, 'Die Planung muss innerhalb des Schuljahres liegen.');
        if (! empty($data['unit_template_id'])) {
            abort_unless(UnitTemplate::where('id', $data['unit_template_id'])->where('organization_id', $teachingGroup->organization_id)->exists(), 422);
        }
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

    public function generateLessons(TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $plan = $this->planFor($teachingGroup)->load('units');
        $dates = $this->instructionDates($teachingGroup);
        $periods = $teachingGroup->schoolPeriods()->get(['school_periods.id', 'period_number'])->mapWithKeys(fn ($period) => [$period->pivot->weekday.'-'.$period->period_number => true]);
        $available = collect($dates)->filter(fn ($date) => $periods->keys()->contains(fn ($key) => (int) explode('-', $key)[0] === $date->dayOfWeekIso));
        $index = 0;
        DB::transaction(function () use ($plan, $available, &$index): void {
            foreach ($plan->units as $unit) {
                foreach ($available->slice($index, $unit->planned_hours) as $position => $date) {
                    $lesson = $unit->lessons()->firstOrCreate(['position' => $position + 1], ['title' => $unit->title.' – '.($position + 1).'. Stunde']);
                    $lesson->occurrences()->firstOrCreate(['planned_on' => $date->toDateString()]);
                    $index++;
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
        ];
    }

    private function availableHours(TeachingGroup $group): int
    {
        $weekdays = $group->schoolPeriods()->get()->pluck('pivot.weekday')->unique()->all();

        return collect($this->instructionDates($group))->filter(fn ($date) => in_array($date->dayOfWeekIso, $weekdays, true))->count();
    }

    private function revise(GroupYearPlan $plan, int $userId, string $action, string $description): void
    {
        $plan->increment('revision');
        $plan->revisions()->create(['user_id' => $userId, 'revision' => $plan->fresh()->revision, 'action' => $action, 'description' => $description]);
    }
}
