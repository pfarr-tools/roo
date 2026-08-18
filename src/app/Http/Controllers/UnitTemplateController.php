<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitTemplateRequest;
use App\Http\Requests\UploadUnitTemplateResourceRequest;
use App\Models\ResourceReference;
use App\Models\Tag;
use App\Models\UnitTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UnitTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $templates = UnitTemplate::query()
            ->where('organization_id', auth()->user()->organization_id)
            ->where('is_active', true)
            ->with(['tags:id,name', 'resources:id,unit_template_id,original_name,mime_type,size'])
            ->when($query !== '', fn ($builder) => $builder->where(fn ($builder) => $builder->where('title', 'like', "%{$query}%")->orWhere('description', 'like', "%{$query}%")->orWhere('notes', 'like', "%{$query}%")))
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'expected_hours', 'notes', 'version', 'copied_from_id']);

        return Inertia::render('UnitTemplates/Index', ['templates' => $templates, 'filters' => ['q' => $query]]);
    }

    public function store(StoreUnitTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? [];
        unset($data['tags']);
        $template = UnitTemplate::create($data + [
            'organization_id' => $request->user()->organization_id,
            'version' => 1,
            'is_active' => true,
        ]);
        $this->syncTags($template, $tags);

        return to_route('unit-templates.index')->with('success', 'Unterrichtseinheit-Vorlage wurde angelegt.');
    }

    public function update(StoreUnitTemplateRequest $request, UnitTemplate $unitTemplate): RedirectResponse
    {
        $this->ensureVisible($unitTemplate);
        $data = $request->validated();
        $tags = $data['tags'] ?? [];
        unset($data['tags']);
        $unitTemplate->update($data + ['version' => $unitTemplate->version + 1]);
        $this->syncTags($unitTemplate, $tags);

        return to_route('unit-templates.index')->with('success', 'Unterrichtseinheit-Vorlage wurde gespeichert.');
    }

    public function copy(UnitTemplate $unitTemplate): RedirectResponse
    {
        $this->ensureVisible($unitTemplate);
        $copy = $unitTemplate->replicate(['version', 'copied_from_id', 'created_at', 'updated_at']);
        $copy->fill(['title' => 'Kopie von '.$unitTemplate->title, 'copied_from_id' => $unitTemplate->id, 'version' => 1]);
        $copy->save();
        $copy->tags()->sync($unitTemplate->tags()->pluck('tags.id'));

        return to_route('unit-templates.index')->with('success', 'Unterrichtseinheit-Vorlage wurde kopiert.');
    }

    public function destroy(UnitTemplate $unitTemplate): RedirectResponse
    {
        $this->ensureVisible($unitTemplate);
        $unitTemplate->delete();

        return to_route('unit-templates.index')->with('success', 'Unterrichtseinheit-Vorlage wurde gelöscht.');
    }

    public function uploadResource(UploadUnitTemplateResourceRequest $request, UnitTemplate $unitTemplate): RedirectResponse
    {
        $this->ensureVisible($unitTemplate);
        $file = $request->file('resource');
        $path = $file->store('unit-templates/'.$unitTemplate->id, 'local');
        $unitTemplate->resources()->create([
            'organization_id' => $request->user()->organization_id,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return to_route('unit-templates.index')->with('success', 'Anhang wurde hochgeladen.');
    }

    public function destroyResource(UnitTemplate $unitTemplate, ResourceReference $resource): RedirectResponse
    {
        $this->ensureVisible($unitTemplate);
        abort_unless($resource->unit_template_id === $unitTemplate->id && $resource->organization_id === auth()->user()->organization_id, 404);
        Storage::disk('local')->delete($resource->storage_path);
        $resource->delete();

        return to_route('unit-templates.index')->with('success', 'Anhang wurde gelöscht.');
    }

    private function ensureVisible(UnitTemplate $unitTemplate): void
    {
        abort_unless($unitTemplate->organization_id === auth()->user()->organization_id && $unitTemplate->is_active, 403);
    }

    private function syncTags(UnitTemplate $template, array $names): void
    {
        $tagIds = collect($names)->map(fn (string $name): string => trim($name))->filter()->unique()->map(fn (string $name): int => Tag::firstOrCreate(['organization_id' => auth()->user()->organization_id, 'name' => $name])->id);
        $template->tags()->sync($tagIds);
    }
}
