<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\CurriculumTopic;
use App\Models\CurriculumVersion;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $curricula = Curriculum::with(['versions' => fn ($q) => $q->withCount('topics')])
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('external_identifier', 'like', "%{$search}%")))
            ->orderBy('title')->get();

        return Inertia::render('Curricula/Index', ['curricula' => $curricula, 'search' => $search]);
    }

    public function create(): Response
    {
        $sources = CurriculumVersion::with(['curriculum:id,title,school_type,grades,variant'])->withCount('topics')->where('is_editable', false)->whereHas('curriculum', fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))->orderBy('id')->get();

        return Inertia::render('Curricula/Create', ['sources' => $sources]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'school_type' => ['nullable', 'string', 'max:50'], 'grades' => ['nullable', 'array'], 'grades.*' => ['integer', 'min:1', 'max:13'], 'denominations' => ['nullable', 'array'], 'denominations.*' => ['string', 'max:50'], 'source_version_ids' => ['nullable', 'array'], 'source_version_ids.*' => ['integer', 'exists:curriculum_versions,id']]);
        $curriculum = DB::transaction(function () use ($data): Curriculum {
            $sourceVersionIds = array_values($data['source_version_ids'] ?? []);
            $sourceVersions = CurriculumVersion::with(['curriculum', 'bindings', 'topics.competencies', 'topics.profiles'])->whereIn('id', $sourceVersionIds)->where('is_editable', false)->whereHas('curriculum', fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))->get();
            abort_if($sourceVersions->count() !== count(array_unique($sourceVersionIds)), 422, 'Mindestens eine Vorlage ist nicht verfügbar.');
            $grades = array_values($data['grades'] ?? []);
            if ($grades === []) {
                $grades = $sourceVersions->pluck('curriculum.grades')->flatten()->filter()->unique()->sort()->values()->all();
            }
            $denominations = array_values($data['denominations'] ?? $sourceVersions->pluck('curriculum.denominations')->flatten()->filter()->unique()->values()->all());
            $curriculum = Curriculum::create(['organization_id' => auth()->user()->organization_id, 'title' => $data['title'], 'school_type' => $data['school_type'] ?? null, 'grades' => $grades, 'denominations' => $denominations, 'cooperation_model' => 'confessional_cooperative', 'derived_from_id' => $sourceVersions->first()?->curriculum_id]);
            $version = $curriculum->versions()->create(['external_identifier' => 'custom-1', 'schema_version' => '1.0.0', 'is_editable' => true, 'is_complete' => true]);
            $bindings = $sourceVersions->flatMap->bindings->map(fn ($binding) => $binding->only(['education_plan_id', 'plan_code', 'role', 'denomination', 'subject', 'raw_data']))->unique(fn ($binding) => implode('|', [$binding['denomination'], $binding['role'], $binding['plan_code']]))->values();
            foreach ($bindings as $binding) {
                $version->bindings()->create($binding);
            }
            $position = 0;
            foreach ($sourceVersions as $source) {
                foreach ($source->topics as $topic) {
                    $sourceGrades = $source->curriculum->grades ?? [];
                    $copy = $version->topics()->create($topic->only(['external_identifier', 'number', 'title', 'position', 'hours', 'notes', 'preparation_questions', 'shared_plan', 'raw_rows']) + ['source_curriculum_version_id' => $source->id, 'year' => $topic->year ?? (count($sourceGrades) === 1 ? (int) $sourceGrades[0] : null), 'position' => $position++]);
                    foreach ($topic->competencies as $competency) {
                        $copy->competencies()->create($competency->only(['education_plan_competency_id', 'denomination', 'competency_kind', 'external_identifier', 'display', 'raw_text', 'position']));
                    }
                    foreach ($topic->profiles as $profile) {
                        $copy->profiles()->create($profile->only(['denomination', 'perspective']));
                    }
                }
            }

            return $curriculum;
        });

        return redirect()->route('curricula.show', $curriculum);
    }

    public function show(Curriculum $curriculum): Response
    {
        $this->ensureVisible($curriculum);
        $version = $curriculum->versions()->latest('id')->with(['bindings.educationPlan', 'topics' => fn ($q) => $q->orderByRaw('year is null desc')->orderBy('year')->orderBy('position'), 'topics.profiles', 'topics.competencies.educationPlanCompetency', 'topics.sourceVersion.curriculum'])->firstOrFail();

        return Inertia::render('Curricula/Show', ['curriculum' => $curriculum, 'version' => $version, 'educationPlans' => $this->educationPlanOptions()]);
    }

    public function storeTopic(Request $request, Curriculum $curriculum): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        $version = $curriculum->versions()->where('is_editable', true)->latest('id')->firstOrFail();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1', 'max:13'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:999'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'preparation_questions' => ['nullable', 'string', 'max:10000'],
        ]);
        $data['preparation_questions'] = collect(preg_split('/\R/', $data['preparation_questions'] ?? ''))
            ->map(fn (string $question): string => trim($question))->filter()->values()->all();
        $position = (int) $version->topics()->max('position') + 1;
        $version->topics()->create($data + ['position' => $position]);

        return back();
    }

    public function update(Request $request, Curriculum $curriculum): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        $isEditable = $curriculum->versions()->where('is_editable', true)->exists();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'school_type' => ['nullable', 'string', 'max:50'],
            'grades' => ['nullable', 'array'],
            'grades.*' => ['integer', 'min:1', 'max:13'],
            'denominations' => ['nullable', 'array'],
            'denominations.*' => ['string', 'max:50'],
            'education_plan_bindings' => ['nullable', 'array'],
            'education_plan_bindings.*.denomination' => ['required', 'string', 'max:50'],
            'education_plan_bindings.*.subject' => ['nullable', 'string', 'max:255'],
            'education_plan_bindings.*.plan_code' => ['nullable', 'string', 'max:255', 'exists:education_plans,external_identifier'],
        ]);
        if (! $isEditable && ($data['title'] !== $curriculum->title || ($data['school_type'] ?? null) !== $curriculum->school_type || ($data['grades'] ?? []) !== ($curriculum->grades ?? []) || ($data['denominations'] ?? []) !== ($curriculum->denominations ?? []))) {
            abort(403);
        }
        DB::transaction(function () use ($curriculum, $data): void {
            $curriculum->update(collect($data)->only(['title', 'school_type', 'grades', 'denominations'])->all());
            $version = $curriculum->versions()->latest('id')->firstOrFail();
            if (array_key_exists('education_plan_bindings', $data)) {
                $version->bindings()->delete();
                foreach ($data['education_plan_bindings'] ?? [] as $binding) {
                    $binding['education_plan_id'] = EducationPlan::where('external_identifier', $binding['plan_code'] ?? '')->value('id');
                    $version->bindings()->create(collect($binding)->only(['education_plan_id', 'plan_code', 'denomination', 'subject'])->all() + ['role' => 'denominational_basis']);
                }
                $this->resolveTopicCompetencies($version);
            }
        });

        return back();
    }

    public function destroy(Curriculum $curriculum): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        abort_unless($curriculum->versions()->where('is_editable', true)->exists(), 403);
        $curriculum->delete();

        return redirect()->route('curricula.index');
    }

    public function updateTopic(Request $request, Curriculum $curriculum, CurriculumTopic $topic): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        abort_unless($topic->version->curriculum_id === $curriculum->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:999'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'preparation_questions' => ['nullable', 'string', 'max:10000'],
        ]);
        $data['preparation_questions'] = collect(preg_split('/\R/', $data['preparation_questions'] ?? ''))
            ->map(fn (string $question): string => trim($question))->filter()->values()->all();
        $topic->update($data);

        return back();
    }

    public function updateTopicYear(Request $request, Curriculum $curriculum, CurriculumTopic $topic): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        abort_unless($topic->version->curriculum_id === $curriculum->id, 404);
        $data = $request->validate(['year' => ['nullable', 'integer', 'min:1', 'max:13']]);
        $topic->update(['year' => $data['year'] ?? null]);

        return back();
    }

    public function updateTopicCompetencies(Request $request, Curriculum $curriculum, CurriculumTopic $topic): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        abort_unless($topic->version->curriculum_id === $curriculum->id, 404);
        $data = $request->validate([
            'competencies' => ['array'],
            'competencies.*.denomination' => ['nullable', 'string', 'max:50'],
            'competencies.*.competency_kind' => ['required', 'in:content,process'],
            'competencies.*.external_identifier' => ['required', 'string', 'max:100'],
            'competencies.*.display' => ['nullable', 'string', 'max:255'],
            'competencies.*.raw_text' => ['nullable', 'string', 'max:10000'],
        ]);
        abort_unless($topic->version->is_editable || collect($data['competencies'] ?? [])->every(fn (array $competency): bool => $competency['competency_kind'] === 'process'), 403);
        $planVersionIds = EducationPlanVersion::whereIn('education_plan_id', $topic->version->bindings()->whereNotNull('education_plan_id')->pluck('education_plan_id'))->pluck('id');
        $topic->competencies()->delete();
        foreach ($data['competencies'] ?? [] as $position => $competency) {
            $match = EducationPlanCompetency::where('external_identifier', $competency['external_identifier'])
                ->whereHas('area', fn ($query) => $query->whereIn('education_plan_version_id', $planVersionIds))->first();
            $topic->competencies()->create($competency + ['display' => $match?->external_identifier, 'raw_text' => $match?->text, 'education_plan_competency_id' => $match?->id, 'position' => $position]);
        }
        $this->resolveTopicCompetencies($topic->version);

        return back();
    }

    private function educationPlanOptions(): array
    {
        return EducationPlan::with(['versions' => fn ($query) => $query->latest('id'), 'versions.competenceAreas.competencies.variants'])
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))
            ->orderBy('title')->get()->map(function (EducationPlan $plan): array {
                $version = $plan->versions->first();

                return [
                    'id' => $plan->id,
                    'plan_code' => $plan->external_identifier,
                    'title' => $plan->title,
                    'version_id' => $version?->id,
                    'version' => $version?->external_identifier,
                    'competencies' => $version?->competenceAreas->flatMap(fn ($area) => $area->competencies->map(fn ($competency) => ['id' => $competency->id, 'external_identifier' => $competency->external_identifier, 'number' => $competency->number, 'text' => $competency->text ?: $competency->variants->pluck('text')->filter()->implode(' / '), 'variants' => $competency->variants->map(fn ($variant) => ['level' => $variant->level?->external_identifier, 'text' => $variant->text])->values()->all(), 'area' => $area->title]))->values()->all() ?? [],
                ];
            })->values()->all();
    }

    private function resolveTopicCompetencies(CurriculumVersion $version): void
    {
        $planIds = $version->bindings()->whereNotNull('education_plan_id')->pluck('education_plan_id');
        $versionIds = EducationPlanVersion::whereIn('education_plan_id', $planIds)->pluck('id');
        $version->load('topics.competencies');
        foreach ($version->topics as $topic) {
            foreach ($topic->competencies as $competency) {
                $match = EducationPlanCompetency::whereIn('external_identifier', [$competency->external_identifier])
                    ->whereHas('area', fn ($query) => $query->whereIn('education_plan_version_id', $versionIds))->first();
                $competency->update([
                    'education_plan_competency_id' => $match?->id,
                    'display' => $match?->external_identifier ?? $competency->display,
                    'raw_text' => $match?->text ?? $competency->raw_text,
                ]);
            }
        }
    }

    private function ensureVisible(Curriculum $curriculum): void
    {
        abort_unless($curriculum->organization_id === null || $curriculum->organization_id === auth()->user()->organization_id, 404);
    }
}
