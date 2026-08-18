<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonTemplateRequest;
use App\Models\LessonTemplate;
use App\Models\UnitTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $organizationId = auth()->user()->organization_id;
        $query = trim((string) $request->query('q', ''));
        $templates = LessonTemplate::query()
            ->with('unitTemplate:id,title')
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

    public function destroy(LessonTemplate $lessonTemplate): RedirectResponse
    {
        $this->ensureVisible($lessonTemplate);
        $lessonTemplate->delete();

        return to_route('lesson-templates.index')->with('success', 'Stunden-Vorlage wurde gelöscht.');
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
