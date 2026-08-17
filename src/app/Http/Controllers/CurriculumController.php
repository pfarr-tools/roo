<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\CurriculumTopic;
use App\Models\CurriculumVersion;
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
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'school_type' => ['nullable', 'string', 'max:50'], 'grades' => ['nullable', 'array'], 'source_version_ids' => ['required', 'array', 'min:1'], 'source_version_ids.*' => ['integer', 'exists:curriculum_versions,id']]);
        $curriculum = DB::transaction(function () use ($data): Curriculum {
            $sourceVersions = CurriculumVersion::with(['curriculum', 'topics.competencies', 'topics.profiles'])->whereIn('id', $data['source_version_ids'])->where('is_editable', false)->whereHas('curriculum', fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))->get();
            abort_if($sourceVersions->count() !== count(array_unique($data['source_version_ids'])), 422, 'Mindestens eine Vorlage ist nicht verfügbar.');
            $grades = array_values($data['grades'] ?? []);
            if ($grades === []) {
                $grades = $sourceVersions->pluck('curriculum.grades')->flatten()->filter()->unique()->sort()->values()->all();
            }
            $curriculum = Curriculum::create(['organization_id' => auth()->user()->organization_id, 'title' => $data['title'], 'school_type' => $data['school_type'] ?? null, 'grades' => $grades, 'cooperation_model' => 'confessional_cooperative', 'derived_from_id' => $sourceVersions->first()?->curriculum_id]);
            $version = $curriculum->versions()->create(['external_identifier' => 'custom-1', 'schema_version' => '1.0.0', 'is_editable' => true, 'is_complete' => true]);
            $position = 0;
            foreach ($sourceVersions as $source) {
                foreach ($source->topics as $topic) {
                    $sourceGrades = $source->curriculum->grades ?? [];
                    $copy = $version->topics()->create($topic->only(['external_identifier', 'number', 'title', 'position', 'hours', 'notes', 'preparation_questions', 'shared_plan', 'raw_rows']) + ['source_curriculum_version_id' => $source->id, 'year' => $topic->year ?? (count($sourceGrades) === 1 ? (int) $sourceGrades[0] : null), 'position' => $position++]);
                    foreach ($topic->competencies as $competency) {
                        $copy->competencies()->create($competency->only(['denomination', 'competency_kind', 'external_identifier', 'display', 'raw_text', 'position']));
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
        $version = $curriculum->versions()->latest('id')->with(['topics' => fn ($q) => $q->orderByRaw('year is null desc')->orderBy('year')->orderBy('position'), 'topics.profiles', 'topics.competencies', 'topics.sourceVersion.curriculum'])->firstOrFail();

        return Inertia::render('Curricula/Show', ['curriculum' => $curriculum, 'version' => $version]);
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
        abort_unless($curriculum->versions()->where('is_editable', true)->exists(), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'school_type' => ['nullable', 'string', 'max:50'],
            'grades' => ['nullable', 'array'],
            'grades.*' => ['integer', 'min:1', 'max:13'],
        ]);
        $curriculum->update($data);

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
        abort_unless($topic->version->is_editable, 403);
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

    private function ensureVisible(Curriculum $curriculum): void
    {
        abort_unless($curriculum->organization_id === null || $curriculum->organization_id === auth()->user()->organization_id, 404);
    }
}
