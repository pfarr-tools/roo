<?php

namespace App\Services;

use App\Models\GroupSongbook;
use App\Models\GroupSongbookEntry;
use App\Models\Lesson;
use App\Models\SongVersion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SongbookContentsResolver
{
    /**
     * Resolve the effective songbook contents without changing persisted data.
     * Entries are the initial songs; lesson/unit/phase assignments are added
     * transiently for the requested date range.
     */
    public function resolve(GroupSongbook $book, ?string $throughDate = null, ?string $afterDate = null): Collection
    {
        $book->load([
            'entries.songVersion.song', 'entries.songVersion.sheet', 'entries.songVersion.parts', 'entries.songVersion.images', 'entries.songVersion.chordSets',
            'group.teachingUnits.songs.song', 'group.teachingUnits.songs.sheet', 'group.teachingUnits.songs.parts', 'group.teachingUnits.songs.images', 'group.teachingUnits.songs.chordSets',
            'group.teachingUnits.lessons.songs.song', 'group.teachingUnits.lessons.songs.sheet', 'group.teachingUnits.lessons.songs.parts', 'group.teachingUnits.lessons.songs.images', 'group.teachingUnits.lessons.songs.chordSets',
            'group.teachingUnits.lessons.phases.songs.song', 'group.teachingUnits.lessons.phases.songs.sheet', 'group.teachingUnits.lessons.phases.songs.parts', 'group.teachingUnits.lessons.phases.songs.images', 'group.teachingUnits.lessons.phases.songs.chordSets',
            'group.teachingUnits.lessons.scheduledLessons.slot',
        ]);

        // Resolve and number the complete effective songbook first. Date
        // filters only select pages for the export; they must never change a
        // song's group-wide number.
        $entries = $afterDate === null ? $book->entries->values() : collect();
        $existingIds = $book->entries->pluck('song_version_id')->flip();
        $assigned = $this->assignedVersions($book);
        $nextNumber = (int) $book->entries->max('song_number');

        foreach ($assigned as $assignment) {
            $version = $assignment['version'];
            if ($existingIds->has($version->id) || ! $this->inRange($assignment['date'], $throughDate, $afterDate)) continue;

            $entry = new GroupSongbookEntry([
                'song_version_id' => $version->id,
                'song_number' => ++$nextNumber,
                'added_at' => $assignment['date'],
            ]);
            $entry->setRelation('songVersion', $version);
            $entries->push($entry);
        }

        return $entries
            ->filter(fn (GroupSongbookEntry $entry): bool => $this->inRange($entry->added_at, $throughDate, $afterDate))
            ->values();
    }

    public function resolveLessonSongs(?GroupSongbook $book, Lesson $lesson): Collection
    {
        $lesson->load(['songs.song', 'songs.sheet', 'songs.parts', 'songs.images', 'phases.songs.song', 'phases.songs.sheet', 'phases.songs.parts', 'phases.songs.images']);
        $initialIds = $book?->entries()->pluck('song_version_id')->flip() ?? collect();

        return $lesson->songs
            ->concat($lesson->phases->flatMap(fn ($phase) => $phase->songs))
            ->unique('id')
            ->reject(fn (SongVersion $version): bool => $initialIds->has($version->id))
            ->values();
    }

    private function assignedVersions(GroupSongbook $book): Collection
    {
        $assigned = collect();
        foreach ($book->group?->teachingUnits ?? [] as $unit) {
            foreach ($unit->songs as $version) $assigned->push(['version' => $version, 'date' => $version->pivot?->created_at ?? now()]);
            foreach ($unit->lessons as $lesson) {
                $date = $lesson->scheduledLessons->sortBy(fn ($scheduled) => $scheduled->slot?->date)->first()?->slot?->date
                    ?? $lesson->songs->first()?->pivot?->created_at
                    ?? now();
                foreach ($lesson->songs as $version) $assigned->push(['version' => $version, 'date' => $date]);
                foreach ($lesson->phases as $phase) foreach ($phase->songs as $version) $assigned->push(['version' => $version, 'date' => $date]);
            }
        }

        return $assigned->groupBy(fn (array $assignment) => $assignment['version']->id)->map(function (Collection $matches): array {
            $first = $matches->sortBy(fn (array $assignment) => Carbon::parse($assignment['date']))->first();
            return $first;
        })->values();
    }

    private function inRange(mixed $date, ?string $throughDate, ?string $afterDate): bool
    {
        if ($throughDate !== null && Carbon::parse($date)->toDateString() > $throughDate) return false;
        return $afterDate === null || Carbon::parse($date)->gt(Carbon::parse($afterDate));
    }
}
