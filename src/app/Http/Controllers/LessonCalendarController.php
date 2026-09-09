<?php

namespace App\Http\Controllers;

use App\Models\ScheduledLesson;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class LessonCalendarController extends Controller
{
    public function __invoke(User $user): Response
    {
        $timezone = (string) config('app.timezone', 'Europe/Berlin');
        $lessons = ScheduledLesson::query()
            ->whereHas('slot.group', fn ($query) => $query->where('user_id', $user->id))
            ->with(['slot.group.school:id,name', 'lesson.unit:id,title'])
            ->whereHas('slot')
            ->orderBy('id')
            ->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Roo//Unterrichtskalender//DE',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:Roo Unterricht',
        ];

        foreach ($lessons as $scheduledLesson) {
            $slot = $scheduledLesson->slot;
            $group = $slot->group;
            $lesson = $scheduledLesson->lesson;
            $start = CarbonImmutable::parse($slot->date->format('Y-m-d').' '.($slot->starts_at ?: '08:00'), $timezone);
            $end = CarbonImmutable::parse($slot->date->format('Y-m-d').' '.($slot->ends_at ?: '08:45'), $timezone);
            $description = collect([
                'Unterricht in '.$group->name,
                $group->school?->name ? 'Schule: '.$group->school->name : null,
                $lesson->unit?->title ? 'Unterrichtseinheit: '.$lesson->unit->title : null,
                'Status: '.$scheduledLesson->status,
            ])->filter()->implode("\n");

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:roo-scheduled-lesson-'.$scheduledLesson->id.'@roo';
            $lines[] = 'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$start->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$end->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.self::escape($group->name.' – '.$lesson->title);
            $lines[] = 'DESCRIPTION:'.self::escape($description);
            $lines[] = 'LOCATION:'.self::escape($group->school?->name ?? '');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, ['Content-Type' => 'text/calendar; charset=UTF-8', 'Content-Disposition' => 'inline; filename="roo-unterricht.ics"']);
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $value);
    }
}
