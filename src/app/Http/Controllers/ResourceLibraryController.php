<?php

namespace App\Http\Controllers;

use App\Models\MaterialItem;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\Lesson;
use App\Models\LessonPhase;
use App\Models\SongVersion;
use App\Models\GroupSongbook;
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
        $data = $request->validate(['target_type' => ['required', 'in:unit,lesson,phase'], 'target_id' => ['required', 'integer'], 'permanent' => ['sometimes', 'boolean']]);
        $item = $this->item($request, $kind, $resource);
        $this->assertTarget($teachingGroup, $data['target_type'], $data['target_id']);

        if ($data['permanent'] ?? false) {
            abort_unless($this->associationCount($item, $kind) <= 1, 422, 'Das Element ist noch an anderer Stelle zugeordnet.');
            if ($kind === 'file') Storage::disk('local')->delete($item->storage_path);
            $item->delete();

            return back()->with('success', 'Element wurde dauerhaft gelöscht.');
        }

        if ($kind === 'song') {
            $target = $data['target_type'] === 'unit' ? TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']) : ($data['target_type'] === 'lesson' ? Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']) : $this->phaseTarget($teachingGroup, $data['target_id']));
            $target->songs()->detach($item->id);
        } elseif ($kind === 'songbook') {
            $target = $data['target_type'] === 'phase' ? $this->phaseTarget($teachingGroup, $data['target_id']) : ($data['target_type'] === 'lesson' ? Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']) : TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']));
            $target->songbooks()->detach($item->id);
        } elseif ($kind === 'file' || $kind === 'resource') {
            if ($data['target_type'] === 'phase') $this->phaseTarget($teachingGroup, $data['target_id'])->resources()->detach($item->id);
            else $item->update($data['target_type'] === 'lesson' ? ['lesson_id' => null] : ['teaching_unit_id' => null]);
        } else {
            $relation = $data['target_type'] === 'phase' ? $this->phaseTarget($teachingGroup, $data['target_id'])->materialItems() : ($data['target_type'] === 'lesson' ? Lesson::findOrFail($data['target_id'])->materialItems() : TeachingUnit::findOrFail($data['target_id'])->materialItems());
            $relation->detach($item->id);
        }

        return back()->with('success', 'Zuordnung wurde entfernt.');
    }

    public function assign(Request $request, TeachingGroup $teachingGroup, ResourceReference $resource): \Illuminate\Http\RedirectResponse
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

    public function assignItem(Request $request, TeachingGroup $teachingGroup, string $kind, int $resource): \Illuminate\Http\RedirectResponse
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

        if ($data['target_type'] === 'unit') {
            $target = TeachingUnit::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['target_id']);
            if ($kind === 'material') $target->materialItems()->syncWithoutDetaching([$item->id]);
            else $item->update(['teaching_unit_id' => $target->id, 'lesson_id' => null]);
        } elseif ($data['target_type'] === 'phase') {
            $target = $this->phaseTarget($teachingGroup, $data['target_id']);
            if ($kind === 'material') $target->materialItems()->syncWithoutDetaching([$item->id]);
            else $target->resources()->syncWithoutDetaching([$item->id]);
        } else {
            $target = Lesson::whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->findOrFail($data['target_id']);
            if ($kind === 'material') $target->materialItems()->syncWithoutDetaching([$item->id]);
            else $item->update(['teaching_unit_id' => $target->teaching_unit_id, 'lesson_id' => $target->id]);
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
        if ($teachingGroup && ($type === 'all' || $type === 'songbook')) {
            $book = $teachingGroup->songbook()->withCount(['entries', 'lessons', 'phases'])->first();
            if ($book) $matches->push($book->setAttribute('kind', 'songbook'));
        }

        if ($request->expectsJson()) return response()->json($matches->values()->map(fn ($item) => $item->kind === 'song' ? ['id' => $item->id, 'kind' => 'song', 'name' => $item->song?->title, 'title' => $item->song?->title, 'version' => $item->name] : ($item->kind === 'songbook' ? ['id' => $item->id, 'kind' => 'songbook', 'name' => 'Gruppenliederbuch', 'title' => 'Gruppenliederbuch', 'entries_count' => $item->entries_count] : $item)));

        $items = $matches->sortBy(fn ($item) => Str::lower((string) ($item->getAttribute($sort) ?? $item->getAttribute('name') ?? $item->getAttribute('title') ?? $item->getAttribute('original_name'))), SORT_NATURAL, $direction === 'desc')->values()->map(fn ($item) => $this->present($item));

        return Inertia::render('Resources/Library', [
            'items' => $items,
            'filters' => ['q' => $query, 'type' => $type, 'sort' => $sort, 'direction' => $direction],
            'counts' => $matches->countBy('kind'),
        ]);
    }

    public function storeFile(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['resource' => ['required', 'file', 'max:51200'], 'description' => ['nullable', 'string', 'max:1000'], 'copyrights' => ['nullable', 'string', 'max:1000']]);
        $file = $data['resource'];
        $path = $file->storeAs('library', Str::uuid().($file->getClientOriginalExtension() ? '.'.$file->getClientOriginalExtension() : ''), 'local');
        ResourceReference::create(['organization_id' => $request->user()->organization_id, 'original_name' => $file->getClientOriginalName(), 'description' => $data['description'] ?? null, 'copyrights' => $data['copyrights'] ?? null, 'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()), 'security_status' => 'pending', 'source' => 'user_upload', 'version' => 1]);
        return back()->with('success', 'Datei wurde zur Bibliothek hinzugefügt.');
    }

    public function storeResource(Request $request): \Illuminate\Http\RedirectResponse
    {
        ResourceLink::create(['organization_id' => $request->user()->organization_id, ...$request->validate(['title' => ['required', 'string', 'max:255'], 'url' => ['required', 'url', 'max:2000'], 'description' => ['nullable', 'string', 'max:1000']])]);
        return back()->with('success', 'Ressource wurde zur Bibliothek hinzugefügt.');
    }

    public function storeMaterial(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'material_number' => ['nullable', 'string', 'max:255'], 'storage_location' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000'], 'image' => ['nullable', 'image', 'max:10240']]);
        $image = $data['image'] ?? null;
        unset($data['image']);
        $item = MaterialItem::create(['organization_id' => $request->user()->organization_id, ...$data]);
        if ($image) $item->update(['image_path' => $image->store('material-items', 'local'), 'image_mime_type' => $image->getMimeType()]);
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
            'file' => ['description' => ['nullable', 'string', 'max:1000'], 'copyrights' => ['nullable', 'string', 'max:1000']],
            'resource' => ['title' => ['required', 'string', 'max:255'], 'url' => ['required', 'url', 'max:2000'], 'description' => ['nullable', 'string', 'max:1000']],
            'material' => ['name' => ['required', 'string', 'max:255'], 'material_number' => ['nullable', 'string', 'max:255'], 'storage_location' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000']],
        };
        $item->update($request->validate($rules));
        return back()->with('success', 'Bibliothekseintrag wurde gespeichert.');
    }

    public function uploadMaterialImage(Request $request, int $resource): \Illuminate\Http\RedirectResponse
    {
        $item = MaterialItem::where('organization_id', $request->user()->organization_id)->findOrFail($resource);
        $data = $request->validate(['image' => ['required', 'image', 'max:10240']]);
        if ($item->image_path) Storage::disk('local')->delete($item->image_path);
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
        $songDescription = $item->kind === 'song' ? $this->songCredits($item) : null;
        return ['id' => $item->id, 'song_id' => $item->song?->id, 'kind' => $item->kind, 'name' => $item->kind === 'songbook' ? 'Gruppenliederbuch' : ($item->song?->title ?? $item->original_name ?? $item->title ?? $item->name), 'description' => $songDescription ?? $item->description ?? $item->song?->copyright_notice, 'copyrights' => $item->copyrights, 'original_name' => $item->original_name, 'title' => $item->song?->title ?? $item->title ?? ($item->kind === 'songbook' ? 'Gruppenliederbuch' : null), 'url' => $item->url, 'mime_type' => $item->mime_type, 'size' => $item->size, 'page_count' => $item->page_count, 'material_number' => $item->material_number, 'storage_location' => $item->storage_location, 'image_url' => $item->image_path ? route('resources.library.materials.image', $item->id) : null, 'relationships' => $relationships, 'created_at' => $item->created_at?->toISOString(), 'can_delete' => $item->kind === 'song' ? $item->song?->organization_id === auth()->user()->organization_id : null, 'generated_sheet_path' => $item->generated_sheet_path, 'generated_sheet_a4_path' => $item->generated_sheet_a4_path, 'sheet_id' => $item->sheet?->id];
    }

    private function songCredits(SongVersion $version): string
    {
        $author = trim((string) $version->song?->author);
        $composer = trim((string) $version->song?->composer);
        if ($author !== '' && $composer !== '' && mb_strtolower($author) === mb_strtolower($composer)) return 'Text & Musik: '.$author;
        return collect([$author !== '' ? 'Text: '.$author : null, $composer !== '' ? 'Musik: '.$composer : null])->filter()->implode(' / ');
    }

    private function item(Request $request, string $kind, int $id): ResourceReference|ResourceLink|MaterialItem|SongVersion|GroupSongbook
    {
        $model = match ($kind) {
            'file' => ResourceReference::class,
            'resource' => ResourceLink::class,
            'material' => MaterialItem::class,
            'song' => SongVersion::class,
            'songbook' => GroupSongbook::class,
            default => abort(404),
        };

        if ($kind === 'song') return $model::whereKey($id)->whereHas('song', fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->findOrFail($id);
        if ($kind === 'songbook') return $model::whereKey($id)->whereHas('group', fn ($query) => $query->where('organization_id', $request->user()->organization_id))->findOrFail($id);
        return $model::where('organization_id', $request->user()->organization_id)->findOrFail($id);
    }

    private function associationCount(ResourceReference|ResourceLink|MaterialItem|SongVersion|GroupSongbook $item, string $kind): int
    {
        if ($kind === 'song') return $item->unitSongs()->count() + $item->lessonSongs()->count() + $item->phaseSongs()->count();
        if ($kind === 'songbook') return $item->teachingUnits()->count() + $item->lessons()->count() + $item->phases()->count();
        if ($kind === 'file') return (int) ($item->teaching_unit_id !== null || $item->lesson_id !== null) + $item->phases()->count();
        if ($kind === 'resource') return (int) ($item->teaching_unit_id !== null) + (int) ($item->lesson_id !== null) + $item->phases()->count();

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

    private function addToSongbook(TeachingGroup $group, SongVersion $version): void
    {
        $book = $group->songbook()->firstOrCreate([]);
        $book->entries()->firstOrCreate(['song_version_id' => $version->id], ['song_number' => ((int) $book->entries()->max('song_number')) + 1, 'added_at' => now()]);
    }
}
