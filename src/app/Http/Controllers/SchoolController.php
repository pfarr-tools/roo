<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolRequest;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
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

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        School::create([...$request->validated(), 'organization_id' => $request->user()->organization_id]);

        return to_route('schools.index')->with('success', 'Schule wurde angelegt.');
    }

    public function update(StoreSchoolRequest $request, School $school): RedirectResponse
    {
        $this->authorize('update', $school);
        $school->update($request->validated());

        return back()->with('success', 'Schule wurde gespeichert.');
    }
}
