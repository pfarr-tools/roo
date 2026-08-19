<?php

namespace App\Http\Controllers;

use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\EducationPlan;
use App\Models\LessonTemplate;
use App\Models\MaterialItem;
use App\Models\PhaseTemplate;
use App\Models\ResourceLink;
use App\Models\SocialForm;
use App\Http\Requests\StorePhaseTemplateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeachingUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $units = TeachingUnit::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(['group:id,name,school_year_id', 'group.schoolYear:id,name', 'sourceCurriculumTopic:id,title', 'educationPlan:id,title,external_identifier', 'resources:id,teaching_unit_id,original_name,description,mime_type,size,page_count,checksum,security_status,source,version', 'resourceLinks:id,organization_id,teaching_unit_id,lesson_id,title,url,description'])
            ->withCount('lessons')
            ->when($query !== '', fn ($builder) => $builder->where(fn ($queryBuilder) => $queryBuilder
                ->where('title', 'like', "%{$query}%")
                ->orWhereHas('group', fn ($group) => $group->where('name', 'like', "%{$query}%"))))
            ->orderBy('title')
            ->get(['id', 'teaching_group_id', 'education_plan_id', 'source_curriculum_topic_id', 'title', 'keyword', 'position', 'notes', 'copied_from_id']);

        return Inertia::render('TeachingUnits/Index', [
            'units' => $units,
            'educationPlans' => EducationPlan::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->orderBy('title')->get(['id', 'title', 'external_identifier']),
            'filters' => ['q' => $query],
            'materialItems' => MaterialItem::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(['id', 'name', 'description']),
            'phaseTemplates' => PhaseTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->with('socialForm:id,name')->orderBy('position')->orderBy('title')->get(['id', 'lesson_template_id', 'title', 'duration_minutes', 'social_form_id', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'material', 'media', 'version']),
            'lessonTemplates' => LessonTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'socialForms' => SocialForm::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storePhaseTemplate(StorePhaseTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureLessonTemplate($data['lesson_template_id'], $request->user()->organization_id);
        unset($data['material_items']);
        $data['social_form_id'] = $this->resolveSocialForm($data['social_form'] ?? null);
        unset($data['social_form']);
        PhaseTemplate::create($data + ['organization_id' => $request->user()->organization_id, 'version' => 1, 'is_active' => true]);

        return to_route('teaching-units.index')->with('success', 'Phasen-Vorlage wurde angelegt.');
    }

    public function updatePhaseTemplate(StorePhaseTemplateRequest $request, PhaseTemplate $phaseTemplate): RedirectResponse
    {
        abort_unless($phaseTemplate->organization_id === $request->user()->organization_id && $phaseTemplate->is_active, 404);
        $data = $request->validated();
        $this->ensureLessonTemplate($data['lesson_template_id'], $request->user()->organization_id);
        unset($data['material_items']);
        $data['social_form_id'] = $this->resolveSocialForm($data['social_form'] ?? null);
        unset($data['social_form']);
        $phaseTemplate->update($data + ['version' => $phaseTemplate->version + 1]);

        return to_route('teaching-units.index')->with('success', 'Phasen-Vorlage wurde gespeichert.');
    }

    public function destroyPhaseTemplate(Request $request, PhaseTemplate $phaseTemplate): RedirectResponse
    {
        abort_unless($phaseTemplate->organization_id === $request->user()->organization_id && $phaseTemplate->is_active, 404);
        $phaseTemplate->delete();

        return to_route('teaching-units.index')->with('success', 'Phasen-Vorlage wurde gelöscht.');
    }

    private function ensureLessonTemplate(int $lessonTemplateId, int $organizationId): void
    {
        abort_unless(LessonTemplate::whereKey($lessonTemplateId)->where('organization_id', $organizationId)->where('is_active', true)->exists(), 422);
    }

    private function resolveSocialForm(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return SocialForm::firstOrCreate(['organization_id' => auth()->user()->organization_id, 'name' => $name])->id;
    }

    public function update(Request $request, TeachingUnit $teachingUnit): RedirectResponse
    {
        abort_unless($teachingUnit->organization_id === $request->user()->organization_id, 404);
        $group = $teachingUnit->group;
        $this->authorize('update', $group);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'keyword' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'], 'education_plan_id' => ['nullable', 'integer'], 'resource_links' => ['sometimes', 'array'], 'resource_links.*.id' => ['nullable', 'integer'], 'resource_links.*.title' => ['required', 'string', 'max:255'], 'resource_links.*.url' => ['required', 'url', 'max:2000'], 'material_items' => ['sometimes', 'array'], 'material_items.*.id' => ['nullable', 'integer'], 'material_items.*.name' => ['required', 'string', 'max:255'], 'material_items.*.description' => ['nullable', 'string'], 'deleted_resource_link_ids' => ['sometimes', 'array'], 'deleted_resource_link_ids.*' => ['integer'], 'deleted_material_item_ids' => ['sometimes', 'array'], 'deleted_material_item_ids.*' => ['integer']]);
        if (isset($data['education_plan_id'])) {
            abort_unless(EducationPlan::whereKey($data['education_plan_id'])->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->exists(), 422);
        }
        $teachingUnit->update(collect($data)->only(['title', 'keyword', 'notes', 'education_plan_id'])->all());
        foreach ($data['resource_links'] ?? [] as $link) {
            if (! empty($link['id'])) {
                ResourceLink::where('organization_id', $request->user()->organization_id)->whereKey($link['id'])->where('teaching_unit_id', $teachingUnit->id)->update(['title' => $link['title'], 'url' => $link['url']]);
            } else {
                ResourceLink::create(['organization_id' => $request->user()->organization_id, 'teaching_unit_id' => $teachingUnit->id, 'title' => $link['title'], 'url' => $link['url']]);
            }
        }
        ResourceLink::where('organization_id', $request->user()->organization_id)->where('teaching_unit_id', $teachingUnit->id)->whereIn('id', $data['deleted_resource_link_ids'] ?? [])->delete();
        foreach ($data['material_items'] ?? [] as $item) {
            if (! empty($item['id'])) MaterialItem::where('organization_id', $request->user()->organization_id)->whereKey($item['id'])->update(['name' => $item['name'], 'description' => $item['description'] ?? null]);
            else MaterialItem::firstOrCreate(['organization_id' => $request->user()->organization_id, 'name' => $item['name']], ['description' => $item['description'] ?? null]);
        }
        MaterialItem::where('organization_id', $request->user()->organization_id)->whereIn('id', $data['deleted_material_item_ids'] ?? [])->delete();

        return back()->with('success', 'Unterrichtseinheit wurde gespeichert.');
    }

    public function destroy(Request $request, TeachingUnit $teachingUnit): RedirectResponse
    {
        abort_unless($teachingUnit->organization_id === $request->user()->organization_id, 404);
        $this->authorize('update', $teachingUnit->group);
        $teachingUnit->delete();

        return back()->with('success', 'Unterrichtseinheit wurde gelöscht.');
    }
}
