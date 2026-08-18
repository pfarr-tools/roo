<?php

namespace App\Http\Controllers;

use App\Models\SchoolPeriod;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $timezone = (string) config('app.timezone', 'Europe/Berlin');
        $currentMonday = CarbonImmutable::now($timezone)->startOfWeek(CarbonImmutable::MONDAY);
        $organizationId = $request->user()->organization_id;
        $schoolYears = SchoolYear::where('organization_id', $organizationId)->with('school:id,name')->orderBy('starts_on')->get();
        $periodNumbers = SchoolPeriod::whereHas('school', fn ($query) => $query->where('organization_id', $organizationId))->distinct()->orderBy('period_number')->pluck('period_number')->values();
        $weekInSchoolYear = fn (CarbonImmutable $monday): bool => $schoolYears->contains(fn (SchoolYear $year) => $year->starts_on->lte($monday->addDays(4)) && $year->ends_on->gte($monday));
        $nextMonday = $schoolYears->filter(fn (SchoolYear $year) => $year->ends_on->gte($currentMonday->addWeek()))->map(fn (SchoolYear $year): CarbonImmutable => CarbonImmutable::parse($year->starts_on->toDateString(), $timezone)->startOfWeek(CarbonImmutable::MONDAY))->filter(fn (CarbonImmutable $monday) => $monday->gte($currentMonday->addWeek()))->sort()->first() ?? $currentMonday->addWeek();
        $requestedWeek = $request->date('week');
        $selectedMonday = $requestedWeek?->toImmutable()->startOfWeek(CarbonImmutable::MONDAY) ?? ($weekInSchoolYear($currentMonday) ? $currentMonday : $nextMonday);
        $weekOptions = collect();
        foreach ($schoolYears as $schoolYear) {
            $monday = CarbonImmutable::parse($schoolYear->starts_on->toDateString(), $timezone)->startOfWeek(CarbonImmutable::MONDAY);
            $lastMonday = CarbonImmutable::parse($schoolYear->ends_on->toDateString(), $timezone)->startOfWeek(CarbonImmutable::MONDAY);
            while ($monday->lte($lastMonday)) {
                $weekOptions->put($monday->toDateString(), ['value' => $monday->toDateString(), 'label' => $monday->format('d.m.Y').' – '.$monday->addDays(4)->format('d.m.Y')]);
                $monday = $monday->addWeek();
            }
        }
        if (! $weekOptions->has($selectedMonday->toDateString())) {
            $weekOptions->put($selectedMonday->toDateString(), ['value' => $selectedMonday->toDateString(), 'label' => $selectedMonday->format('d.m.Y').' – '.$selectedMonday->addDays(4)->format('d.m.Y')]);
        }
        $weekOptions = $weekOptions->sortKeys()->values();
        $schoolYearIds = $schoolYears->filter(fn (SchoolYear $year) => $year->starts_on->lte($selectedMonday->addDays(4)) && $year->ends_on->gte($selectedMonday))->pluck('id');
        $groups = $request->user()->organization_id
            ? TeachingGroup::where('organization_id', $organizationId)->whereIn('school_year_id', $schoolYearIds)->with(['school:id,name', 'schoolYear:id,name', 'schoolPeriods:id,school_id,period_number,starts_at,ends_at'])->get()
            : collect();
        $days = collect(range(1, 5))->map(function (int $weekday) use ($selectedMonday, $groups): array {
            $date = $selectedMonday->addDays($weekday - 1);
            $entries = $groups->flatMap(function ($group) use ($weekday, $date) {
                return $group->schoolPeriods->filter(fn ($period) => (int) $period->pivot->weekday === $weekday)->map(fn ($period): array => [
                    'period_number' => $period->period_number,
                    'starts_at' => $period->starts_at->format('H:i'),
                    'ends_at' => $period->ends_at->format('H:i'),
                    'group_name' => $group->name,
                    'group_id' => $group->id,
                    'school_name' => $group->school->name,
                    'school_year_name' => $group->schoolYear->name,
                    'date' => $date->toDateString(),
                ]);
            })->sortBy('period_number')->values();

            return ['weekday' => $weekday, 'date' => $date->toDateString(), 'label' => $date->locale('de')->isoFormat('dddd'), 'entries' => $entries];
        });

        return Inertia::render('Dashboard', ['week' => $selectedMonday->toDateString(), 'weekOptions' => $weekOptions, 'previousWeek' => $selectedMonday->subWeek()->toDateString(), 'nextWeek' => $selectedMonday->addWeek()->toDateString(), 'days' => $days, 'periodNumbers' => $periodNumbers, 'hasSchoolYear' => $schoolYearIds->isNotEmpty()]);
    }
}
