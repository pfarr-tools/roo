<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonTemplateRequest;
use App\Http\Requests\UploadUnitTemplateResourceRequest;
use App\Models\LessonTemplate;
use App\Models\ResourceReference;
use App\Models\UnitTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LessonTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $organizationId = auth()->user()->organization_id;
        $query = trim((string) $request->query('q', ''));
        $templates = LessonTemplate::query()
            ->with(['unitTemplate:id,title', 'resources:id,lesson_template_id,original_name,description,mime_type,size,page_count'])
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->when($query !== '', fn ($builder) => $builder->where(fn ($builder) => $builder->where('title', 'like', "%{$query}%")->orWhere('objective', 'like', "%{$query}%")->orWhere('notes', 'like', "%{$query}%")))
            ->orderBy('title')
            ->get(['id', 'unit_template_id', 'title', 'duration_minutes', 'objective', 'notes', 'version']);
        $unitTemplates = UnitTemplate::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('LessonTemplates/Index', compact('templates', 'unitTemplates') + ['filters' => ['q' => $query]]);
    }

    public function store(StoreLessonTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureUnitTemplateBelongsToOrganization((int) $data['unit_template_id']);
        LessonTemplate::create($data + ['organization_id' => $request->user()->organization_id, 'version' => 1, 'is_active' => true]);

        return to_route('lesson-templates.index')->with('success', 'Stunden-Vorlage wurde angelegt.');
    }

    public function update(StoreLessonTemplateRequest $request, LessonTemplate $lessonTemplate): RedirectResponse
    {
        $this->ensureVisible($lessonTemplate);
        $data = $request->validated();
        $this->ensureUnitTemplateBelongsToOrganization((int) $data['unit_template_id']);
        $lessonTemplate->update($data + ['version' => $lessonTemplate->version + 1]);

        return to_route('lesson-templates.index')->with('success', 'Stunden-Vorlage wurde gespeichert.');
    }

    public function copy(LessonTemplate $lessonTemplate): RedirectResponse
    {
        $this->ensureVisible($lessonTemplate);
        $copy = $lessonTemplate->replicate(['version', 'copied_from_id', 'created_at', 'updated_at']);
        $copy->fill(['title' => 'Kopie von '.$lessonTemplate->title, 'copied_from_id' => $lessonTemplate->id, 'version' => 1]);
        $copy->save();

        return to_route('lesson-templates.index')->with('success', 'Stunden-Vorlage wurde kopiert.');
    }

    public function destroy(LessonTemplate $lessonTemplate): RedirectResponse
    {
        $this->ensureVisible($lessonTemplate);
        $lessonTemplate->delete();

        return to_route('lesson-templates.index')->with('success', 'Stunden-Vorlage wurde gelöscht.');
    }

    public function uploadResource(UploadUnitTemplateResourceRequest $request, LessonTemplate $lessonTemplate): RedirectResponse
    {
        $this->ensureVisible($lessonTemplate);
        $file = $request->file('resource');
        $path = $file->store('lesson-templates/'.$lessonTemplate->id, 'local');
        $lessonTemplate->resources()->create(['organization_id' => $request->user()->organization_id, 'original_name' => $file->getClientOriginalName(), 'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);

        return to_route('lesson-templates.index')->with('success', 'Anhang wurde hochgeladen.');
    }

    public function destroyResource(LessonTemplate $lessonTemplate, ResourceReference $resource): RedirectResponse
    {
        $this->ensureVisible($lessonTemplate);
        abort_unless($resource->lesson_template_id === $lessonTemplate->id && $resource->organization_id === auth()->user()->organization_id, 404);
        Storage::disk('local')->delete($resource->storage_path);
        $resource->delete();

        return to_route('lesson-templates.index')->with('success', 'Anhang wurde gelöscht.');
    }

    private function ensureVisible(LessonTemplate $lessonTemplate): void
    {
        abort_unless($lessonTemplate->organization_id === auth()->user()->organization_id && $lessonTemplate->is_active, 403);
    }

    private function ensureUnitTemplateBelongsToOrganization(int $unitTemplateId): void
    {
        abort_unless(UnitTemplate::query()->whereKey($unitTemplateId)->where('organization_id', auth()->user()->organization_id)->where('is_active', true)->exists(), 403);
    }
}
