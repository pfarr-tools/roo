<?php

namespace App\Services;

use App\Models\CurriculumTopic;
use App\Models\Lesson;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class YearPlanningWorkspace
{
    public const BLOCKED_STATUSES = ['absent', 'cancelled', 'blocked'];

    public function syncSlots(TeachingGroup $group): Collection
    {
        $blocked = $this->blockedDates($group);
        $periods = $group->schoolPeriods()->get(['school_periods.period_number', 'school_periods.starts_at', 'school_periods.ends_at'])->keyBy(fn ($period) => $period->pivot->weekday.'-'.$period->period_number);
        $slots = collect();

        foreach (CarbonPeriod::create($group->schoolYear->starts_on, $group->schoolYear->ends_on) as $date) {
            $dateString = $date->toDateString();
            if ($blocked->contains($dateString)) {
                continue;
            }
            foreach ($periods as $key => $period) {
                [$weekday, $periodNumber] = explode('-', $key);
                if ((int) $weekday !== $date->dayOfWeekIso) {
                    continue;
                }
                $slot = ScheduleSlot::where('teaching_group_id', $group->id)
                    ->whereDate('date', $dateString)
                    ->where('period_number', $periodNumber)
                    ->first();
                $slots->push($slot ?? ScheduleSlot::create([
                    'teaching_group_id' => $group->id,
                    'date' => $dateString,
                    'period_number' => $periodNumber,
                    'starts_at' => $period->starts_at,
                    'ends_at' => $period->ends_at,
                    'status' => 'free',
                ]));
            }
        }

        return $slots->sortBy(fn (ScheduleSlot $slot) => $slot->date->timestamp.'-'.$slot->period_number)->values();
    }

    public function blockedDates(TeachingGroup $group): Collection
    {
        $year = $group->schoolYear;
        $dates = $year->days()->whereIn('kind', ['no_instruction', 'holiday'])->pluck('date')->map(fn ($date) => (string) $date);
        $dates = $dates->merge($year->calendarExceptions()->whereIn('kind', ['no_instruction', 'holiday'])->pluck('date')->map(fn ($date) => (string) $date));
        foreach ($year->holidayPeriods()->get(['starts_on', 'ends_on']) as $holiday) {
            $dates = $dates->merge(collect(CarbonPeriod::create($holiday->starts_on, $holiday->ends_on))->map->toDateString());
        }

        return $dates->unique()->values();
    }

    public function importCurriculumUnit(TeachingGroup $group, CurriculumTopic $topic): TeachingUnit
    {
        return DB::transaction(function () use ($group, $topic): TeachingUnit {
            $unit = $group->teachingUnits()->create([
                'organization_id' => $group->organization_id,
                'source_curriculum_topic_id' => $topic->id,
                'title' => $topic->title,
                'position' => ($group->teachingUnits()->max('position') ?? 0) + 1,
                'notes' => $topic->notes,
            ]);

            foreach ($topic->competencies as $competency) {
                $unit->competencies()->create([
                    'education_plan_competency_id' => $competency->education_plan_competency_id,
                    'curriculum_topic_competency_id' => $competency->id,
                    'source_curriculum_topic_id' => $topic->id,
                    'local_wording' => null,
                ]);
            }

            $hours = max(1, (int) ($topic->hours ?? 1));
            foreach (range(1, $hours) as $position) {
                $unit->lessons()->create(['title' => $topic->title.' – '.$position.'. Stunde', 'position' => $position, 'duration' => 1]);
            }

            return $unit->load(['competencies.curriculumCompetency', 'lessons']);
        });
    }

    public function availableSlots(TeachingGroup $group): Collection
    {
        return $this->syncSlots($group)->filter(fn (ScheduleSlot $slot) => $slot->status === 'free' && ! $slot->scheduledLesson()->exists())->values();
    }

    public function scheduleLesson(TeachingGroup $group, Lesson $lesson, ?ScheduleSlot $start = null): array
    {
        abort_unless($lesson->unit->teaching_group_id === $group->id, 404);
        ScheduledLesson::where('lesson_id', $lesson->id)->delete();
        $slots = $this->availableSlots($group);
        if ($start) {
            $index = $slots->search(fn (ScheduleSlot $slot) => $slot->id === $start->id);
            abort_unless($index !== false, 422, 'Der Zielslot ist nicht verfügbar.');
            $slots = $slots->slice($index)->values();
        }
        $selected = $slots->take($lesson->duration);
        if ($selected->count() < $lesson->duration) {
            return ['scheduled' => 0, 'overflow' => $lesson->duration - $selected->count()];
        }
        DB::transaction(function () use ($lesson, $selected): void {
            foreach ($selected as $slot) {
                ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
            }
        });

        return ['scheduled' => $selected->count(), 'overflow' => 0];
    }

    public function scheduleUnit(TeachingGroup $group, TeachingUnit $unit, ?ScheduleSlot $start = null): array
    {
        abort_unless($unit->teaching_group_id === $group->id, 404);
        ScheduledLesson::whereIn('lesson_id', $unit->lessons()->pluck('id'))->delete();
        $slots = $this->availableSlots($group);
        if ($start) {
            $index = $slots->search(fn (ScheduleSlot $slot) => $slot->id === $start->id);
            abort_unless($index !== false, 422, 'Der Zielslot ist nicht verfügbar.');
            $slots = $slots->slice($index)->values();
        }
        $scheduled = 0;
        foreach ($unit->lessons()->orderBy('position')->get() as $lesson) {
            $selected = $slots->slice($scheduled, $lesson->duration);
            if ($selected->count() < $lesson->duration) {
                return ['scheduled' => $scheduled, 'overflow' => $lesson->duration - $selected->count()];
            }
            foreach ($selected as $slot) {
                ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
                $scheduled++;
            }
        }

        return ['scheduled' => $scheduled, 'overflow' => 0];
    }

    /**
     * Insert a lesson or unit at a calendar position and shift the following
     * schedule to the right. A lesson may therefore occupy two separate
     * ranges temporarily; adjacency is derived from the slot order and needs
     * no additional persisted "part" records.
     */
    public function insertAtSlot(TeachingGroup $group, string $type, int $sourceId, ScheduleSlot $target): array
    {
        abort_unless($target->teaching_group_id === $group->id, 404);

        $sourceLessons = match ($type) {
            'lesson' => Lesson::whereKey($sourceId)
                ->whereHas('unit', fn ($query) => $query->where('teaching_group_id', $group->id))
                ->get(),
            'unit' => TeachingUnit::whereKey($sourceId)
                ->where('teaching_group_id', $group->id)
                ->with(['lessons' => fn ($query) => $query->orderBy('position')])
                ->firstOrFail()
                ->lessons,
            default => abort(422, 'Ungültiger Einfügetyp.'),
        };
        abort_unless($sourceLessons->isNotEmpty(), 404);

        $slots = ScheduleSlot::where('teaching_group_id', $group->id)
            ->with('scheduledLesson')
            ->orderBy('date')
            ->orderBy('period_number')
            ->get()
            ->filter(fn (ScheduleSlot $slot) => in_array($slot->status, ['free', 'buffer'], true) || $slot->scheduledLesson)
            ->values();
        $targetIndex = $slots->search(fn (ScheduleSlot $slot) => $slot->id === $target->id);
        abort_unless($targetIndex !== false, 422, 'Der Zielslot ist nicht verfügbar.');

        $block = $sourceLessons->flatMap(fn (Lesson $lesson) => array_fill(0, max(1, (int) $lesson->duration), $lesson->id))->values()->all();
        $sourceIds = $sourceLessons->pluck('id')->map(fn ($id) => (int) $id)->all();
        $tokens = $slots->map(fn (ScheduleSlot $slot) => $slot->scheduledLesson?->lesson_id)->all();
        $sourceIndexes = collect($tokens)->keys()->filter(fn ($index) => in_array((int) ($tokens[$index] ?? 0), $sourceIds, true))->values();

        foreach ($tokens as $index => $lessonId) {
            if ($lessonId !== null && in_array((int) $lessonId, $sourceIds, true)) {
                $tokens[$index] = null;
            }
        }

        $length = count($block);
        $sourceBeforeTarget = $sourceIndexes->isNotEmpty() && $sourceIndexes->min() < $targetIndex;
        if (! $sourceBeforeTarget) {
            abort_unless($targetIndex + $length <= count($tokens), 422, 'Für diese Einfügung sind nicht genügend Termine vorhanden.');
            $tail = array_slice($tokens, -$length);
            abort_unless(collect($tail)->every(fn ($lessonId) => $lessonId === null), 422, 'Für diese Einfügung sind nicht genügend Termine vorhanden.');
        } else {
            $firstSourceIndex = $sourceIndexes->min();
            $segment = array_values(array_filter(array_slice($tokens, $firstSourceIndex, $targetIndex - $firstSourceIndex + 1), fn ($lessonId) => $lessonId !== null));
            abort_unless($firstSourceIndex + count($segment) + $length <= count($tokens), 422, 'Für diese Einfügung sind nicht genügend Termine vorhanden.');
        }

        return DB::transaction(function () use ($group, $slots, $tokens, $block, $targetIndex, $length, $sourceBeforeTarget, $sourceIndexes): array {
            if ($sourceBeforeTarget) {
                $firstSourceIndex = $sourceIndexes->min();
                $segment = array_values(array_filter(array_slice($tokens, $firstSourceIndex, $targetIndex - $firstSourceIndex + 1), fn ($lessonId) => $lessonId !== null));
                $insertionIndex = $firstSourceIndex + count($segment);
                $extra = max(0, $insertionIndex + $length - ($targetIndex + 1));
                for ($index = count($tokens) - 1; $index >= $targetIndex + 1 + $extra; $index--) {
                    $tokens[$index] = $tokens[$index - $extra];
                }
                for ($index = $firstSourceIndex; $index < $insertionIndex + $length; $index++) {
                    $tokens[$index] = null;
                }
                foreach ($segment as $offset => $lessonId) {
                    $tokens[$firstSourceIndex + $offset] = $lessonId;
                }
                $targetIndex = $insertionIndex;
            } else {
                for ($index = count($tokens) - 1; $index >= $targetIndex + $length; $index--) {
                    $tokens[$index] = $tokens[$index - $length];
                }
            }
            foreach ($block as $offset => $lessonId) {
                $tokens[$targetIndex + $offset] = $lessonId;
            }

            $lessonIds = $group->teachingUnits()->with('lessons')->get()->flatMap->lessons->pluck('id');
            ScheduledLesson::whereIn('lesson_id', $lessonIds)->delete();
            foreach ($tokens as $index => $lessonId) {
                if ($lessonId !== null) {
                    ScheduledLesson::create(['lesson_id' => $lessonId, 'schedule_slot_id' => $slots[$index]->id]);
                }
            }

            return ['scheduled' => $length, 'overflow' => 0];
        });
    }

    public function blockAndReflow(TeachingGroup $group, ScheduleSlot $slot, string $status): array
    {
        return DB::transaction(function () use ($group, $slot, $status): array {
            $slot->update(['status' => $status]);
            $allSlots = ScheduleSlot::where('teaching_group_id', $group->id)
                ->with('scheduledLesson')
                ->orderBy('date')
                ->orderBy('period_number')
                ->get();
            $changedIndex = $allSlots->search(fn (ScheduleSlot $candidate) => $candidate->id === $slot->id);
            abort_unless($changedIndex !== false, 404);

            $eligible = $allSlots->filter(fn (ScheduleSlot $candidate) => in_array($candidate->status, ['free', 'buffer'], true))->values();
            $suffixSlots = $allSlots->slice($changedIndex)->values();
            $suffixLessonIds = $suffixSlots->map(fn (ScheduleSlot $candidate) => $candidate->scheduledLesson?->lesson_id)->filter()->values();
            $suffixEligible = $eligible->filter(fn (ScheduleSlot $candidate) => $candidate->date->gt($slot->date) || ($candidate->date->equalTo($slot->date) && $candidate->period_number >= $slot->period_number))->values();
            $prefixAssignments = $allSlots->slice(0, $changedIndex)
                ->filter(fn (ScheduleSlot $candidate) => $candidate->scheduledLesson && in_array($candidate->status, ['free', 'buffer'], true))
                ->map(fn (ScheduleSlot $candidate) => [$candidate->scheduledLesson->lesson_id, $candidate->id])
                ->values();
            $suffixAssignments = $suffixEligible->take($suffixLessonIds->count())->values()->map(fn (ScheduleSlot $candidate, int $index) => [$suffixLessonIds[$index], $candidate->id]);
            $assignments = $prefixAssignments->concat($suffixAssignments)->values();
            $lessonIds = $group->teachingUnits()->with('lessons')->get()->flatMap->lessons->pluck('id');
            ScheduledLesson::whereIn('lesson_id', $lessonIds)->delete();
            foreach ($assignments as [$lessonId, $slotId]) {
                ScheduledLesson::create(['lesson_id' => $lessonId, 'schedule_slot_id' => $slotId]);
            }

            return ['scheduled' => $suffixLessonIds->count() - max(0, $suffixLessonIds->count() - $suffixEligible->count()), 'overflow' => max(0, $suffixLessonIds->count() - $suffixEligible->count())];
        });
    }

    public function coverage(TeachingGroup $group): array
    {
        $units = $group->teachingUnits()->with(['competencies', 'lessons.competencies'])->get();
        $unitCompetencies = $units->flatMap->competencies;
        $lessonCompetencies = $units->flatMap(fn ($unit) => $unit->lessons->flatMap->competencies);
        $plannedEducation = $unitCompetencies->pluck('education_plan_competency_id')->filter()->unique()->values();
        $lessonEducation = $lessonCompetencies->pluck('education_plan_competency_id')->filter()->unique()->values();
        $curriculumCompetencies = $group->curricula()->with('versions.topics.competencies')->get()->flatMap(fn ($curriculum) => $curriculum->versions->flatMap->topics)->flatMap->competencies;
        $curriculumIds = $curriculumCompetencies->pluck('id')->unique();
        $coveredEducationIds = $unitCompetencies->pluck('education_plan_competency_id')->filter()->unique();
        $coveredCurriculumIds = $curriculumCompetencies->filter(fn ($competency) => $coveredEducationIds->contains($competency->education_plan_competency_id) || $unitCompetencies->pluck('curriculum_topic_competency_id')->contains($competency->id))->pluck('id')->unique();

        return [
            'teaching_unit_competencies' => $unitCompetencies->count(),
            'lesson_competencies' => $lessonCompetencies->count(),
            'education_plan_planned' => $plannedEducation->count(),
            'education_plan_lesson' => $lessonEducation->count(),
            'education_plan_total' => $curriculumCompetencies->pluck('education_plan_competency_id')->filter()->unique()->count(),
            'curriculum_covered' => $coveredCurriculumIds->intersect($curriculumIds)->count(),
            'curriculum_total' => $curriculumIds->count(),
        ];
    }
}
