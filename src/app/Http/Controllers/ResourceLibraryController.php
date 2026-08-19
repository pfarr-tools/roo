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

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');
        $organizationId = $request->user()->organization_id;
        $matches = collect();

        if ($type === 'all' || $type === 'file') {
            $matches = $matches->concat(ResourceReference::where('organization_id', $organizationId)->when($query !== '', fn ($builder) => $builder->where('original_name', 'like', "%{$query}%"))->orderBy('original_name')->limit(30)->get(['id', 'original_name', 'description', 'mime_type', 'size', 'page_count'])->map(fn ($item) => $item->setAttribute('kind', 'file')));
        }
        if ($type === 'all' || $type === 'resource') {
            $matches = $matches->concat(ResourceLink::where('organization_id', $organizationId)->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('title', 'like', "%{$query}%")->orWhere('url', 'like', "%{$query}%")))->orderBy('title')->limit(30)->get(['id', 'title', 'url'])->map(fn ($item) => $item->setAttribute('kind', 'resource')));
        }
        if ($type === 'all' || $type === 'material') {
            $matches = $matches->concat(MaterialItem::where('organization_id', $organizationId)->when($query !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('name', 'like', "%{$query}%")->orWhere('material_number', 'like', "%{$query}%")->orWhere('storage_location', 'like', "%{$query}%")))->orderBy('name')->limit(30)->get(['id', 'name', 'material_number', 'storage_location', 'description'])->map(fn ($item) => $item->setAttribute('kind', 'material')));
        }

        return response()->json($matches->values());
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
