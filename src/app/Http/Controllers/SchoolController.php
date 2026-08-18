<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolPeriodsRequest;
use App\Models\Curriculum;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', School::class);

        return Inertia::render('Schools/Index', [
            'schools' => School::query()->where('organization_id', auth()->user()->organization_id)->with('schoolYears:id,school_id,name,slug,starts_on,ends_on')->withCount('schoolYears')->orderBy('name')->get(),
        ]);
    }

    public function show(School $school): Response
    {
        $this->authorize('view', $school);
        $school->load(['curriculumAssignments' => fn ($query) => $query->where('organization_id', auth()->user()->organization_id)->with('curriculum:id,title,school_type,grades'), 'periods' => fn ($query) => $query->orderBy('period_number')]);

        return Inertia::render('Schools/Show', [
            'school' => $school,
            'curricula' => Curriculum::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))->orderBy('title')->get(['id', 'title', 'school_type', 'grades']),
        ]);
    }

    public function updatePeriods(UpdateSchoolPeriodsRequest $request, School $school): RedirectResponse
    {
        $this->authorize('update', $school);
        $periods = collect($request->validated()['periods']);
        abort_if($periods->pluck('period_number')->duplicates()->isNotEmpty(), 422, 'Jede Periodennummer darf nur einmal vorkommen.');

        DB::transaction(function () use ($school, $periods): void {
            abort_if($school->periods()->whereNotIn('period_number', $periods->pluck('period_number'))->whereHas('teachingGroups')->exists(), 422, 'Verwendete Stunden können nicht entfernt werden.');
            $keptIds = collect();
            foreach ($periods as $period) {
                $savedPeriod = $school->periods()->updateOrCreate(
                    ['period_number' => $period['period_number']],
                    [...collect($period)->only(['period_number', 'starts_at'])->all(), 'ends_at' => Carbon::createFromFormat('H:i', $period['starts_at'])->addMinutes(45)->format('H:i:s')],
                );
                $keptIds->push($savedPeriod->id);
            }
            $school->periods()->whereNotIn('id', $keptIds)->whereDoesntHave('teachingGroups')->delete();
        });

        return back()->with('success', 'Stundenraster wurde gespeichert.');
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        School::create([...$request->validated(), 'organization_id' => $request->user()->organization_id]);

        return to_route('schools.index')->with('success', 'Schule wurde angelegt.');
    }

    public function update(StoreSchoolRequest $request, School $school): RedirectResponse
    {
        $this->authorize('update', $school);
        $data = $request->validated();
        $curriculumIds = collect($data['curriculum_assignments'] ?? [])->pluck('curriculum_id')->unique();
        abort_unless(Curriculum::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->whereIn('id', $curriculumIds)->count() === $curriculumIds->count(), 403);
        $school->update(collect($data)->only(['name', 'short_name', 'school_type', 'city', 'notes'])->all());
        $school->curriculumAssignments()->where('organization_id', $request->user()->organization_id)->delete();
        foreach ($data['curriculum_assignments'] ?? [] as $assignment) {
            $school->curriculumAssignments()->create($assignment + ['organization_id' => $request->user()->organization_id]);
        }

        return to_route('schools.index')->with('success', 'Schule wurde gespeichert.');
    }

    public function destroy(School $school): RedirectResponse
    {
        $this->authorize('delete', $school);
        $school->delete();

        return to_route('schools.index')->with('success', 'Schule wurde gelöscht.');
    }
}
