<?php

namespace App\Actions\SchoolYears;

use App\Models\SchoolYear;
use Carbon\CarbonPeriod;

class GenerateSchoolYearDays
{
    public function execute(SchoolYear $schoolYear): void
    {
        $overrides = $schoolYear->calendarExceptions()->get()->keyBy(fn ($item) => $item->date->toDateString());
        $holidays = $schoolYear->holidayPeriods()->get();
        $rows = [];

        foreach (CarbonPeriod::create($schoolYear->starts_on, $schoolYear->ends_on) as $date) {
            $key = $date->toDateString();
            $exception = $overrides->get($key);
            $holiday = $holidays->first(fn ($item) => $date->betweenIncluded($item->starts_on, $item->ends_on));
            $kind = $exception?->kind ?? ($holiday ? 'holiday' : ($date->isWeekend() ? 'weekend' : 'instruction'));
            $label = $exception?->label ?? ($holiday?->name ?? ($date->isWeekend() ? 'Wochenende' : null));

            $rows[] = [
                'school_year_id' => $schoolYear->id,
                'date' => $key,
                'kind' => $kind,
                'label' => $label,
                'source_type' => $exception ? 'calendar_exception' : ($holiday ? 'holiday_period' : null),
                'source_id' => $exception?->id ?? $holiday?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $schoolYear->days()->upsert($rows, ['school_year_id', 'date'], ['kind', 'label', 'source_type', 'source_id', 'updated_at']);
    }
}
