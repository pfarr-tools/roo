<?php

namespace App\Http\Controllers;

use App\Models\AssessmentTask;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetency;
use App\Models\GroupSongbook;
use App\Models\Lesson;
use App\Models\LessonPhase;
use App\Models\MaterialItem;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
use App\Models\SongVersion;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\TeachingUnitCompetency;
use App\Services\CompetencyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ResourceLibraryController extends Controller
{
    public function createAssessmentTask(Request $request)
    {
        return Inertia::render('AssessmentTask/Edit', [
            'backUrl' => route('resources.library'),
            'submitUrl' => route('resources.library.assessment-tasks.store'),
            'method' => 'post',
            'libraryMode' => true,
            'competencyField' => 'competency_id',
            'competencies' => $this->competencies($request->user()->organization_id),
            'educationPlans' => EducationPlan::whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id)->orderBy('title')->get(['id', 'title', 'external_identifier']),
        ]);
    }

    public function editAssessmentTask(Request $request, int $assessmentTask)
    {
        $task = $this->item($request, 'assessment-task', $assessmentTask);
        $task->load(['educationPlanCompetency.variants', 'levels']);

        return Inertia::render('AssessmentTask/Edit', [
            'backUrl' => route('resources.library'),
            'submitUrl' => route('resources.library.update', ['assessment-task', $task->id]),
            'method' => 'put',
            'libraryMode' => true,
            'competencyField' => 'competency_id',
            'task' => $task,
            'competencies' => $this->competencies($request->user()->organization_id),
            'educationPlans' => EducationPlan::whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id)->orderBy('title')->get(['id', 'title', 'external_identifier']),
        ]);
    }

    public function associationStatus(Request $request, TeachingGroup $teachingGroup, string $kind, int $resource): JsonResponse
    {
        $this->authorize('update', $teachingGroup);
        $item = $this->item($request, $kind, $resource);

        return response()->json(['association_count' => $this->associationCount($item, $kind)]);
    }

    public function detach(Request $request, TeachingGroup $teachingGroup, string $kind, int $resource): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['target_type' => ['required', 'in:unit,lesson,phase'], 'target_id' => ['required', 'integer'], 'permanent' => ['sometimes', 'boolean']]);
        $item = $this->item($request, $kind, $resource);
        $this->assertTarget($teachingGroup, $data['target_type'], $data['target_id']);

        if ($data['permanent'] ?? false) {
            abort_unless($this->associationCount($item, $kind) <= 1, 422, 'Das Element ist noch an anderer Stelle zugeordnet.');
            if ($kind === 'file') {
                Storage::disk('local')->delete($item->storage_path);
            }
            $item->delete();

            return back()->with('success', 'Element wurde dauerhaft gelöscht.');
        }

        if ($kind === 'song') {
            $target = $data['target_type'] === 'unit' ? TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']) : ($data['target_type'] === 'lesson' ? Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']) : $this->phaseTarget($teachingGroup, $data['target_id']));
            $target->songs()->detach($item->id);
        } elseif ($kind === 'songbook') {
            $target = $data['target_type'] === 'phase' ? $this->phaseTarget($teachingGroup, $data['target_id']) : ($data['target_type'] === 'lesson' ? Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']) : TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']));
            $target->songbooks()->detach($item->id);
        } elseif ($kind === 'assessment-task') {
            abort_unless($data['target_type'] === 'lesson', 422, 'Eine Prüfungsaufgabe kann nur einer Stunde zugeordnet werden.');
            $this->lessonTarget($teachingGroup, $data['target_id'])->assessmentTasks()->detach($item->id);
        } elseif ($kind === 'file' || $kind === 'resource') {
            if ($data['target_type'] === 'phase') {
                $this->phaseTarget($teachingGroup, $data['target_id'])->resources()->detach($item->id);
            } else {
                $item->update($data['target_type'] === 'lesson' ? ['lesson_id' => null] : ['teaching_unit_id' => null]);
            }
        } else {
            $relation = $data['target_type'] === 'phase' ? $this->phaseTarget($teachingGroup, $data['target_id'])->materialItems() : ($data['target_type'] === 'lesson' ? Lesson::findOrFail($data['target_id'])->materialItems() : TeachingUnit::findOrFail($data['target_id'])->materialItems());
            $relation->detach($item->id);
        }

        return back()->with('success', 'Zuordnung wurde entfernt.');
    }

    public function assign(Request $request, TeachingGroup $teachingGroup, ResourceReference $resource): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($resource->organization_id === $request->user()->organization_id, 404);
        $data = $request->validate(['target_type' => ['required', 'in:unit,lesson,phase'], 'target_id' => ['required', 'integer']]);
        if ($data['target_type'] === 'phase') {
            $target = $this->phaseTarget($teachingGroup, $data['target_id']);
            $target->resources()->syncWithoutDetaching([$resource->id]);
        } elseif ($data['target_type'] === 'unit') {
            $target = TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']);
            $resource->update(['teaching_unit_id' => $target->id, 'lesson_id' => null]);
        } else {
            $target = Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']);
            $resource->update(['teaching_unit_id' => $target->teaching_unit_id, 'lesson_id' => $target->id]);
        }

        return back()->with('success', 'Datei wurde zugeordnet.');
    }

    public function assignItem(Request $request, TeachingGroup $teachingGroup, string $kind, int $resource): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $item = $this->item($request, $kind, $resource);
        $data = $request->validate(['target_type' => ['required', 'in:unit,lesson,phase'], 'target_id' => ['required', 'integer']]);
        $this->assertTarget($teachingGroup, $data['target_type'], $data['target_id']);

        if ($kind === 'song') {
            $target = $data['target_type'] === 'unit' ? TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']) : ($data['target_type'] === 'lesson' ? Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']) : $this->phaseTarget($teachingGroup, $data['target_id']));
            $target->songs()->syncWithoutDetaching([$item->id]);
            $this->addToSongbook($teachingGroup, $item);

            return back()->with('success', 'Lied wurde zugeordnet.');
        }

        if ($kind === 'songbook') {
            $target = $data['target_type'] === 'unit' ? TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']) : ($data['target_type'] === 'lesson' ? Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']) : $this->phaseTarget($teachingGroup, $data['target_id']));
            $target->songbooks()->syncWithoutDetaching([$item->id]);

            return back()->with('success', 'Gruppenliederbuch wurde zugeordnet.');
        }

        if ($kind === 'assessment-task') {
            abort_unless($data['target_type'] === 'lesson', 422, 'Eine Prüfungsaufgabe kann nur einer Stunde zugeordnet werden.');
            $this->lessonTarget($teachingGroup, $data['target_id'])->assessmentTasks()->syncWithoutDetaching([$item->id]);

            return back()->with('success', 'Prüfungsaufgabe wurde der Stunde zugeordnet.');
        }

        if ($data['target_type'] === 'unit') {
            $target = TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']);
            if ($kind === 'material') {
                $target->materialItems()->syncWithoutDetaching([$item->id]);
            } else {
                $item->update(['teaching_unit_id' => $target->id, 'lesson_id' => null]);
            }
        } elseif ($data['target_type'] === 'phase') {
            $target = $this->phaseTarget($teachingGroup, $data['target_id']);
            if ($kind === 'material') {
                $target->materialItems()->syncWithoutDetaching([$item->id]);
            } else {
                $target->resources()->syncWithoutDetaching([$item->id]);
            }
        } else {
            $target = Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']);
            if ($kind === 'material') {
                $target->materialItems()->syncWithoutDetaching([$item->id]);
            } else {
                $item->update(['teaching_unit_id' => $target->teaching_unit_id, 'lesson_id' => $target->id]);
            }
        }

        return back()->with('success', 'Element wurde zugeordnet.');
    }

    public function __invoke(Request $request, ?TeachingGroup $teachingGroup = null)
    {
        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');
        $sort = (string) $request->query('sort', 'name');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $organizationId = $request->user()->organization_id;
        if ($teachingGroup) {
            $this->authorize('view', $teachingGroup);
            abort_unless($teachingGroup->organization_id === $organizationId, 404);
        }
        $matches = collect();

        if ($type === 'all' || $type === 'file') {
            $matches = $matches->concat(ResourceReference::where('organization_id', $organizationId)->with(['teachingUnit:id,title', 'lesson:id,title', 'phases:id,title'])->when($query !== '', fn ($builder) => $builder->where('original_name', 'like', "%{$query}%"))->orderBy('original_name')->when($request->expectsJson(), fn ($builder) => $builder->limit(30))->get(['id', 'teaching_unit_id', 'lesson_id', 'original_name', 'description', 'copyrights', 'mime_type', 'size', 'page_count', 'created_at'])->map(fn ($item) => $item->setAttribute('kind', 'file')));
        }
        if ($type === 'all' || $type === 'resource') {
            $matches = $matches->concat(ResourceLink::where('organization_id', $organizationId)->with(['teachingUnit:id,title', 'lesson:id,title', 'phases:id,title'])->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('title', 'like', "%{$query}%")->orWhere('url', 'like', "%{$query}%")))->orderBy('title')->when($request->expectsJson(), fn ($builder) => $builder->limit(30))->get(['id', 'teaching_unit_id', 'lesson_id', 'title', 'url', 'description', 'created_at'])->map(fn ($item) => $item->setAttribute('kind', 'resource')));
        }
        if ($type === 'all' || $type === 'material') {
            $matches = $matches->concat(MaterialItem::where('organization_id', $organizationId)->with(['teachingUnits:id,title', 'lessons:id,title', 'phases:id,title'])->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('name', 'like', "%{$query}%")->orWhere('material_number', 'like', "%{$query}%")->orWhere('storage_location', 'like', "%{$query}%")))->orderBy('name')->when($request->expectsJson(), fn ($builder) => $builder->limit(30))->get(['id', 'name', 'material_number', 'storage_location', 'description', 'image_path', 'image_mime_type', 'created_at'])->map(fn ($item) => $item->setAttribute('kind', 'material')));
        }
        if ($type === 'all' || $type === 'song') {
            $matches = $matches->concat(SongVersion::whereHas('song', fn ($builder) => $builder->whereNull('organization_id')->orWhere('organization_id', $organizationId))->with(['song:id,organization_id,title,author,composer', 'sheet'])->when($query !== '', fn ($builder) => $builder->whereHas('song', fn ($song) => $song->where('title', 'like', "%{$query}%")))->orderBy('name')->when($request->expectsJson(), fn ($builder) => $builder->limit(30))->get()->map(fn ($item) => $item->setAttribute('kind', 'song')));
        }
        if ($type === 'all' || $type === 'assessment-task') {
            $matches = $matches->concat(AssessmentTask::where('organization_id', $organizationId)->with(['competency.unit', 'educationPlan:id,title', 'educationPlanCompetency.area', 'educationPlanCompetency.variants.level', 'lessons:id,title'])->when($query !== '', fn ($builder) => $builder->where('title', 'like', "%{$query}%"))->orderBy('title')->when($request->expectsJson(), fn ($builder) => $builder->limit(30))->get()->map(fn ($item) => $item->setAttribute('kind', 'assessment-task')));
        }
        if ($teachingGroup && ($type === 'all' || $type === 'songbook')) {
            $book = $teachingGroup->songbook()->withCount(['entries', 'lessons', 'phases'])->first();
            if ($book) {
                $matches->push($book->setAttribute('kind', 'songbook'));
            }
        }

        if ($request->expectsJson()) {
            return response()->json($matches->values()->map(fn ($item) => $item->kind === 'assessment-task' ? $this->present($item) : ($item->kind === 'song' ? ['id' => $item->id, 'kind' => 'song', 'name' => $item->song?->title, 'title' => $item->song?->title, 'version' => $item->name] : ($item->kind === 'songbook' ? ['id' => $item->id, 'kind' => 'songbook', 'name' => 'Gruppenliederbuch', 'title' => 'Gruppenliederbuch', 'entries_count' => $item->entries_count] : $item))));
        }

        $items = $matches->sortBy(fn ($item) => Str::lower((string) ($item->getAttribute($sort) ?? $item->getAttribute('name') ?? $item->getAttribute('title') ?? $item->getAttribute('original_name'))), SORT_NATURAL, $direction === 'desc')->values()->map(fn ($item) => $this->present($item));
        $totalCount = ResourceReference::where('organization_id', $organizationId)->count()
            + ResourceLink::where('organization_id', $organizationId)->count()
            + MaterialItem::where('organization_id', $organizationId)->count()
            + SongVersion::whereHas('song', fn ($builder) => $builder->whereNull('organization_id')->orWhere('organization_id', $organizationId))->count()
            + AssessmentTask::where('organization_id', $organizationId)->count();

        return Inertia::render('Resources/Library', [
            'items' => $items,
            'filters' => ['q' => $query, 'type' => $type, 'sort' => $sort, 'direction' => $direction],
            'counts' => $matches->countBy('kind')->put('total', $totalCount),
            'competencies' => $this->competencies($organizationId),
            'educationPlans' => EducationPlan::whereNull('organization_id')->orWhere('organization_id', $organizationId)->orderBy('title')->get(['id', 'title', 'external_identifier']),
        ]);
    }

    public function educationPlanCompetencyPicker(Request $request, EducationPlan $educationPlan, CompetencyResolver $competencyResolver): JsonResponse
    {
        abort_unless(is_null($educationPlan->organization_id) || $educationPlan->organization_id === $request->user()->organization_id, 404);
        $competencies = EducationPlanCompetency::whereHas('area.version', fn ($query) => $query->where('education_plan_id', $educationPlan->id))
            ->with(['area:id,kind,external_identifier,title', 'variants:id,education_plan_competency_id,education_plan_level_id,text,position', 'variants.level:id,label'])
            ->orderBy('external_identifier')->get(['id', 'education_plan_competence_area_id', 'external_identifier', 'number', 'text'])
            ->each(function (EducationPlanCompetency $competency) use ($competencyResolver): void {
                $presentation = $competencyResolver->present($competency);
                $presentation['kind'] = $competency->area?->kind ?? $presentation['kind'];
                $competency->setAttribute('competency_presentation', $presentation);
                $competency->setAttribute('competency_area', ['identifier' => $competency->area?->external_identifier, 'title' => $competency->area?->title, 'kind' => $competency->area?->kind]);
                $competency->setAttribute('has_differentiation', $competency->variants->contains(fn ($variant) => filled($variant->education_plan_level_id)));
            });

        return response()->json(['competencies' => $competencies, 'covered_hours' => []]);
    }

    public function storeFile(Request $request): RedirectResponse
    {
        $data = $request->validate(['resource' => ['required', 'file', 'max:51200'], 'description' => ['nullable', 'string', 'max:1000'], 'copyrights' => ['nullable', 'string', 'max:1000']]);
        $file = $data['resource'];
        $path = $file->storeAs('library', Str::uuid().($file->getClientOriginalExtension() ? '.'.$file->getClientOriginalExtension() : ''), 'local');
        ResourceReference::create(['organization_id' => $request->user()->organization_id, 'original_name' => $file->getClientOriginalName(), 'description' => $data['description'] ?? null, 'copyrights' => $data['copyrights'] ?? null, 'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()), 'security_status' => 'pending', 'source' => 'user_upload', 'version' => 1]);

        return back()->with('success', 'Datei wurde zur Bibliothek hinzugefügt.');
    }

    public function storeResource(Request $request): RedirectResponse
    {
        ResourceLink::create(['organization_id' => $request->user()->organization_id, ...$request->validate(['title' => ['required', 'string', 'max:255'], 'url' => ['required', 'url', 'max:2000'], 'description' => ['nullable', 'string', 'max:1000']])]);

        return back()->with('success', 'Ressource wurde zur Bibliothek hinzugefügt.');
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'material_number' => ['nullable', 'string', 'max:255'], 'storage_location' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000'], 'image' => ['nullable', 'image', 'max:10240']]);
        $image = $data['image'] ?? null;
        unset($data['image']);
        $item = MaterialItem::create(['organization_id' => $request->user()->organization_id, ...$data]);
        if ($image) {
            $item->update(['image_path' => $image->store('material-items', 'local'), 'image_mime_type' => $image->getMimeType()]);
        }

        return back()->with('success', 'Material wurde zur Bibliothek hinzugefügt.');
    }

    public function storeAssessmentTask(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'solution' => ['nullable', 'string'], 'max_points' => ['nullable', 'integer', 'min:1'], 'competency_id' => ['nullable', 'integer'], 'education_plan_id' => ['nullable', 'integer'], 'education_plan_competency_id' => ['nullable', 'integer'], 'levels' => ['sometimes', 'array'], 'levels.*' => ['in:G,M,E']]);
        $attributes = ['organization_id' => $request->user()->organization_id, 'title' => $data['title'], 'solution' => $data['solution'] ?? null, 'max_points' => $data['max_points'] ?? null, 'level' => collect($data['levels'] ?? [])->first()];
        if (filled($data['education_plan_id'] ?? null) && filled($data['education_plan_competency_id'] ?? null)) {
            abort_unless(EducationPlan::whereKey($data['education_plan_id'])->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->exists(), 422, 'Der Bildungsplan ist nicht verfügbar.');
            abort_unless(EducationPlanCompetency::whereKey($data['education_plan_competency_id'])->whereHas('area.version', fn ($query) => $query->where('education_plan_id', $data['education_plan_id']))->exists(), 422, 'Die Kompetenz gehört nicht zum gewählten Bildungsplan.');
            $attributes += ['education_plan_id' => $data['education_plan_id'], 'education_plan_competency_id' => $data['education_plan_competency_id']];
        } else {
            $competency = TeachingUnitCompetency::whereKey($data['competency_id'])->whereHas('unit', fn ($query) => $query->where('organization_id', $request->user()->organization_id))->firstOrFail();
            $attributes['teaching_unit_competency_id'] = $competency->id;
        }
        $task = AssessmentTask::create($attributes);
        $task->levels()->delete();
        $task->levels()->createMany(collect($data['levels'] ?? [])->map(fn ($level) => ['level' => $level])->all());

        return back()->with('success', 'Prüfungsaufgabe wurde zur Bibliothek hinzugefügt.');
    }

    public function storeAndAssign(Request $request, TeachingGroup $teachingGroup, string $kind): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['target_type' => ['required', 'in:unit,lesson'], 'target_id' => ['required', 'integer']]);
        $this->assertTarget($teachingGroup, $data['target_type'], $data['target_id']);
        $target = $data['target_type'] === 'unit'
            ? TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id'])
            : Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']);

        if ($kind === 'resource') {
            $attributes = $request->validate(['title' => ['required', 'string', 'max:255'], 'url' => ['required', 'url', 'max:2000'], 'description' => ['nullable', 'string', 'max:1000']]);
            ResourceLink::create($attributes + ['organization_id' => $teachingGroup->organization_id, 'teaching_unit_id' => $target instanceof Lesson ? $target->teaching_unit_id : $target->id, 'lesson_id' => $target instanceof Lesson ? $target->id : null]);
        } elseif ($kind === 'material') {
            $item = MaterialItem::create(['organization_id' => $teachingGroup->organization_id, ...$request->validate(['name' => ['required', 'string', 'max:255'], 'material_number' => ['nullable', 'string', 'max:255'], 'storage_location' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000']])]);
            $target->materialItems()->syncWithoutDetaching([$item->id]);
        } else {
            abort(404);
        }

        return back()->with('success', 'Element wurde angelegt und zugeordnet.');
    }

    public function updateItem(Request $request, string $kind, int $resource): RedirectResponse
    {
        $item = $this->item($request, $kind, $resource);
        $rules = match ($kind) {
            'file' => ['description' => ['nullable', 'string', 'max:1000'], 'copyrights' => ['nullable', 'string', 'max:1000']],
            'resource' => ['title' => ['required', 'string', 'max:255'], 'url' => ['required', 'url', 'max:2000'], 'description' => ['nullable', 'string', 'max:1000']],
            'material' => ['name' => ['required', 'string', 'max:255'], 'material_number' => ['nullable', 'string', 'max:255'], 'storage_location' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000']],
            'assessment-task' => ['title' => ['required', 'string', 'max:255'], 'solution' => ['nullable', 'string'], 'max_points' => ['nullable', 'integer', 'min:1'], 'education_plan_id' => ['required', 'integer'], 'education_plan_competency_id' => ['required', 'integer'], 'levels' => ['sometimes', 'array'], 'levels.*' => ['in:G,M,E']],
        };
        $validated = $request->validate($rules);
        if ($kind === 'assessment-task') {
            abort_unless(EducationPlan::whereKey($validated['education_plan_id'])->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->exists(), 422, 'Der Bildungsplan ist nicht verfügbar.');
            abort_unless(EducationPlanCompetency::whereKey($validated['education_plan_competency_id'])->whereHas('area.version', fn ($query) => $query->where('education_plan_id', $validated['education_plan_id']))->exists(), 422, 'Die Kompetenz gehört nicht zum gewählten Bildungsplan.');
            $item->update($validated + ['teaching_unit_competency_id' => null]);
        } else {
            $item->update($validated);
        }
        if ($kind === 'assessment-task') {
            $levels = $request->input('levels', []);
            $item->levels()->delete();
            $item->levels()->createMany(collect($levels)->map(fn ($level) => ['level' => $level])->all());
            $item->update(['level' => collect($levels)->first()]);
        }

        return back()->with('success', 'Bibliothekseintrag wurde gespeichert.');
    }

    public function uploadMaterialImage(Request $request, int $resource): RedirectResponse
    {
        $item = MaterialItem::where('organization_id', $request->user()->organization_id)->findOrFail($resource);
        $data = $request->validate(['image' => ['required', 'image', 'max:10240']]);
        if ($item->image_path) {
            Storage::disk('local')->delete($item->image_path);
        }
        $path = $data['image']->store('material-items', 'local');
        $item->update(['image_path' => $path, 'image_mime_type' => $data['image']->getMimeType()]);

        return back()->with('success', 'Bild wurde gespeichert.');
    }

    public function materialImage(Request $request, int $resource)
    {
        $item = MaterialItem::where('organization_id', $request->user()->organization_id)->findOrFail($resource);
        abort_unless($item->image_path && Storage::disk('local')->exists($item->image_path), 404);

        return response()->file(Storage::disk('local')->path($item->image_path), ['Content-Type' => $item->image_mime_type ?: 'application/octet-stream']);
    }

    public function destroyItem(Request $request, string $kind, int $resource): RedirectResponse
    {
        $item = $this->item($request, $kind, $resource);
        abort_unless($this->associationCount($item, $kind) === 0, 422, 'Der Eintrag ist noch zugeordnet und kann nicht gelöscht werden.');
        if ($kind === 'file') {
            Storage::disk('local')->delete($item->storage_path);
        }
        $item->delete();

        return back()->with('success', 'Bibliothekseintrag wurde gelöscht.');
    }

    public function download(Request $request, int $resource)
    {
        $item = ResourceReference::where('organization_id', $request->user()->organization_id)->findOrFail($resource);

        return Storage::disk('local')->download($item->storage_path, $item->original_name);
    }

    public function preview(Request $request, int $resource)
    {
        $item = ResourceReference::where('organization_id', $request->user()->organization_id)->findOrFail($resource);

        return response()->file(Storage::disk('local')->path($item->storage_path), ['Content-Type' => $item->mime_type ?: 'application/octet-stream']);
    }

    private function present($item): array
    {
        $relationships = collect([$item->teachingUnit ?? null, $item->lesson ?? null])->merge($item->teachingUnits ?? [])->merge($item->lessons ?? [])->merge($item->phases ?? [])->map(fn ($relation) => $relation->title ?? $relation->name ?? null)->filter()->unique()->values()->all();
        $songDescription = $item->kind === 'song' ? $this->songCredits($item) : null;
        $taskDescription = $item->kind === 'assessment-task' ? $this->assessmentTaskDescription($item) : null;

        return ['id' => $item->id, 'song_id' => $item->song?->id, 'kind' => $item->kind, 'name' => $item->kind === 'songbook' ? 'Gruppenliederbuch' : ($item->song?->title ?? $item->original_name ?? $item->title ?? $item->name), 'description' => $taskDescription ?? $songDescription ?? $item->description ?? $item->song?->copyright_notice, 'copyrights' => $item->copyrights, 'original_name' => $item->original_name, 'title' => $item->song?->title ?? $item->title ?? ($item->kind === 'songbook' ? 'Gruppenliederbuch' : null), 'url' => $item->url, 'mime_type' => $item->mime_type, 'size' => $item->size, 'page_count' => $item->page_count, 'material_number' => $item->material_number, 'storage_location' => $item->storage_location, 'solution' => $item->solution, 'max_points' => $item->max_points, 'competency_id' => $item->teaching_unit_competency_id, 'competency' => $item->kind === 'assessment-task' ? $this->assessmentTaskCompetencyText($item) : $item->competency?->local_wording, 'education_plan_id' => $item->education_plan_id, 'education_plan_competency_id' => $item->education_plan_competency_id, 'education_plan' => $item->educationPlan?->title, 'has_differentiation' => $item->kind === 'assessment-task' && $item->educationPlanCompetency?->variants?->contains(fn ($variant) => filled($variant->education_plan_level_id)), 'levels' => $item->kind === 'assessment-task' ? $item->levels->pluck('level')->values()->all() : [], 'image_url' => $item->image_path ? route('resources.library.materials.image', $item->id) : null, 'relationships' => $relationships, 'created_at' => $item->created_at?->toISOString(), 'can_delete' => $item->kind === 'song' ? $item->song?->organization_id === auth()->user()->organization_id : null, 'generated_sheet_path' => $item->generated_sheet_path, 'generated_sheet_a4_path' => $item->generated_sheet_a4_path, 'generated_chord_sheet_paths' => $item->generated_chord_sheet_paths, 'sheet_id' => $item->sheet?->id];
    }

    private function assessmentTaskDescription(AssessmentTask $task): string
    {
        return collect([$this->assessmentTaskCompetencyIdentifier($task) ?: $task->competency?->local_wording ?: 'Kompetenz '.$task->teaching_unit_competency_id, $task->levels->pluck('level')->implode(', ')])->filter()->implode(' · ');
    }

    private function assessmentTaskCompetencyIdentifier(AssessmentTask $task): ?string
    {
        $identifier = $task->educationPlanCompetency?->external_identifier;
        if ($identifier) {
            return preg_replace('/^(\d+\.\d+\.\d+)\.(\d+)$/', '$1 ($2)', $identifier);
        }

        return $task->educationPlanCompetency?->number;
    }

    private function assessmentTaskCompetencyText(AssessmentTask $task): ?string
    {
        if ($task->educationPlanCompetency) {
            $number = $task->educationPlanCompetency->external_identifier ?: $task->educationPlanCompetency->number;
            $text = $task->educationPlanCompetency->text
                ?: $task->educationPlanCompetency->variants?->pluck('text')->filter()->implode(' / ');

            return collect([$number, $text])->filter()->implode(' – ') ?: null;
        }

        return $task->competency?->local_wording;
    }

    private function songCredits(SongVersion $version): string
    {
        $author = trim((string) $version->song?->author);
        $composer = trim((string) $version->song?->composer);
        if ($author !== '' && $composer !== '' && mb_strtolower($author) === mb_strtolower($composer)) {
            return 'Text & Musik: '.$author;
        }

        return collect([$author !== '' ? 'Text: '.$author : null, $composer !== '' ? 'Musik: '.$composer : null])->filter()->implode(' / ');
    }

    private function item(Request $request, string $kind, int $id): ResourceReference|ResourceLink|MaterialItem|SongVersion|GroupSongbook|AssessmentTask
    {
        $model = match ($kind) {
            'file' => ResourceReference::class,
            'resource' => ResourceLink::class,
            'material' => MaterialItem::class,
            'song' => SongVersion::class,
            'assessment-task' => AssessmentTask::class,
            'songbook' => GroupSongbook::class,
            default => abort(404),
        };

        if ($kind === 'song') {
            return $model::whereKey($id)->whereHas('song', fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->findOrFail($id);
        }
        if ($kind === 'songbook') {
            return $model::whereKey($id)->whereHas('group', fn ($query) => $query->where('organization_id', $request->user()->organization_id))->findOrFail($id);
        }

        return $model::where('organization_id', $request->user()->organization_id)->findOrFail($id);
    }

    private function associationCount(ResourceReference|ResourceLink|MaterialItem|SongVersion|GroupSongbook|AssessmentTask $item, string $kind): int
    {
        if ($kind === 'song') {
            return $item->unitSongs()->count() + $item->lessonSongs()->count() + $item->phaseSongs()->count();
        }
        if ($kind === 'songbook') {
            return $item->teachingUnits()->count() + $item->lessons()->count() + $item->phases()->count();
        }
        if ($kind === 'file') {
            return (int) ($item->teaching_unit_id !== null || $item->lesson_id !== null) + $item->phases()->count();
        }
        if ($kind === 'resource') {
            return (int) ($item->teaching_unit_id !== null) + (int) ($item->lesson_id !== null) + $item->phases()->count();
        }
        if ($kind === 'assessment-task') {
            return $item->lessons()->count() + $item->assessments()->count();
        }

        return $item->teachingUnits()->count() + $item->lessons()->count() + $item->phases()->count() + $item->phaseTemplates()->count();
    }

    private function assertTarget(TeachingGroup $group, string $type, int $id): void
    {
        if ($type === 'phase') {
            $this->phaseTarget($group, $id);
        } elseif ($type === 'unit') {
            abort_unless(TeachingUnit::where('teaching_group_id', $group->id)->whereKey($id)->exists(), 404);
        } else {
            abort_unless(Lesson::whereKey($id)->whereHas('unit', fn ($query) => $query->where('teaching_group_id', $group->id))->exists(), 404);
        }
    }

    private function phaseTarget(TeachingGroup $group, int $id): LessonPhase
    {
        return LessonPhase::whereKey($id)->whereHas('lesson.unit', fn ($query) => $query->where('teaching_group_id', $group->id))->firstOrFail();
    }

    private function lessonTarget(TeachingGroup $group, int $id): Lesson
    {
        return Lesson::whereKey($id)->whereHas('unit', fn ($query) => $query->where('teaching_group_id', $group->id))->firstOrFail();
    }

    private function competencies(int $organizationId)
    {
        return TeachingUnitCompetency::whereHas('unit', fn ($query) => $query->where('organization_id', $organizationId))->with('unit:id,title,teaching_group_id')->get(['id', 'teaching_unit_id', 'local_wording'])->map(fn ($item) => ['id' => $item->id, 'label' => $item->local_wording ?: 'Kompetenz '.$item->id, 'unit' => $item->unit?->title]);
    }

    private function addToSongbook(TeachingGroup $group, SongVersion $version): void
    {
        $book = $group->songbook()->firstOrCreate([]);
        $book->entries()->firstOrCreate(['song_version_id' => $version->id], ['song_number' => ((int) $book->entries()->max('song_number')) + 1, 'added_at' => now()]);
    }
}
