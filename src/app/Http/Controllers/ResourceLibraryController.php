<?php

namespace App\Http\Controllers;

use App\Models\MaterialItem;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ResourceLibraryController extends Controller
{
    public function associationStatus(Request $request, TeachingGroup $teachingGroup, string $kind, int $resource): JsonResponse
    {
        $this->authorize('update', $teachingGroup);
        $item = $this->item($request, $kind, $resource);

        return response()->json(['association_count' => $this->associationCount($item, $kind)]);
    }

    public function detach(Request $request, TeachingGroup $teachingGroup, string $kind, int $resource): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['target_type' => ['required', 'in:unit,lesson'], 'target_id' => ['required', 'integer'], 'permanent' => ['sometimes', 'boolean']]);
        $item = $this->item($request, $kind, $resource);
        $this->assertTarget($teachingGroup, $data['target_type'], $data['target_id']);

        if ($data['permanent'] ?? false) {
            abort_unless($this->associationCount($item, $kind) <= 1, 422, 'Das Element ist noch an anderer Stelle zugeordnet.');
            if ($kind === 'file') Storage::disk('local')->delete($item->storage_path);
            $item->delete();

            return back()->with('success', 'Element wurde dauerhaft gelöscht.');
        }

        if ($kind === 'file' || $kind === 'resource') {
            $item->update($data['target_type'] === 'lesson' ? ['lesson_id' => null] : ['teaching_unit_id' => null]);
        } else {
            $relation = $data['target_type'] === 'lesson' ? Lesson::findOrFail($data['target_id'])->materialItems() : TeachingUnit::findOrFail($data['target_id'])->materialItems();
            $relation->detach($item->id);
        }

        return back()->with('success', 'Zuordnung wurde entfernt.');
    }

    public function assign(Request $request, TeachingGroup $teachingGroup, ResourceReference $resource): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($resource->organization_id === $request->user()->organization_id, 404);
        $data = $request->validate(['target_type' => ['required', 'in:unit,lesson'], 'target_id' => ['required', 'integer']]);
        if ($data['target_type'] === 'unit') {
            $target = TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']);
            $resource->update(['teaching_unit_id' => $target->id, 'lesson_id' => null]);
        } else {
            $target = Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']);
            $resource->update(['teaching_unit_id' => $target->teaching_unit_id, 'lesson_id' => $target->id]);
        }

        return back()->with('success', 'Datei wurde zugeordnet.');
    }

    public function assignItem(Request $request, TeachingGroup $teachingGroup, string $kind, int $resource): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $item = $this->item($request, $kind, $resource);
        $data = $request->validate(['target_type' => ['required', 'in:unit,lesson'], 'target_id' => ['required', 'integer']]);
        $this->assertTarget($teachingGroup, $data['target_type'], $data['target_id']);

        if ($data['target_type'] === 'unit') {
            $target = TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']);
            if ($kind === 'material') $target->materialItems()->syncWithoutDetaching([$item->id]);
            else $item->update(['teaching_unit_id' => $target->id, 'lesson_id' => null]);
        } else {
            $target = Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']);
            if ($kind === 'material') $target->materialItems()->syncWithoutDetaching([$item->id]);
            else $item->update(['teaching_unit_id' => $target->teaching_unit_id, 'lesson_id' => $target->id]);
        }

        return back()->with('success', 'Element wurde zugeordnet.');
    }

    public function __invoke(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');
        $sort = (string) $request->query('sort', 'name');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $organizationId = $request->user()->organization_id;
        $matches = collect();

        if ($type === 'all' || $type === 'file') {
            $matches = $matches->concat(ResourceReference::where('organization_id', $organizationId)->with(['teachingUnit:id,title', 'lesson:id,title', 'phases:id,title'])->when($query !== '', fn ($builder) => $builder->where('original_name', 'like', "%{$query}%"))->orderBy('original_name')->when($request->expectsJson(), fn ($builder) => $builder->limit(30))->get(['id', 'teaching_unit_id', 'lesson_id', 'original_name', 'description', 'mime_type', 'size', 'page_count', 'created_at'])->map(fn ($item) => $item->setAttribute('kind', 'file')));
        }
        if ($type === 'all' || $type === 'resource') {
            $matches = $matches->concat(ResourceLink::where('organization_id', $organizationId)->with(['teachingUnit:id,title', 'lesson:id,title', 'phases:id,title'])->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('title', 'like', "%{$query}%")->orWhere('url', 'like', "%{$query}%")))->orderBy('title')->when($request->expectsJson(), fn ($builder) => $builder->limit(30))->get(['id', 'teaching_unit_id', 'lesson_id', 'title', 'url', 'description', 'created_at'])->map(fn ($item) => $item->setAttribute('kind', 'resource')));
        }
        if ($type === 'all' || $type === 'material') {
            $matches = $matches->concat(MaterialItem::where('organization_id', $organizationId)->with(['teachingUnits:id,title', 'lessons:id,title', 'phases:id,title'])->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('name', 'like', "%{$query}%")->orWhere('material_number', 'like', "%{$query}%")->orWhere('storage_location', 'like', "%{$query}%")))->orderBy('name')->when($request->expectsJson(), fn ($builder) => $builder->limit(30))->get(['id', 'name', 'material_number', 'storage_location', 'description', 'created_at'])->map(fn ($item) => $item->setAttribute('kind', 'material')));
        }

        if ($request->expectsJson()) return response()->json($matches->values());

        $items = $matches->sortBy(fn ($item) => Str::lower((string) ($item->getAttribute($sort) ?? $item->getAttribute('name') ?? $item->getAttribute('title') ?? $item->getAttribute('original_name'))), SORT_NATURAL, $direction === 'desc')->values()->map(fn ($item) => $this->present($item));

        return Inertia::render('Resources/Library', [
            'items' => $items,
            'filters' => ['q' => $query, 'type' => $type, 'sort' => $sort, 'direction' => $direction],
            'counts' => $matches->countBy('kind'),
        ]);
    }

    public function storeFile(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['resource' => ['required', 'file', 'max:51200'], 'description' => ['nullable', 'string', 'max:1000']]);
        $file = $data['resource'];
        $path = $file->storeAs('library', Str::uuid().($file->getClientOriginalExtension() ? '.'.$file->getClientOriginalExtension() : ''), 'local');
        ResourceReference::create(['organization_id' => $request->user()->organization_id, 'original_name' => $file->getClientOriginalName(), 'description' => $data['description'] ?? null, 'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()), 'security_status' => 'pending', 'source' => 'user_upload', 'version' => 1]);
        return back()->with('success', 'Datei wurde zur Bibliothek hinzugefügt.');
    }

    public function storeResource(Request $request): \Illuminate\Http\RedirectResponse
    {
        ResourceLink::create(['organization_id' => $request->user()->organization_id, ...$request->validate(['title' => ['required', 'string', 'max:255'], 'url' => ['required', 'url', 'max:2000'], 'description' => ['nullable', 'string', 'max:1000']])]);
        return back()->with('success', 'Ressource wurde zur Bibliothek hinzugefügt.');
    }

    public function storeMaterial(Request $request): \Illuminate\Http\RedirectResponse
    {
        MaterialItem::create(['organization_id' => $request->user()->organization_id, ...$request->validate(['name' => ['required', 'string', 'max:255'], 'material_number' => ['nullable', 'string', 'max:255'], 'storage_location' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000']])]);
        return back()->with('success', 'Material wurde zur Bibliothek hinzugefügt.');
    }

    public function storeAndAssign(Request $request, TeachingGroup $teachingGroup, string $kind): \Illuminate\Http\RedirectResponse
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

    public function updateItem(Request $request, string $kind, int $resource): \Illuminate\Http\RedirectResponse
    {
        $item = $this->item($request, $kind, $resource);
        $rules = match ($kind) {
            'file' => ['description' => ['nullable', 'string', 'max:1000']],
            'resource' => ['title' => ['required', 'string', 'max:255'], 'url' => ['required', 'url', 'max:2000'], 'description' => ['nullable', 'string', 'max:1000']],
            'material' => ['name' => ['required', 'string', 'max:255'], 'material_number' => ['nullable', 'string', 'max:255'], 'storage_location' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000']],
        };
        $item->update($request->validate($rules));
        return back()->with('success', 'Bibliothekseintrag wurde gespeichert.');
    }

    public function destroyItem(Request $request, string $kind, int $resource): \Illuminate\Http\RedirectResponse
    {
        $item = $this->item($request, $kind, $resource);
        abort_unless($this->associationCount($item, $kind) === 0, 422, 'Der Eintrag ist noch zugeordnet und kann nicht gelöscht werden.');
        if ($kind === 'file') Storage::disk('local')->delete($item->storage_path);
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
        return ['id' => $item->id, 'kind' => $item->kind, 'name' => $item->original_name ?? $item->title ?? $item->name, 'description' => $item->description, 'original_name' => $item->original_name, 'title' => $item->title, 'url' => $item->url, 'mime_type' => $item->mime_type, 'size' => $item->size, 'page_count' => $item->page_count, 'material_number' => $item->material_number, 'storage_location' => $item->storage_location, 'relationships' => $relationships, 'created_at' => $item->created_at?->toISOString()];
    }

    private function item(Request $request, string $kind, int $id): ResourceReference|ResourceLink|MaterialItem
    {
        $model = match ($kind) {
            'file' => ResourceReference::class,
            'resource' => ResourceLink::class,
            'material' => MaterialItem::class,
            default => abort(404),
        };

        return $model::where('organization_id', $request->user()->organization_id)->findOrFail($id);
    }

    private function associationCount(ResourceReference|ResourceLink|MaterialItem $item, string $kind): int
    {
        if ($kind === 'file') return (int) ($item->teaching_unit_id !== null || $item->lesson_id !== null) + $item->phases()->count();
        if ($kind === 'resource') return (int) ($item->teaching_unit_id !== null) + (int) ($item->lesson_id !== null) + $item->phases()->count();

        return $item->teachingUnits()->count() + $item->lessons()->count() + $item->phases()->count() + $item->phaseTemplates()->count();
    }

    private function assertTarget(TeachingGroup $group, string $type, int $id): void
    {
        if ($type === 'unit') {
            abort_unless(TeachingUnit::where('teaching_group_id', $group->id)->whereKey($id)->exists(), 404);
        } else {
            abort_unless(Lesson::whereKey($id)->whereHas('unit', fn ($query) => $query->where('teaching_group_id', $group->id))->exists(), 404);
        }
    }
}
