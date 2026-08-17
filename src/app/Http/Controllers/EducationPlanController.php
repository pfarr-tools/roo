<?php

namespace App\Http\Controllers;

use App\Models\EducationPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EducationPlanController extends Controller
{
    public function index(): Response
    {
        $organizationId = auth()->user()->organization_id;

        return Inertia::render('EducationPlans/Index', [
            'educationPlans' => EducationPlan::query()
                ->where(function ($query) use ($organizationId): void {
                    $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
                })
                ->with(['versions' => fn ($query) => $query->select(['id', 'education_plan_id', 'external_identifier', 'title', 'version_date', 'is_complete'])->orderByDesc('version_date')->orderByDesc('id')])
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function show(Request $request, EducationPlan $educationPlan): Response
    {
        $this->ensureVisible($educationPlan);
        $versions = $educationPlan->versions()->orderByDesc('version_date')->orderByDesc('id')->get(['id', 'education_plan_id', 'external_identifier', 'schema_version', 'title', 'version_date', 'source_url', 'is_complete', 'conversion_metadata']);
        abort_if($versions->isEmpty(), HttpResponse::HTTP_NOT_FOUND, 'Für diesen Bildungsplan ist keine Fassung vorhanden.');

        $version = $versions->firstWhere('id', $request->integer('version')) ?? $versions->first();
        $version->load([
            'stages' => fn ($query) => $query->orderBy('position'),
            'stages.gradeLevels' => fn ($query) => $query->orderBy('position'),
            'stages.levels' => fn ($query) => $query->orderBy('position'),
            'stages.competenceAreas' => fn ($query) => $query->orderBy('position'),
            'stages.competenceAreas.competencies' => fn ($query) => $query->orderBy('position'),
            'stages.competenceAreas.competencies.variants' => fn ($query) => $query->orderBy('position'),
            'stages.competenceAreas.competencies.variants.level',
            'stages.competenceAreas.competencies.relations' => fn ($query) => $query->orderBy('position'),
            'competenceAreas' => fn ($query) => $query->where('kind', 'process')->orderBy('position'),
            'competenceAreas.competencies' => fn ($query) => $query->orderBy('position'),
            'competenceAreas.competencies.variants' => fn ($query) => $query->orderBy('position'),
            'competenceAreas.competencies.variants.level',
            'competenceAreas.competencies.relations' => fn ($query) => $query->orderBy('position'),
        ]);

        return Inertia::render('EducationPlans/Show', [
            'educationPlan' => $educationPlan,
            'versions' => $versions,
            'selectedVersion' => $version,
        ]);
    }

    private function ensureVisible(EducationPlan $educationPlan): void
    {
        abort_unless($educationPlan->organization_id === null || $educationPlan->organization_id === auth()->user()->organization_id, HttpResponse::HTTP_NOT_FOUND);
    }
}
