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
            ->orderByRaw('organization_id is null asc')
            ->orderBy('title')->get();

        return Inertia::render('Curricula/Index', ['curricula' => $curricula, 'search' => $search]);
    }

    public function create(): Response
    {
        $sources = CurriculumVersion::with(['curriculum:id,title,school_type,grades,variant'])->withCount('topics')->where('is_editable', false)->whereHas('curriculum', fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))->orderBy('id')->get();

        return Inertia::render('Curricula/Create', ['sources' => $sources, 'schoolTypes' => $this->schoolTypeOptions()]);
    }

    public function compare(Request $request): Response
    {
        $available = Curriculum::query()->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))->orderBy('title')->get(['id', 'title', 'school_type', 'grades']);
        $selectedIds = collect([$request->integer('left'), $request->integer('right')])->filter()->unique()->values();
        $selected = Curriculum::with(['versions' => fn ($query) => $query->latest('id'), 'versions.topics' => fn ($query) => $query->withCount('competencies')->orderByRaw('year is null desc')->orderBy('year')->orderBy('position')])->whereIn('id', $selectedIds)->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))->get()->keyBy('id');

        return Inertia::render('Curricula/Compare', ['curricula' => $available, 'left' => $this->comparisonData($selected->get($selectedIds->get(0))), 'right' => $this->comparisonData($selected->get($selectedIds->get(1))), 'selected' => ['left' => $selectedIds->get(0), 'right' => $selectedIds->get(1)]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'school_type' => ['nullable', 'string', 'max:50'], 'grades' => ['nullable', 'array'], 'grades.*' => ['integer', 'min:1', 'max:13'], 'denominations' => ['nullable', 'array'], 'denominations.*' => ['string', 'max:50'], 'source_version_ids' => ['nullable', 'array'], 'source_version_ids.*' => ['integer', 'exists:curriculum_versions,id']]);
        $curriculum = DB::transaction(function () use ($data): Curriculum {
            $sourceVersionIds = array_values($data['source_version_ids'] ?? []);
            $sourceVersions = CurriculumVersion::with(['curriculum', 'bindings', 'topics.competencies', 'topics.profiles', 'topics.perspectives'])->whereIn('id', $sourceVersionIds)->where('is_editable', false)->whereHas('curriculum', fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))->get();
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
                    foreach ($topic->perspectives as $perspective) {
                        $copy->perspectives()->create($perspective->only(['denomination', 'text']));
                    }
                }
            }

            return $curriculum;
        });

        return redirect()->route('curricula.show', $curriculum);
    }

    public function storeVersion(Curriculum $curriculum): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        $source = $curriculum->versions()->with(['bindings', 'topics.competencies', 'topics.profiles', 'topics.perspectives'])->latest('id')->firstOrFail();
        abort_unless($source->is_editable, 403);

        $number = ((int) $curriculum->versions()->where('external_identifier', 'like', 'custom-%')->pluck('external_identifier')->map(fn (string $id): int => (int) str_replace('custom-', '', $id))->max()) + 1;
        $version = $curriculum->versions()->create($source->only(['schema_version', 'source_url', 'source_format', 'is_complete', 'conversion_metadata', 'raw_payload']) + ['external_identifier' => 'custom-'.$number, 'is_editable' => true]);
        foreach ($source->bindings as $binding) {
            $version->bindings()->create($binding->only(['education_plan_id', 'plan_code', 'role', 'denomination', 'subject', 'raw_data']));
        }
        foreach ($source->topics as $topic) {
            $copy = $version->topics()->create($topic->only(['source_curriculum_version_id', 'external_identifier', 'number', 'title', 'position', 'year', 'hours', 'notes', 'preparation_questions', 'shared_plan', 'raw_rows']));
            foreach ($topic->competencies as $competency) {
                $copy->competencies()->create($competency->only(['education_plan_competency_id', 'denomination', 'competency_kind', 'external_identifier', 'display', 'raw_text', 'position']));
            }
            foreach ($topic->profiles as $profile) {
                $copy->profiles()->create($profile->only(['denomination', 'perspective']));
            }
            foreach ($topic->perspectives as $perspective) {
                $copy->perspectives()->create($perspective->only(['denomination', 'text']));
            }
        }

        return redirect()->route('curricula.show', $curriculum)->with('success', 'Neue Curriculumfassung wurde angelegt.');
    }

    public function show(Curriculum $curriculum): Response
    {
        $this->ensureVisible($curriculum);
        $version = $curriculum->versions()->latest('id')->with(['bindings.educationPlan', 'topics' => fn ($q) => $q->orderByRaw('year is null desc')->orderBy('year')->orderBy('position'), 'topics.profiles', 'topics.perspectives', 'topics.competencies.educationPlanCompetency', 'topics.sourceVersion.curriculum'])->firstOrFail();

        return Inertia::render('Curricula/Show', ['curriculum' => $curriculum, 'version' => $version, 'educationPlans' => $this->educationPlanOptions(), 'schoolTypes' => $this->schoolTypeOptions(), 'canToggleEditing' => app()->environment() !== 'production' && $curriculum->external_identifier !== null]);
    }

    public function toggleEditing(Curriculum $curriculum): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        abort_unless(app()->environment() !== 'production' && $curriculum->external_identifier !== null, 403);

        $version = $curriculum->versions()->latest('id')->firstOrFail();
        $version->update(['is_editable' => ! $version->is_editable]);

        return back()->with('success', $version->is_editable ? 'Bearbeiten des importierten Curriculums wurde aktiviert.' : 'Bearbeiten des importierten Curriculums wurde deaktiviert.');
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
            'topics' => ['nullable', 'array'],
            'topics.*.id' => ['required', 'integer'],
            'topics.*.perspectives' => ['nullable', 'array'],
            'topics.*.perspectives.*' => ['nullable', 'string', 'max:10000'],
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
            foreach ($data['topics'] ?? [] as $topicData) {
                $topic = $version->topics()->find($topicData['id']);
                if (! $topic) {
                    continue;
                }
                $allowedPerspectives = collect(['common', ...($curriculum->denominations ?? [])])->unique()->values();
                $perspectives = collect($topicData['perspectives'] ?? [])->filter(fn ($text, $denomination): bool => $allowedPerspectives->contains($denomination));
                $topic->perspectives()->whereNotIn('denomination', $allowedPerspectives)->delete();
                foreach ($perspectives as $denomination => $text) {
                    $topic->perspectives()->updateOrCreate(['denomination' => $denomination], ['text' => trim((string) $text)]);
                }
            }
        });

        return back();
    }

    public function destroy(Curriculum $curriculum): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        abort_unless($curriculum->external_identifier === null, 403);
        abort_unless($curriculum->versions()->where('is_editable', true)->exists(), 403);
        $curriculum->delete();

        return redirect()->route('curricula.index');
    }

    public function updateTopic(Request $request, Curriculum $curriculum, CurriculumTopic $topic): RedirectResponse
    {
        $this->ensureVisible($curriculum);
        abort_unless($topic->version->curriculum_id === $curriculum->id, 404);
        abort_unless($topic->version->is_editable, 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:999'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'preparation_questions' => ['nullable', 'string', 'max:10000'],
            'perspectives' => ['nullable', 'array'],
            'perspectives.*' => ['nullable', 'string', 'max:10000'],
        ]);
        $data['preparation_questions'] = collect(preg_split('/\R/', $data['preparation_questions'] ?? ''))
            ->map(fn (string $question): string => trim($question))->filter()->values()->all();
        $allowedPerspectives = collect(['common', ...($curriculum->denominations ?? [])])->unique()->values();
        $perspectives = collect($data['perspectives'] ?? [])->filter(fn ($text, $denomination): bool => $allowedPerspectives->contains($denomination));
        $topic->update(collect($data)->except('perspectives')->all());
        $topic->perspectives()->whereNotIn('denomination', $allowedPerspectives)->delete();
        foreach ($perspectives as $denomination => $text) {
            $topic->perspectives()->updateOrCreate(['denomination' => $denomination], ['text' => trim((string) $text)]);
        }

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
        abort_unless($topic->version->is_editable, 403);
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

    private function schoolTypeOptions(): array
    {
        $curriculumTypes = Curriculum::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))
            ->pluck('school_type');
        $planTypes = EducationPlan::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))
            ->pluck('external_identifier')
            ->map(function (string $identifier): ?string {
                $parts = explode('_', preg_replace('/\([^)]*\)$/', '', $identifier));

                return count($parts) >= 2 ? $parts[count($parts) - 2] : null;
            });

        return $curriculumTypes->merge($planTypes)->filter()->unique()->sort()->values()->all();
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

    private function comparisonData(?Curriculum $curriculum): ?array
    {
        if (! $curriculum) {
            return null;
        }
        $version = $curriculum->versions->first();

        return ['id' => $curriculum->id, 'title' => $curriculum->title, 'school_type' => $curriculum->school_type, 'grades' => $curriculum->grades, 'topics' => $version?->topics->map(fn ($topic) => ['number' => $topic->number, 'title' => $topic->title, 'year' => $topic->year, 'hours' => $topic->hours, 'competencies_count' => $topic->competencies_count])->values()->all() ?? []];
    }
}
