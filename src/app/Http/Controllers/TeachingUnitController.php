<?php

namespace App\Http\Controllers;

use App\Enums\PublicationStatus;
use App\Http\Requests\StorePhaseTemplateRequest;
use App\Models\EducationPlan;
use App\Models\LessonTemplate;
use App\Models\MaterialItem;
use App\Models\PhaseTemplate;
use App\Models\ResourceLink;
use App\Models\SocialForm;
use App\Models\TeachingUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeachingUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $units = TeachingUnit::query()
            ->where('user_id', $request->user()->id)
            ->with(['group:id,name,school_year_id', 'group.schoolYear:id,name', 'sourceCurriculumTopic:id,title', 'educationPlan:id,title,external_identifier', 'resources:id,teaching_unit_id,original_name,description,mime_type,size,page_count,checksum,security_status,source,version', 'resourceLinks:id,user_id,teaching_unit_id,lesson_id,title,url,description', 'materialItems:id,name,description'])
            ->withCount('lessons')
            ->when($query !== '', fn ($builder) => $builder->where(fn ($queryBuilder) => $queryBuilder
                ->where('title', 'like', "%{$query}%")
                ->orWhereHas('group', fn ($group) => $group->where('name', 'like', "%{$query}%"))))
            ->orderBy('title')
            ->get(['id', 'teaching_group_id', 'education_plan_id', 'source_curriculum_topic_id', 'title', 'keyword', 'position', 'notes', 'copied_from_id']);

        return Inertia::render('TeachingUnits/Index', [
            'units' => $units,
            'educationPlans' => EducationPlan::where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))->orderBy('title')->get(['id', 'title', 'external_identifier']),
            'filters' => ['q' => $query],
            'materialItems' => MaterialItem::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name', 'material_number', 'storage_location', 'description']),
            'phaseTemplates' => PhaseTemplate::where('user_id', $request->user()->id)->where('is_active', true)->with('socialForm:id,name')->orderBy('position')->orderBy('title')->get(['id', 'lesson_template_id', 'title', 'duration_minutes', 'social_form_id', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'material', 'media', 'version']),
            'lessonTemplates' => LessonTemplate::where('user_id', $request->user()->id)->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'socialForms' => SocialForm::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storePhaseTemplate(StorePhaseTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureLessonTemplate($data['lesson_template_id'], $request->user()->id);
        unset($data['material_items']);
        $data['social_form_id'] = $this->resolveSocialForm($data['social_form'] ?? null);
        unset($data['social_form']);
        PhaseTemplate::create($data + ['user_id' => $request->user()->id, 'version' => 1, 'is_active' => true]);

        return to_route('teaching-units.index')->with('success', 'Phasen-Vorlage wurde angelegt.');
    }

    public function updatePhaseTemplate(StorePhaseTemplateRequest $request, PhaseTemplate $phaseTemplate): RedirectResponse
    {
        abort_unless($phaseTemplate->user_id === $request->user()->id && $phaseTemplate->is_active, 404);
        $data = $request->validated();
        $this->ensureLessonTemplate($data['lesson_template_id'], $request->user()->id);
        unset($data['material_items']);
        $data['social_form_id'] = $this->resolveSocialForm($data['social_form'] ?? null);
        unset($data['social_form']);
        $phaseTemplate->update($data + ['version' => $phaseTemplate->version + 1]);

        return to_route('teaching-units.index')->with('success', 'Phasen-Vorlage wurde gespeichert.');
    }

    public function destroyPhaseTemplate(Request $request, PhaseTemplate $phaseTemplate): RedirectResponse
    {
        abort_unless($phaseTemplate->user_id === $request->user()->id && $phaseTemplate->is_active, 404);
        $phaseTemplate->delete();

        return to_route('teaching-units.index')->with('success', 'Phasen-Vorlage wurde gelöscht.');
    }

    private function ensureLessonTemplate(int $lessonTemplateId, int $userId): void
    {
        abort_unless(LessonTemplate::whereKey($lessonTemplateId)->where('user_id', $userId)->where('is_active', true)->exists(), 422);
    }

    private function resolveSocialForm(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return SocialForm::firstOrCreate(['user_id' => auth()->user()->id, 'name' => $name])->id;
    }

    public function update(Request $request, TeachingUnit $teachingUnit): RedirectResponse
    {
        abort_unless($teachingUnit->user_id === $request->user()->id, 404);
        $group = $teachingUnit->group;
        $this->authorize('update', $group);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'keyword' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'], 'introduction_text' => ['nullable', 'string'], 'education_plan_id' => ['nullable', 'integer'], 'resource_links' => ['sometimes', 'array'], 'resource_links.*.id' => ['nullable', 'integer'], 'resource_links.*.title' => ['required', 'string', 'max:255'], 'resource_links.*.url' => ['required', 'url', 'max:2000'], 'resource_links.*.publication_status' => ['sometimes', Rule::in([PublicationStatus::NOT_SHARED->value, PublicationStatus::SHARED_IMMEDIATELY->value])], 'material_items' => ['sometimes', 'array'], 'material_items.*.id' => ['nullable', 'integer'], 'material_items.*.name' => ['required', 'string', 'max:255'], 'material_items.*.material_number' => ['nullable', 'string', 'max:255'], 'material_items.*.storage_location' => ['nullable', 'string', 'max:255'], 'material_items.*.description' => ['nullable', 'string'], 'deleted_resource_link_ids' => ['sometimes', 'array'], 'deleted_resource_link_ids.*' => ['integer'], 'deleted_material_item_ids' => ['sometimes', 'array'], 'deleted_material_item_ids.*' => ['integer']]);
        if (isset($data['education_plan_id'])) {
            abort_unless(EducationPlan::whereKey($data['education_plan_id'])->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))->exists(), 422);
        }
        $teachingUnit->update(collect($data)->only(['title', 'keyword', 'notes', 'introduction_text', 'education_plan_id'])->all());
        foreach ($data['resource_links'] ?? [] as $link) {
            if (! empty($link['id'])) {
                ResourceLink::where('user_id', $request->user()->id)->whereKey($link['id'])->where('teaching_unit_id', $teachingUnit->id)->update(array_filter(['title' => $link['title'], 'url' => $link['url'], 'publication_status' => $link['publication_status'] ?? null], static fn ($value): bool => $value !== null));
            } else {
                ResourceLink::create(['user_id' => $request->user()->id, 'teaching_unit_id' => $teachingUnit->id, 'title' => $link['title'], 'url' => $link['url'], 'publication_status' => $link['publication_status'] ?? PublicationStatus::NOT_SHARED]);
            }
        }
        ResourceLink::where('user_id', $request->user()->id)->where('teaching_unit_id', $teachingUnit->id)->whereIn('id', $data['deleted_resource_link_ids'] ?? [])->delete();
        $materialItemIds = [];
        foreach ($data['material_items'] ?? [] as $item) {
            if (! empty($item['id'])) {
                $material = MaterialItem::where('user_id', $request->user()->id)->whereKey($item['id'])->firstOrFail();
                $material->update(['name' => $item['name'], 'material_number' => $item['material_number'] ?? null, 'storage_location' => $item['storage_location'] ?? null, 'description' => $item['description'] ?? null]);
            } else {
                $material = MaterialItem::firstOrCreate(['user_id' => $request->user()->id, 'name' => $item['name']], ['material_number' => $item['material_number'] ?? null, 'storage_location' => $item['storage_location'] ?? null, 'description' => $item['description'] ?? null]);
            }
            $materialItemIds[] = $material->id;
        }
        if (array_key_exists('material_items', $data)) {
            $teachingUnit->materialItems()->sync($materialItemIds);
        }

        return back()->with('success', 'Unterrichtseinheit wurde gespeichert.');
    }

    public function destroy(Request $request, TeachingUnit $teachingUnit): RedirectResponse
    {
        abort_unless($teachingUnit->user_id === $request->user()->id, 404);
        $this->authorize('update', $teachingUnit->group);
        $teachingUnit->delete();

        return back()->with('success', 'Unterrichtseinheit wurde gelöscht.');
    }
}
