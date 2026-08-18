<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhaseTemplateRequest;
use App\Models\LessonTemplate;
use App\Models\PhaseTemplate;
use App\Models\SocialForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhaseTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $organizationId = auth()->user()->organization_id;
        $query = trim((string) $request->query('q', ''));
        $templates = PhaseTemplate::query()
            ->with(['lessonTemplate:id,title', 'socialForm:id,name'])
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->when($query !== '', fn ($builder) => $builder->where(fn ($builder) => $builder->where('title', 'like', "%{$query}%")->orWhere('description', 'like', "%{$query}%")->orWhere('material', 'like', "%{$query}%")))
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'lesson_template_id', 'title', 'duration_minutes', 'social_form_id', 'description', 'material', 'position', 'version']);
        $lessonTemplates = LessonTemplate::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('PhaseTemplates/Index', compact('templates', 'lessonTemplates') + ['filters' => ['q' => $query]]);
    }

    public function store(StorePhaseTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureLessonTemplateBelongsToOrganization((int) $data['lesson_template_id']);
        $data['social_form_id'] = $this->resolveSocialForm($data['social_form'] ?? null);
        unset($data['social_form']);
        $data['position'] ??= $this->nextPosition((int) $data['lesson_template_id']);
        PhaseTemplate::create($data + ['organization_id' => $request->user()->organization_id, 'version' => 1, 'is_active' => true]);

        return to_route('phase-templates.index')->with('success', 'Phasen-Vorlage wurde angelegt.');
    }

    public function update(StorePhaseTemplateRequest $request, PhaseTemplate $phaseTemplate): RedirectResponse
    {
        $this->ensureVisible($phaseTemplate);
        $data = $request->validated();
        $this->ensureLessonTemplateBelongsToOrganization((int) $data['lesson_template_id']);
        $data['social_form_id'] = $this->resolveSocialForm($data['social_form'] ?? null);
        unset($data['social_form']);
        $phaseTemplate->update($data + ['version' => $phaseTemplate->version + 1]);

        return to_route('phase-templates.index')->with('success', 'Phasen-Vorlage wurde gespeichert.');
    }

    public function destroy(PhaseTemplate $phaseTemplate): RedirectResponse
    {
        $this->ensureVisible($phaseTemplate);
        $phaseTemplate->delete();

        return to_route('phase-templates.index')->with('success', 'Phasen-Vorlage wurde gelöscht.');
    }

    private function ensureVisible(PhaseTemplate $phaseTemplate): void
    {
        abort_unless($phaseTemplate->organization_id === auth()->user()->organization_id && $phaseTemplate->is_active, 403);
    }

    private function ensureLessonTemplateBelongsToOrganization(int $lessonTemplateId): void
    {
        abort_unless(LessonTemplate::query()->whereKey($lessonTemplateId)->where('organization_id', auth()->user()->organization_id)->where('is_active', true)->exists(), 403);
    }

    private function nextPosition(int $lessonTemplateId): int
    {
        return ((int) PhaseTemplate::where('lesson_template_id', $lessonTemplateId)->max('position')) + 1;
    }

    private function resolveSocialForm(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return SocialForm::firstOrCreate([
            'organization_id' => auth()->user()->organization_id,
            'name' => $name,
        ])->id;
    }
}
