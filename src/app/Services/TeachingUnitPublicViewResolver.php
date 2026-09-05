<?php

namespace App\Services;

use App\Enums\PublicationStatus;
use App\Models\Lesson;
use App\Models\LessonPhase;
use App\Models\TeachingUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class TeachingUnitPublicViewResolver
{
    public function resolve(TeachingUnit $unit, CarbonImmutable $now): TeachingUnitPublicView
    {
        $unit->loadMissing([
            'creator',
            'group.school',
            'competencies.educationPlanCompetency.area',
            'competencies.educationPlanCompetency.variants',
            'competencies.curriculumEducationPlanReference.educationPlanCompetency.area',
            'competencies.curriculumEducationPlanReference.educationPlanCompetency.variants',
            'lessons.scheduledLessons.slot',
            'lessons.phases.resources',
            'lessons.phases.resourceLinks',
        ]);

        $scheduledLessons = $unit->lessons
            ->flatMap(fn (Lesson $lesson): Collection => $lesson->scheduledLessons
                ->filter(fn ($scheduledLesson): bool => $scheduledLesson->slot !== null && $scheduledLesson->status !== 'cancelled')
                ->map(fn ($scheduledLesson): array => [
                    'lesson' => $lesson,
                    'scheduled_lesson' => $scheduledLesson,
                    'starts_at' => $this->slotDateTime($scheduledLesson->slot),
                ]))
            ->sortBy('starts_at')
            ->values();

        $visiblePhaseResources = collect();
        $visiblePhaseLinks = collect();
        $nextVisibilityAt = null;

        foreach ($unit->lessons as $lesson) {
            $lessonStart = $this->earliestStart($lesson);
            if ($lessonStart !== null && $lessonStart->greaterThan($now) && ($nextVisibilityAt === null || $lessonStart->lessThan($nextVisibilityAt))) {
                $nextVisibilityAt = $lessonStart;
            }

            foreach ($lesson->phases as $phase) {
                $this->appendVisibleResources($visiblePhaseResources, $phase, $lessonStart, $now, false);
                $this->appendVisibleResources($visiblePhaseLinks, $phase, $lessonStart, $now, true);
            }
        }

        return new TeachingUnitPublicView(
            unit: $unit,
            creator: $unit->creator,
            group: $unit->group,
            school: $unit->group->school,
            competencies: $unit->competencies,
            scheduledLessons: $scheduledLessons,
            visiblePhaseResources: $visiblePhaseResources->values(),
            visiblePhaseLinks: $visiblePhaseLinks->values(),
            nextVisibilityAt: $nextVisibilityAt,
        );
    }

    private function earliestStart(Lesson $lesson): ?CarbonImmutable
    {
        return $lesson->scheduledLessons
            ->filter(fn ($scheduledLesson): bool => $scheduledLesson->slot !== null && $scheduledLesson->status !== 'cancelled')
            ->map(fn ($scheduledLesson): CarbonImmutable => $this->slotDateTime($scheduledLesson->slot))
            ->sort()
            ->first();
    }

    private function slotDateTime($slot): CarbonImmutable
    {
        return CarbonImmutable::parse($slot->date->toDateString().' '.$slot->starts_at, 'Europe/Berlin');
    }

    private function appendVisibleResources(Collection $visible, LessonPhase $phase, ?CarbonImmutable $lessonStart, CarbonImmutable $now, bool $links): void
    {
        $items = $links ? $phase->resourceLinks : $phase->resources;

        foreach ($items as $item) {
            $status = $item->pivot->publication_status instanceof PublicationStatus
                ? $item->pivot->publication_status
                : PublicationStatus::tryFrom((string) $item->pivot->publication_status);
            $lessonStarted = $lessonStart !== null && $lessonStart->lessThanOrEqualTo($now);

            if ($status === null || ! $status->allowsPublicAccess($lessonStarted)) {
                continue;
            }

            $item->setAttribute('phase_title', $phase->title);
            $visible->push($item);
        }
    }
}
