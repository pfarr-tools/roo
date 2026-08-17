<?php

namespace App\Http\Controllers;

use App\Models\EducationPlan;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanImportRun;
use App\Models\EducationPlanVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EducationPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $organizationId = auth()->user()->organization_id;
        $search = trim((string) $request->string('q'));

        return Inertia::render('EducationPlans/Index', [
            'educationPlans' => EducationPlan::query()
                ->where(function ($query) use ($organizationId): void {
                    $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
                })
                ->when($search !== '', function ($query) use ($search): void {
                    $like = '%'.mb_strtolower($search).'%';
                    $query->where(function ($query) use ($like): void {
                        $query->whereRaw('LOWER(title) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(subject) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(external_identifier) LIKE ?', [$like])
                            ->orWhereHas('versions', function ($query) use ($like): void {
                                $query->whereRaw('LOWER(title) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(external_identifier) LIKE ?', [$like])
                                    ->orWhereHas('competenceAreas', function ($query) use ($like): void {
                                        $query->whereRaw('LOWER(title) LIKE ?', [$like])
                                            ->orWhereRaw('LOWER(introduction) LIKE ?', [$like])
                                            ->orWhereHas('competencies', function ($query) use ($like): void {
                                                $query->whereRaw('LOWER(external_identifier) LIKE ?', [$like])
                                                    ->orWhereRaw('LOWER(text) LIKE ?', [$like]);
                                            });
                                    });
                            });
                    });
                })
                ->with(['versions' => fn ($query) => $query->select(['id', 'education_plan_id', 'external_identifier', 'title', 'version_date', 'is_complete'])->orderByDesc('version_date')->orderByDesc('id')])
                ->orderBy('title')
                ->get(),
            'search' => $search,
        ]);
    }

    public function show(Request $request, EducationPlan $educationPlan): Response
    {
        $this->ensureVisible($educationPlan);
        $versions = $educationPlan->versions()->orderByDesc('version_date')->orderByDesc('id')->get(['id', 'education_plan_id', 'external_identifier', 'schema_version', 'title', 'version_date', 'source_url', 'is_complete', 'conversion_metadata']);
        abort_if($versions->isEmpty(), HttpResponse::HTTP_NOT_FOUND, 'Für diesen Bildungsplan ist keine Fassung vorhanden.');

        $version = $versions->firstWhere('id', $request->integer('version')) ?? $versions->first();
        $version = $this->loadVersion($version);
        $comparisonVersion = $versions->firstWhere('id', $request->integer('compare'));
        if ($comparisonVersion && $comparisonVersion->id === $version->id) {
            $comparisonVersion = null;
        }
        if ($comparisonVersion) {
            $comparisonVersion = $this->loadVersion($comparisonVersion);
        }
        $importRuns = EducationPlanImportRun::query()->whereIn('education_plan_version_id', $versions->pluck('id'))->with('version:id,external_identifier')->orderByDesc('created_at')->get();

        return Inertia::render('EducationPlans/Show', [
            'educationPlan' => $educationPlan,
            'versions' => $versions,
            'selectedVersion' => $version,
            'comparisonVersion' => $comparisonVersion,
            'comparisonRows' => $comparisonVersion ? $this->comparisonRows($version, $comparisonVersion) : [],
            'importRuns' => $importRuns,
        ]);
    }

    public function updateCompetencyStatus(Request $request, EducationPlan $educationPlan, EducationPlanCompetency $competency): RedirectResponse
    {
        $this->ensureVisible($educationPlan);
        $competency->load('area.version');
        abort_unless($competency->area?->version?->education_plan_id === $educationPlan->id, HttpResponse::HTTP_NOT_FOUND);
        $competency->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Kompetenzstatus wurde gespeichert.');
    }

    private function loadVersion(EducationPlanVersion $version): EducationPlanVersion
    {
        return $version->load([
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
    }

    /** @return list<array<string, mixed>> */
    private function comparisonRows(EducationPlanVersion $current, EducationPlanVersion $other): array
    {
        $currentRows = $this->flattenCompetencies($current);
        $otherRows = $this->flattenCompetencies($other);
        $keys = collect(array_keys($currentRows))->merge(array_keys($otherRows))->unique()->sort()->values();

        return $keys->map(function (string $key) use ($currentRows, $otherRows): array {
            $currentRow = $currentRows[$key] ?? null;
            $otherRow = $otherRows[$key] ?? null;
            $status = ! $currentRow ? 'removed' : (! $otherRow ? 'added' : ($currentRow['content'] === $otherRow['content'] && $currentRow['is_active'] === $otherRow['is_active'] ? 'unchanged' : 'changed'));

            return [
                'external_identifier' => $key,
                'title' => $currentRow['title'] ?? $otherRow['title'],
                'current' => $currentRow['content'] ?? null,
                'other' => $otherRow['content'] ?? null,
                'status' => $status,
            ];
        })->all();
    }

    /** @return array<string, array{title: string, content: string, is_active: bool}> */
    private function flattenCompetencies(EducationPlanVersion $version): array
    {
        $rows = [];
        foreach ($version->competenceAreas->where('kind', 'process') as $area) {
            foreach ($area->competencies as $competency) {
                $rows[$competency->external_identifier] = ['title' => $area->title, 'content' => $this->competencyContent($competency), 'is_active' => $competency->is_active];
            }
        }
        foreach ($version->stages as $stage) {
            foreach ($stage->competenceAreas as $area) {
                foreach ($area->competencies as $competency) {
                    $rows[$competency->external_identifier] = ['title' => $stage->label.' · '.$area->title, 'content' => $this->competencyContent($competency), 'is_active' => $competency->is_active];
                }
            }
        }

        return $rows;
    }

    private function competencyContent(EducationPlanCompetency $competency): string
    {
        if ($competency->text) {
            return $competency->text;
        }

        return $competency->variants->map(fn ($variant): string => ($variant->level?->external_identifier ?? 'Standard').': '.$variant->text)->implode("\n");
    }

    private function ensureVisible(EducationPlan $educationPlan): void
    {
        abort_unless($educationPlan->organization_id === null || $educationPlan->organization_id === auth()->user()->organization_id, HttpResponse::HTTP_NOT_FOUND);
    }
}
