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
    public const BLOCKED_STATUSES = ['absent', 'cancelled', 'blocked', 'lse'];

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
                'education_plan_id' => $topic->loadMissing('version.bindings')->version?->bindings->firstWhere('education_plan_id', '!=', null)?->education_plan_id,
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

    public function copyTeachingUnit(TeachingGroup $targetGroup, TeachingUnit $source): TeachingUnit
    {
        abort_unless($source->organization_id === $targetGroup->organization_id, 404);

        return DB::transaction(function () use ($targetGroup, $source): TeachingUnit {
            $source->load(['competencies', 'lessons' => fn ($query) => $query->orderBy('position'), 'lessons.competencies', 'lessons.phases' => fn ($query) => $query->orderBy('position')]);
            $copy = $targetGroup->teachingUnits()->create([
                'organization_id' => $targetGroup->organization_id,
                'education_plan_id' => $source->education_plan_id,
                'copied_from_id' => $source->id,
                'source_curriculum_topic_id' => $source->source_curriculum_topic_id,
                'title' => $source->title,
                'keyword' => $source->keyword,
                'position' => ($targetGroup->teachingUnits()->max('position') ?? 0) + 1,
                'notes' => $source->notes,
            ]);
            $competencies = [];
            foreach ($source->competencies as $competency) {
                $copyCompetency = $copy->competencies()->create($competency->only(['education_plan_competency_id', 'curriculum_topic_competency_id', 'source_curriculum_topic_id', 'local_wording', 'is_secondary']));
                $competencies[$competency->id] = $copyCompetency;
            }
            foreach ($source->lessons as $lesson) {
                $lessonCopy = $copy->lessons()->create($lesson->only(['title', 'duration', 'position', 'learning_goals', 'materials', 'homework', 'assessment_note', 'notes']));
                $lessonCopy->competencies()->sync(collect($lesson->competencies)->map(fn ($competency) => $competencies[$competency->id]?->id)->filter()->all());
                foreach ($lesson->phases as $phase) {
                    $lessonCopy->phases()->create($phase->only(['title', 'position', 'duration_minutes', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'materials', 'media']));
                }
            }

            return $copy->load(['competencies', 'lessons.phases']);
        });
    }

    public function availableSlots(TeachingGroup $group): Collection
    {
        return $this->syncSlots($group)->filter(fn (ScheduleSlot $slot) => $slot->status === 'free' && ! $slot->is_pinned && ! $slot->scheduledLesson()->exists())->values();
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
        $ritualsAdded = $this->ensureGroupRituals($group, $lesson, $selected->first());
        DB::transaction(function () use ($lesson, $selected, $ritualsAdded): void {
            foreach ($selected as $slot) {
                ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id, 'status' => $ritualsAdded ? ScheduledLesson::STATUS_PLANNED : ScheduledLesson::STATUS_ASSIGNED]);
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
            $ritualsAdded = $this->ensureGroupRituals($group, $lesson, $selected->first());
            foreach ($selected as $slot) {
                ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id, 'status' => $ritualsAdded ? ScheduledLesson::STATUS_PLANNED : ScheduledLesson::STATUS_ASSIGNED]);
                $scheduled++;
            }
        }

        return ['scheduled' => $scheduled, 'overflow' => 0];
    }

    public function autoPlan(TeachingGroup $group, ?ScheduleSlot $start, bool $keepTogether): array
    {
        $units = $group->teachingUnits()->with(['lessons' => fn ($query) => $query->orderBy('position'), 'lessons.scheduledLessons'])->orderBy('position')->get();
        $units = $units->filter(fn (TeachingUnit $unit) => $unit->lessons->isNotEmpty() && $unit->lessons->every(fn (Lesson $lesson) => $lesson->scheduledLessons->isEmpty()))->values();
        if ($units->isEmpty()) {
            return ['planned' => 0, 'overflow' => 0];
        }

        return DB::transaction(function () use ($group, $start, $keepTogether, $units): array {
            $slots = $this->availableSlots($group);
            $startIndex = $start ? $slots->search(fn (ScheduleSlot $slot) => $slot->id === $start->id) : 0;
            abort_unless($startIndex !== false, 422, 'Der Startslot ist nicht frei.');
            $cursor = $startIndex;
            $planned = 0;
            $overflow = 0;

            foreach ($units as $unit) {
                $lessons = $unit->lessons;
                if ($keepTogether) {
                    $duration = (int) $lessons->sum('duration');
                    $target = $slots[$cursor] ?? null;
                    if (! $target) {
                        $overflow += $duration;

                        continue;
                    }
                    $result = $this->insertAtSlot($group, 'unit', $unit->id, $target);
                    $planned += $result['scheduled'];
                    $overflow += $result['overflow'];
                    $slots = $this->availableSlots($group);
                    $cursor = $slots->search(fn (ScheduleSlot $slot) => $slot->date->gt($target->date) || ($slot->date->equalTo($target->date) && $slot->period_number > $target->period_number));
                    if ($cursor === false) {
                        $cursor = $slots->count();
                    }
                } else {
                    foreach ($lessons as $lesson) {
                        for ($offset = 0; $offset < $lesson->duration; $offset++) {
                            $target = $slots[$cursor] ?? null;
                            if (! $target) {
                                $overflow++;

                                continue;
                            }
                            $ritualsAdded = $offset === 0 && $this->ensureGroupRituals($group, $lesson, $target);
                            ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $target->id, 'status' => $ritualsAdded ? ScheduledLesson::STATUS_PLANNED : ScheduledLesson::STATUS_ASSIGNED]);
                            $planned++;
                            $cursor++;
                        }
                    }
                }
            }

            return ['planned' => $planned, 'overflow' => $overflow];
        });
    }

    private function ensureGroupRituals(TeachingGroup $group, Lesson $lesson, ?ScheduleSlot $start = null): bool
    {
        if ($start && ! $this->startsNewGroupHour($group, $start)) {
            return false;
        }
        $group->loadMissing('rituals.phaseTemplate');
        $existing = $lesson->phases()->whereNotNull('phase_template_id')->pluck('phase_template_id')->all();
        $position = (int) $lesson->phases()->max('position');
        $added = false;
        foreach ($group->rituals as $ritual) {
            $template = $ritual->phaseTemplate;
            if (! $template || in_array($template->id, $existing, true)) {
                continue;
            }
            $lesson->phases()->create([
                'phase_template_id' => $template->id,
                'title' => $template->title,
                'position' => ++$position,
                'duration_minutes' => $template->duration_minutes,
                'social_form_id' => $template->social_form_id,
                'description' => $template->description,
                'materials' => $template->material,
            ]);
            $existing[] = $template->id;
            $added = true;
        }

        return $added;
    }

    private function startsNewGroupHour(TeachingGroup $group, ScheduleSlot $start): bool
    {
        $previous = ScheduleSlot::where('teaching_group_id', $group->id)
            ->where(function ($query) use ($start): void {
                $query->whereDate('date', '<', $start->date)
                    ->orWhere(fn ($nested) => $nested->whereDate('date', $start->date)->where('period_number', '<', $start->period_number));
            })
            ->orderByDesc('date')
            ->orderByDesc('period_number')
            ->first();

        return ! $previous || ! $previous->scheduledLesson()->exists();
    }

    /**
     * Insert a lesson or unit at a calendar position and shift the following
     * schedule to the right. A lesson may therefore occupy two separate
     * ranges temporarily; adjacency is derived from the slot order and needs
     * no additional persisted "part" records.
     */
    public function insertAtSlot(TeachingGroup $group, string $type, int $sourceId, ScheduleSlot $target, bool $allowOverflow = false, bool $pullFollowing = false): array
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
            ->filter(fn (ScheduleSlot $slot) => $slot->status !== 'buffer' && (in_array($slot->status, ['free'], true) || $slot->scheduledLesson))
            ->values();
        abort_unless($target->status !== 'buffer', 422, 'Pufferzeiten bleiben frei.');
        $targetIndex = $slots->search(fn (ScheduleSlot $slot) => $slot->id === $target->id);
        abort_unless($targetIndex !== false, 422, 'Der Zielslot ist nicht verfügbar.');

        $plannedSourceIds = $slots->filter(fn (ScheduleSlot $slot) => $slot->scheduledLesson && $sourceLessons->contains('id', $slot->scheduledLesson->lesson_id))->pluck('scheduledLesson.lesson_id')->map(fn ($id) => (int) $id)->unique()->all();
        if ($type === 'unit' && $plannedSourceIds !== []) {
            $lessonsById = $sourceLessons->keyBy('id');
            $sourceLessons = collect($plannedSourceIds)->map(fn (int $lessonId) => $lessonsById->get($lessonId))->filter()->values();
        }
        $pinnedSourceIds = $slots->filter(fn (ScheduleSlot $slot) => $slot->is_pinned && $slot->scheduledLesson && $sourceLessons->contains('id', $slot->scheduledLesson->lesson_id))->pluck('scheduledLesson.lesson_id')->map(fn ($id) => (int) $id)->all();
        abort_unless($type === 'unit' || $pinnedSourceIds === [], 422, 'Eine fixierte Stunde kann nicht einzeln verschoben werden.');
        $movableSourceLessons = $sourceLessons->reject(fn (Lesson $lesson) => in_array((int) $lesson->id, $pinnedSourceIds, true));
        $block = $movableSourceLessons->flatMap(fn (Lesson $lesson) => array_fill(0, max(1, (int) $lesson->duration), $lesson->id))->values()->all();
        $sourceIds = $movableSourceLessons->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($block === []) {
            return ['scheduled' => 0, 'overflow' => 0];
        }
        abort_unless(! $target->is_pinned, 422, 'Der Zielslot ist fixiert.');
        if ($slots->contains(fn (ScheduleSlot $slot) => $slot->is_pinned)) {
            $movableIndexes = $slots->keys()->filter(fn (int $index) => ! $slots[$index]->is_pinned)->values()->all();
            $targetMovableIndex = array_search($targetIndex, $movableIndexes, true);
            abort_unless($targetMovableIndex !== false, 422, 'Der Zielslot ist fixiert.');
            $movableTokens = array_map(fn (int $index) => $slots[$index]->scheduledLesson?->lesson_id, $movableIndexes);
            $movableTokens = array_map(fn ($lessonId) => in_array((int) $lessonId, $sourceIds, true) ? null : $lessonId, $movableTokens);
            $freeRun = 0;
            for ($index = $targetMovableIndex; $index < count($movableTokens) && $movableTokens[$index] === null; $index++) {
                $freeRun++;
            }
            if (! $target->scheduledLesson && $freeRun >= count($block)) {
                return DB::transaction(function () use ($sourceIds, $block, $slots, $movableIndexes, $targetMovableIndex): array {
                    ScheduledLesson::whereIn('lesson_id', $sourceIds)->delete();
                    foreach ($block as $offset => $lessonId) {
                        ScheduledLesson::create(['lesson_id' => $lessonId, 'schedule_slot_id' => $slots[$movableIndexes[$targetMovableIndex + $offset]]->id]);
                    }

                    return ['scheduled' => count($block), 'overflow' => 0];
                });
            }
            $length = count($block);
            array_splice($movableTokens, $targetMovableIndex, 0, $block);
            $overflow = count(array_filter(array_slice($movableTokens, count($movableIndexes)), fn ($lessonId) => $lessonId !== null));
            if ($overflow > 0 && ! $allowOverflow) {
                return ['scheduled' => 0, 'overflow' => $overflow, 'requires_confirmation' => true];
            }
            $movableTokens = array_slice($movableTokens, 0, count($movableIndexes));

            return DB::transaction(function () use ($group, $slots, $movableIndexes, $movableTokens, $length, $overflow): array {
                $lessonIds = $group->teachingUnits()->with('lessons')->get()->flatMap->lessons->pluck('id');
                ScheduledLesson::whereIn('lesson_id', $lessonIds)->delete();
                foreach ($movableTokens as $index => $lessonId) {
                    if ($lessonId !== null) {
                        ScheduledLesson::create(['lesson_id' => $lessonId, 'schedule_slot_id' => $slots[$movableIndexes[$index]]->id]);
                    }
                }
                foreach ($slots->filter(fn (ScheduleSlot $slot) => $slot->is_pinned && $slot->scheduledLesson) as $slot) {
                    ScheduledLesson::create(['lesson_id' => $slot->scheduledLesson->lesson_id, 'schedule_slot_id' => $slot->id]);
                }

                return ['scheduled' => $length - $overflow, 'overflow' => $overflow];
            });
        }
        $sourceIndexes = $slots->keys()->filter(fn ($index) => in_array((int) ($slots[$index]->scheduledLesson?->lesson_id ?? 0), $sourceIds, true))->values();
        $firstSourceIndex = $sourceIndexes->min();
        abort_unless($firstSourceIndex === null || ! $slots->slice($firstSourceIndex + 1, max(0, $targetIndex - $firstSourceIndex))->contains(fn (ScheduleSlot $slot) => $slot->is_pinned), 422, 'Eine fixierte Stunde kann nicht verschoben werden.');
        $nextPinned = $slots->slice($targetIndex)->search(fn (ScheduleSlot $candidate) => $candidate->is_pinned);
        $freeRun = $slots->slice($targetIndex)->takeWhile(fn (ScheduleSlot $candidate) => ! $candidate->is_pinned && (! $candidate->scheduledLesson || in_array((int) $candidate->scheduledLesson->lesson_id, $sourceIds, true)))->count();
        if ($nextPinned !== false) {
            $beforePinned = $slots->slice($targetIndex, $nextPinned)->filter(fn (ScheduleSlot $candidate) => ! $candidate->scheduledLesson || in_array((int) $candidate->scheduledLesson->lesson_id, $sourceIds, true));
            abort_unless($beforePinned->count() >= count($block), 422, 'Die Einfügung würde eine fixierte Stunde verschieben.');
        }
        if (! $target->scheduledLesson && $freeRun >= count($block) && ! $pullFollowing) {
            return DB::transaction(function () use ($sourceIds, $block, $slots, $targetIndex): array {
                ScheduledLesson::whereIn('lesson_id', $sourceIds)->delete();
                foreach ($block as $offset => $lessonId) {
                    ScheduledLesson::create(['lesson_id' => $lessonId, 'schedule_slot_id' => $slots[$targetIndex + $offset]->id]);
                }

                return ['scheduled' => count($block), 'overflow' => 0];
            });
        }
        $tokens = $slots->map(fn (ScheduleSlot $slot) => $slot->scheduledLesson?->lesson_id)->all();
        $overflow = 0;
        $sourceIndexes = collect($tokens)->keys()->filter(fn ($index) => in_array((int) ($tokens[$index] ?? 0), $sourceIds, true))->values();

        foreach ($tokens as $index => $lessonId) {
            if ($lessonId !== null && in_array((int) $lessonId, $sourceIds, true)) {
                $tokens[$index] = null;
            }
        }

        $length = count($block);
        $sourceBeforeTarget = $sourceIndexes->isNotEmpty() && $sourceIndexes->min() < $targetIndex;
        if ($sourceIndexes->isNotEmpty() && $targetIndex >= $sourceIndexes->min() && $targetIndex <= $sourceIndexes->max()) {
            $overflow = max(0, $targetIndex + $length - count($tokens));
            if ($overflow > 0 && ! $allowOverflow) {
                return ['scheduled' => 0, 'overflow' => $overflow, 'requires_confirmation' => true];
            }

            return DB::transaction(function () use ($group, $slots, $tokens, $block, $targetIndex, $length, $overflow): array {
                foreach ($block as $offset => $lessonId) {
                    if (isset($slots[$targetIndex + $offset])) {
                        $tokens[$targetIndex + $offset] = $lessonId;
                    }
                }

                $lessonIds = $group->teachingUnits()->with('lessons')->get()->flatMap->lessons->pluck('id');
                ScheduledLesson::whereIn('lesson_id', $lessonIds)->delete();
                foreach (array_slice($tokens, 0, count($slots)) as $index => $lessonId) {
                    if ($lessonId !== null) {
                        ScheduledLesson::create(['lesson_id' => $lessonId, 'schedule_slot_id' => $slots[$index]->id]);
                    }
                }

                return ['scheduled' => $length - $overflow, 'overflow' => $overflow];
            });
        }
        if ($pullFollowing && $sourceIndexes->isNotEmpty() && ! $sourceBeforeTarget) {
            for ($index = $targetIndex + $length; $index < count($tokens); $index++) {
                $tokens[$index - $length] = $tokens[$index];
            }
            for ($index = max($targetIndex, count($tokens) - $length); $index < count($tokens); $index++) {
                $tokens[$index] = null;
            }
            foreach ($block as $offset => $lessonId) {
                if (isset($slots[$targetIndex + $offset])) {
                    $tokens[$targetIndex + $offset] = $lessonId;
                }
            }

            return DB::transaction(function () use ($group, $slots, $tokens): array {
                $lessonIds = $group->teachingUnits()->with('lessons')->get()->flatMap->lessons->pluck('id');
                ScheduledLesson::whereIn('lesson_id', $lessonIds)->delete();
                foreach ($tokens as $index => $lessonId) {
                    if ($lessonId !== null) {
                        ScheduledLesson::create(['lesson_id' => $lessonId, 'schedule_slot_id' => $slots[$index]->id]);
                    }
                }

                return ['scheduled' => collect($tokens)->filter()->count(), 'overflow' => 0];
            });
        }
        if (! $sourceBeforeTarget) {
            $overflow = max(0, $targetIndex + $length - count($tokens)) + collect(array_slice($tokens, max($targetIndex, count($tokens) - $length)))->filter()->count();
            if ($overflow > 0 && ! $allowOverflow) {
                return ['scheduled' => 0, 'overflow' => $overflow, 'requires_confirmation' => true];
            }
        } else {
            $firstSourceIndex = $sourceIndexes->min();
            $segment = array_values(array_filter(array_slice($tokens, $firstSourceIndex, $targetIndex - $firstSourceIndex + 1), fn ($lessonId) => $lessonId !== null));
            $overflow = max(0, $firstSourceIndex + count($segment) + $length - count($tokens), $targetIndex + $length - count($tokens));
            if ($overflow > 0 && ! $allowOverflow) {
                return ['scheduled' => 0, 'overflow' => $overflow, 'requires_confirmation' => true];
            }
        }

        $directOverflowPlacement = $sourceBeforeTarget && $targetIndex + $length > count($tokens);

        return DB::transaction(function () use ($group, $slots, $tokens, $block, $targetIndex, $length, $sourceBeforeTarget, $sourceIndexes, $overflow, $directOverflowPlacement): array {
            if ($directOverflowPlacement) {
                foreach ($block as $offset => $lessonId) {
                    if (isset($slots[$targetIndex + $offset])) {
                        $tokens[$targetIndex + $offset] = $lessonId;
                    }
                }
            } elseif ($sourceBeforeTarget) {
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
                if (! isset($slots[$targetIndex + $offset])) {
                    continue;
                }
                $tokens[$targetIndex + $offset] = $lessonId;
            }

            $lessonIds = $group->teachingUnits()->with('lessons')->get()->flatMap->lessons->pluck('id');
            ScheduledLesson::whereIn('lesson_id', $lessonIds)->delete();
            foreach (array_slice($tokens, 0, count($slots)) as $index => $lessonId) {
                if ($lessonId !== null) {
                    ScheduledLesson::create(['lesson_id' => $lessonId, 'schedule_slot_id' => $slots[$index]->id]);
                }
            }

            return ['scheduled' => $length - $overflow, 'overflow' => $overflow];
        });
    }

    public function removeScheduled(TeachingGroup $group, string $type, int $sourceId, bool $moveFollowing): void
    {
        $sourceLessons = match ($type) {
            'lesson' => Lesson::whereKey($sourceId)->whereHas('unit', fn ($query) => $query->where('teaching_group_id', $group->id))->get(),
            'unit' => TeachingUnit::whereKey($sourceId)->where('teaching_group_id', $group->id)->with('lessons')->firstOrFail()->lessons,
            default => abort(422, 'Ungültiger Entfernungstyp.'),
        };
        abort_unless($sourceLessons->isNotEmpty(), 404);
        $sourceIds = $sourceLessons->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allSlots = ScheduleSlot::where('teaching_group_id', $group->id)->with('scheduledLesson')->orderBy('date')->orderBy('period_number')->get();
        $sourceIndex = $allSlots->search(fn (ScheduleSlot $slot) => in_array((int) ($slot->scheduledLesson?->lesson_id ?? 0), $sourceIds, true));

        DB::transaction(function () use ($group, $sourceIds, $allSlots, $sourceIndex, $moveFollowing): void {
            ScheduledLesson::whereIn('lesson_id', $sourceIds)->delete();
            if (! $moveFollowing || $sourceIndex === false) {
                return;
            }

            $allSlots = ScheduleSlot::where('teaching_group_id', $group->id)->with('scheduledLesson')->orderBy('date')->orderBy('period_number')->get();

            $start = $sourceIndex + ($allSlots[$sourceIndex]->is_pinned ? 1 : 0);
            $barrier = $allSlots->slice($start)->search(fn (ScheduleSlot $slot) => $slot->is_pinned);
            $length = $barrier === false ? $allSlots->count() - $start : $barrier;
            $segment = $allSlots->slice($start, $length)->values();
            $tokens = $segment->map(fn (ScheduleSlot $slot) => $slot->scheduledLesson?->lesson_id)->filter()->values();
            $eligible = $segment->filter(fn (ScheduleSlot $slot) => ! $slot->is_pinned && $slot->status === 'free')->values();
            $preserved = $allSlots->reject(fn (ScheduleSlot $slot) => $segment->contains('id', $slot->id))->filter(fn (ScheduleSlot $slot) => $slot->scheduledLesson)->map(fn (ScheduleSlot $slot) => [$slot->scheduledLesson->lesson_id, $slot->id]);
            $assignments = $preserved->concat($eligible->take($tokens->count())->values()->map(fn (ScheduleSlot $slot, int $index) => [$tokens[$index], $slot->id]));
            $lessonIds = $group->teachingUnits()->with('lessons')->get()->flatMap->lessons->pluck('id');
            ScheduledLesson::whereIn('lesson_id', $lessonIds)->delete();
            foreach ($assignments as [$lessonId, $slotId]) {
                ScheduledLesson::create(['lesson_id' => $lessonId, 'schedule_slot_id' => $slotId]);
            }
        });
    }

    public function blockAndReflow(TeachingGroup $group, ScheduleSlot $slot, string $status, string $mode = 'move'): array
    {
        return DB::transaction(function () use ($group, $slot, $status, $mode): array {
            abort_unless(! $slot->is_pinned || $mode === 'remove', 422, 'Der fixierte Slot kann nicht automatisch verschoben werden.');
            $slot->update(['status' => $status]);
            if ($mode === 'remove') {
                $lessonId = $slot->scheduledLesson?->lesson_id;
                if ($lessonId) {
                    ScheduledLesson::where('lesson_id', $lessonId)->delete();
                }

                return ['scheduled' => 0, 'overflow' => 0];
            }
            $allSlots = ScheduleSlot::where('teaching_group_id', $group->id)
                ->with('scheduledLesson')
                ->orderBy('date')
                ->orderBy('period_number')
                ->get();
            $changedIndex = $allSlots->search(fn (ScheduleSlot $candidate) => $candidate->id === $slot->id);
            abort_unless($changedIndex !== false, 404);

            $nextPinned = $allSlots->slice($changedIndex + 1)->search(fn (ScheduleSlot $candidate) => $candidate->is_pinned);
            $segmentLength = $nextPinned === false ? $allSlots->count() - $changedIndex : $nextPinned;
            $segmentSlots = $allSlots->slice($changedIndex, $segmentLength)->values();
            $eligible = $segmentSlots->filter(fn (ScheduleSlot $candidate) => ! $candidate->is_pinned && $candidate->status === 'free')->values();
            $suffixSlots = $segmentSlots;
            $suffixLessonIds = $suffixSlots->map(fn (ScheduleSlot $candidate) => $candidate->scheduledLesson?->lesson_id)->filter()->values();
            $suffixEligible = $eligible;
            $prefixAssignments = $allSlots->slice(0, $changedIndex)
                ->filter(fn (ScheduleSlot $candidate) => $candidate->scheduledLesson && $candidate->status === 'free')
                ->map(fn (ScheduleSlot $candidate) => [$candidate->scheduledLesson->lesson_id, $candidate->id])
                ->values();
            $suffixAssignments = $suffixEligible->take($suffixLessonIds->count())->values()->map(fn (ScheduleSlot $candidate, int $index) => [$suffixLessonIds[$index], $candidate->id]);
            $pinnedAssignments = $allSlots->filter(fn (ScheduleSlot $candidate) => $candidate->is_pinned && $candidate->scheduledLesson)
                ->map(fn (ScheduleSlot $candidate) => [$candidate->scheduledLesson->lesson_id, $candidate->id])
                ->values();
            $outsideAssignments = $allSlots->reject(fn (ScheduleSlot $candidate) => $segmentSlots->contains('id', $candidate->id) || ! $candidate->scheduledLesson)
                ->map(fn (ScheduleSlot $candidate) => [$candidate->scheduledLesson->lesson_id, $candidate->id])
                ->values();
            $assignments = $prefixAssignments->concat($suffixAssignments)->concat($outsideAssignments)->concat($pinnedAssignments)->unique(fn (array $assignment) => $assignment[1])->values();
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
        $curriculumCompetencies = $group->curricula()->with(['versions.topics.competencies' => fn ($query) => $query->forGroup($group)])->get()->flatMap(fn ($curriculum) => $curriculum->versions->flatMap->topics)->flatMap->competencies;
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
