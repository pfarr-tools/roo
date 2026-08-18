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
            $response = Http::acceptJson()->timeout(15)->get(
                rtrim(config('services.ferien_api.url'), '/').'/SchoolHolidays',
                [
                    'countryIsoCode' => config('services.ferien_api.country', 'DE'),
                    'subdivisionCode' => $this->subdivisionCode($stateCode),
                    'languageIsoCode' => config('services.ferien_api.language', 'DE'),
                    'validFrom' => $year.'-01-01',
                    'validTo' => $year.'-12-31',
                ],
            );
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
                if (! isset($holiday['id'], $holiday['name'], $holiday['startDate'], $holiday['endDate'])) {
                    continue;
                }

                $name = collect($holiday['name'])->firstWhere('language', config('services.ferien_api.language', 'DE'))['text']
                    ?? collect($holiday['name'])->first()['text']
                    ?? null;
                if (! $name) {
                    continue;
                }

                $start = now()->parse($holiday['startDate'])->setTimezone($schoolYear->timezone)->toDateString();
                $end = now()->parse($holiday['endDate'])->setTimezone($schoolYear->timezone)->toDateString();
                if ($start > $end || $end < $schoolYear->starts_on->toDateString() || $start > $schoolYear->ends_on->toDateString()) {
                    continue;
                }

                HolidayPeriod::updateOrCreate(
                    ['school_year_id' => $schoolYear->id, 'external_identifier' => $holiday['id']],
                    ['data_source_id' => $source->id, 'name' => $name, 'starts_on' => max($start, $schoolYear->starts_on->toDateString()), 'ends_on' => min($end, $schoolYear->ends_on->toDateString()), 'change_reason' => null],
                );
                $count++;
            }

            app(GenerateSchoolYearDays::class)->execute($schoolYear->fresh());

            return $count;
        });
    }

    private function subdivisionCode(string $stateCode): string
    {
        return str_contains($stateCode, '-') ? strtoupper($stateCode) : config('services.ferien_api.country', 'DE').'-'.strtoupper($stateCode);
    }
}
