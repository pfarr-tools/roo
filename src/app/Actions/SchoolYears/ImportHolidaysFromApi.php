<?php

namespace App\Actions\SchoolYears;

use App\Models\DataSource;
use App\Models\HolidayPeriod;
use App\Models\SchoolYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ImportHolidaysFromApi
{
    public function execute(SchoolYear $schoolYear, string $stateCode = 'BW'): int
    {
        $years = array_unique([$schoolYear->starts_on->year, $schoolYear->ends_on->year]);
        $holidays = [];
        foreach ($years as $year) {
            $response = Http::acceptJson()->timeout(15)->get(sprintf('%s/api/v1/holidays/%s/%d', rtrim(config('services.ferien_api.url'), '/'), strtoupper($stateCode), $year));
            $response->throw();
            $payload = $response->json();
            if (! is_array($payload)) {
                throw ValidationException::withMessages(['ferien' => 'Die Ferien-API hat ein ungültiges Format geliefert.']);
            }
            $holidays = [...$holidays, ...$payload];
        }

        return DB::transaction(function () use ($schoolYear, $stateCode, $years, $holidays): int {
            $source = DataSource::create([
                'organization_id' => $schoolYear->organization_id,
                'name' => 'Ferien-API',
                'kind' => 'ferien-api',
                'external_identifier' => strtoupper($stateCode).'-'.implode('-', $years),
                'imported_at' => now(),
            ]);

            $count = 0;
            foreach ($holidays as $holiday) {
                if (! isset($holiday['slug'], $holiday['name'], $holiday['start'], $holiday['end'])) {
                    continue;
                }

                $start = now()->parse($holiday['start'])->setTimezone($schoolYear->timezone)->toDateString();
                $end = now()->parse($holiday['end'])->setTimezone($schoolYear->timezone)->subDay()->toDateString();
                if ($start > $end || $end < $schoolYear->starts_on->toDateString() || $start > $schoolYear->ends_on->toDateString()) {
                    continue;
                }

                HolidayPeriod::updateOrCreate(
                    ['school_year_id' => $schoolYear->id, 'external_identifier' => $holiday['slug']],
                    ['data_source_id' => $source->id, 'name' => mb_strtoupper(mb_substr($holiday['name'], 0, 1)).mb_substr($holiday['name'], 1), 'starts_on' => max($start, $schoolYear->starts_on->toDateString()), 'ends_on' => min($end, $schoolYear->ends_on->toDateString()), 'change_reason' => null],
                );
                $count++;
            }

            app(GenerateSchoolYearDays::class)->execute($schoolYear->fresh());

            return $count;
        });
    }
}
