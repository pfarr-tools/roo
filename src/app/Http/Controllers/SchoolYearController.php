<?php

namespace App\Http\Controllers;

use App\Actions\SchoolYears\GenerateSchoolYearDays;
use App\Actions\SchoolYears\ImportHolidaysFromApi;
use App\Http\Requests\StoreCalendarExceptionRequest;
use App\Http\Requests\StoreHolidayPeriodRequest;
use App\Http\Requests\StoreSchoolYearRequest;
use App\Models\CalendarException;
use App\Models\HolidayPeriod;
use App\Models\School;
use App\Models\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SchoolYearController extends Controller
{
    public function show(SchoolYear $schoolYear): Response
    {
        $this->authorize('view', $schoolYear);
        $schoolYear->load(['school', 'holidayPeriods', 'calendarExceptions']);

        return Inertia::render('SchoolYears/Show', [
            'schoolYear' => $schoolYear,
            'days' => $schoolYear->days()->orderBy('date')->get(),
        ]);
    }

    public function store(StoreSchoolYearRequest $request): RedirectResponse
    {
        $school = School::whereKey($request->integer('school_id'))->where('organization_id', $request->user()->organization_id)->firstOrFail();
        $year = SchoolYear::create([...$request->validated(), 'organization_id' => $request->user()->organization_id]);
        app(GenerateSchoolYearDays::class)->execute($year);

        return to_route('school-years.show', $year)->with('success', 'Schuljahr wurde angelegt.');
    }

    public function update(StoreSchoolYearRequest $request, SchoolYear $schoolYear): RedirectResponse
    {
        $this->authorize('update', $schoolYear);
        $schoolYear->update($request->validated());
        app(GenerateSchoolYearDays::class)->execute($schoolYear->refresh());

        return back()->with('success', 'Schuljahr wurde gespeichert.');
    }

    public function storeHoliday(StoreHolidayPeriodRequest $request, SchoolYear $schoolYear): RedirectResponse
    {
        $this->authorize('update', $schoolYear);
        $data = $request->validated();
        abort_if($data['starts_on'] < $schoolYear->starts_on->toDateString() || $data['ends_on'] > $schoolYear->ends_on->toDateString(), 422, 'Der Zeitraum liegt außerhalb des Schuljahres.');
        HolidayPeriod::create([...$data, 'school_year_id' => $schoolYear->id]);
        app(GenerateSchoolYearDays::class)->execute($schoolYear);

        return back()->with('success', 'Ferienzeit wurde erfasst.');
    }

    public function storeException(StoreCalendarExceptionRequest $request, SchoolYear $schoolYear): RedirectResponse
    {
        $this->authorize('update', $schoolYear);
        CalendarException::updateOrCreate(['school_year_id' => $schoolYear->id, 'date' => $request->date('date')], $request->validated());
        app(GenerateSchoolYearDays::class)->execute($schoolYear);

        return back()->with('success', 'Kalenderausnahme wurde gespeichert.');
    }

    public function importHolidays(SchoolYear $schoolYear): RedirectResponse
    {
        $this->authorize('update', $schoolYear);
        $count = app(ImportHolidaysFromApi::class)->execute($schoolYear, (string) request('state_code', config('services.ferien_api.default_state')));

        return back()->with('success', $count.' Ferienzeiträume wurden aus der Ferien-API importiert.');
    }
}
