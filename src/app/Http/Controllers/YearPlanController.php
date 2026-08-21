<?php

namespace App\Http\Controllers;

use App\Http\Requests\InterruptPlannedUnitRequest;
use App\Http\Requests\SplitPlannedUnitRequest;
use App\Http\Requests\StorePlannedUnitRequest;
use App\Http\Requests\UpdateLessonOccurrenceRequest;
use App\Http\Requests\StoreLessonPhaseRequest;
use App\Http\Requests\UpdateScheduledLessonStatusRequest;
use App\Models\CurriculumTopic;
use App\Models\CurriculumTopicCompetency;
use App\Models\CurriculumEducationPlanBinding;
use App\Models\EducationPlanCompetency;
use App\Models\GroupYearPlan;
use App\Models\Lesson;
use App\Models\LessonTemplate;
use App\Models\LessonOccurrence;
use App\Models\LessonPhase;
use App\Models\MaterialItem;
use App\Models\PlannedUnit;
use App\Models\ScheduledLesson;
use App\Models\PhaseTemplate;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
use App\Models\ScheduleSlot;
use App\Models\SocialForm;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\SongVersion;
use App\Models\TeachingUnitCompetency;
use App\Models\UnitTemplate;
use App\Models\UserPreference;
use App\Services\YearPlanningWorkspace;
use App\Services\CompetencyResolver;
use App\Services\TeachingGroupCompetencyOverview;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class YearPlanController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $organizationId = auth()->user()->organization_id;
        $groups = TeachingGroup::where('organization_id', $organizationId)->with(['school:id,name', 'schoolYear:id,name'])->with('yearPlan')->orderBy('name')->get();

        if ($groups->isNotEmpty()) {
            $group = $groups->firstWhere('id', auth()->user()->last_year_plan_teaching_group_id) ?? $groups->first();

            return to_route('year-plans.show', $group);
        }

        return Inertia::render('YearPlans/Index', [
            'groups' => $groups,
        ]);
    }

    public function competencyPicker(Request $request, TeachingGroup $teachingGroup, CompetencyResolver $competencyResolver, TeachingGroupCompetencyOverview $overview): JsonResponse
    {
        $this->authorize('view', $teachingGroup);
        $groupCompetencies = $overview->forGroup($teachingGroup, $competencyResolver);
        $coveredHoursByEducationId = $groupCompetencies->filter(fn (array $competency) => filled($competency['education_plan_competency_id']))
            ->mapWithKeys(fn (array $competency) => [$competency['education_plan_competency_id'] => $competency['covered_hours']]);
        $coveredHoursByIdentifier = $groupCompetencies->filter(fn (array $competency) => filled($competency['external_identifier']))
            ->mapWithKeys(fn (array $competency) => [$competency['external_identifier'] => $competency['covered_hours']]);
        $competencies = EducationPlanCompetency::query()
            ->whereIn('education_plan_competence_area_id', fn ($query) => $query->select('id')->from('education_plan_competence_areas')->whereIn('education_plan_version_id', fn ($versions) => $versions->select('id')->from('education_plan_versions')->whereIn('education_plan_id', $this->educationPlanIdsForGroup($teachingGroup))))
            ->with(['area:id,kind,external_identifier,title', 'variants:id,education_plan_competency_id,text,position'])
            ->orderBy('external_identifier')
            ->get(['id', 'education_plan_competence_area_id', 'external_identifier', 'number', 'text'])
            ->unique('external_identifier')
            ->values()
            ->each(function ($competency) use ($competencyResolver, $coveredHoursByEducationId, $coveredHoursByIdentifier): void {
                $presentation = $competencyResolver->present($competency);
                $presentation['kind'] = $competency->area?->kind ?? $presentation['kind'];
                $competency->setAttribute('competency_presentation', $presentation);
                $competency->setAttribute('competency_area', ['identifier' => $competency->area?->external_identifier, 'title' => $competency->area?->title, 'kind' => $competency->area?->kind]);
                $competency->setAttribute('covered_hours', $coveredHoursByEducationId->get($competency->id, $coveredHoursByIdentifier->get($competency->external_identifier, 0)));
            });
        return response()->json([
            'competencies' => $competencies,
            'covered_hours' => $competencies->mapWithKeys(fn ($competency) => [$competency->id => $competency->covered_hours])->all(),
        ]);
    }

    public function show(Request $request, TeachingGroup $teachingGroup, YearPlanningWorkspace $workspace, CompetencyResolver $competencyResolver, TeachingGroupCompetencyOverview $competencyOverview): Response
    {
        $this->authorize('view', $teachingGroup);
        if ($request->user()->last_year_plan_teaching_group_id !== $teachingGroup->id) {
            $request->user()->update(['last_year_plan_teaching_group_id' => $teachingGroup->id]);
        }
        $plan = $this->planFor($teachingGroup)->load(['units.template:id,title', 'units.curriculumTopic:id,title', 'units.lessons.occurrences', 'revisions.user:id,name']);
        $gradeLevels = $teachingGroup->gradeLevels()->pluck('grade_level')->map(fn ($grade) => (int) preg_replace('/\D+/', '', (string) $grade))->filter()->values();

        $workspace->syncSlots($teachingGroup);
        $curriculumColumnPreference = $request->user()->preferences()->where('key', 'year-plan.'.$teachingGroup->id.'.curriculum-column')->first()?->value ?? [];
        $educationPlanAreasByIdentifier = EducationPlanCompetency::query()
            ->whereIn('education_plan_competence_area_id', fn ($query) => $query->select('id')->from('education_plan_competence_areas')->whereIn('education_plan_version_id', fn ($versions) => $versions->select('id')->from('education_plan_versions')->whereIn('education_plan_id', $this->educationPlanIdsForGroup($teachingGroup))))
            ->with('area:id,kind,external_identifier,title')
            ->get(['id', 'education_plan_competence_area_id', 'external_identifier'])
            ->mapWithKeys(fn ($competency) => [$competency->external_identifier => $competency->area])
            ->filter();

        $workspaceUnits = $teachingGroup->teachingUnits()->with(['template:id,title', 'educationPlan:id,title,external_identifier', 'sourceCurriculumTopic:id,title', 'resources:id,teaching_unit_id,lesson_id,original_name,description,mime_type,size,page_count,checksum,security_status,source,version', 'resourceLinks:id,organization_id,teaching_unit_id,lesson_id,title,url,description', 'materialItems:id,name,description', 'songs.song:id,title', 'competencies.educationPlanCompetency:id,education_plan_competence_area_id,external_identifier,number,text', 'competencies.educationPlanCompetency.variants:id,education_plan_competency_id,text,position', 'competencies.educationPlanCompetency.area:id,kind,external_identifier,title', 'competencies.curriculumCompetency:id,education_plan_competency_id,external_identifier,display,text,raw_text,competency_kind,denomination', 'competencies.curriculumCompetency.educationPlanCompetency.area:id,kind,external_identifier,title', 'lessons.template:id,title', 'lessons.resources:id,teaching_unit_id,lesson_id,original_name,description,mime_type,size,page_count,checksum,source,version', 'lessons.resourceLinks:id,organization_id,teaching_unit_id,lesson_id,title,url,description', 'lessons.materialItems:id,name,description', 'lessons.songs.song:id,title', 'lessons.songbooks', 'lessons.competencies', 'lessons.phases.socialForm', 'lessons.phases.songs.song:id,title', 'lessons.scheduledLessons.slot'])->orderBy('position')->get();
        $workspaceUnits->each(function ($unit) use ($teachingGroup, $competencyResolver, $educationPlanAreasByIdentifier): void {
            $unit->setRelation('competencies', $unit->competencies->filter(fn ($competency) => ! $competency->curriculumCompetency || ! $teachingGroup->denomination || blank($competency->curriculumCompetency->denomination) || $competency->curriculumCompetency->denomination === $teachingGroup->denomination)->values());
            $unit->competencies->each(function ($competency) use ($competencyResolver, $educationPlanAreasByIdentifier): void {
                $competency->setAttribute('competency_presentation', $competencyResolver->present($competency));
                $area = $competency->educationPlanCompetency?->area ?? $competency->curriculumCompetency?->educationPlanCompetency?->area ?? $educationPlanAreasByIdentifier->get($competency->curriculumCompetency?->external_identifier);
                $competency->setAttribute('competency_area', $area ? ['identifier' => $area->external_identifier, 'title' => $area->title] : null);
            });
        });
        $curricula = $teachingGroup->curricula()->with(['versions.topics' => fn ($query) => $query->whereIn('year', $gradeLevels), 'versions.topics.competencies' => fn ($query) => $query->forGroup($teachingGroup), 'versions.topics.competencies.educationPlanCompetency:id,education_plan_competence_area_id,external_identifier,number,text', 'versions.topics.competencies.educationPlanCompetency.area:id,kind,external_identifier,title', 'versions.topics.competencies.educationPlanCompetency.variants:id,education_plan_competency_id,text,position'])->get();
        $curricula->each(fn ($curriculum) => $curriculum->versions->each(fn ($version) => $version->topics->each(fn ($topic) => $topic->competencies->each(fn ($competency) => $competency->setAttribute('competency_presentation', $competencyResolver->present($competency))))));
        $coverage = $workspace->coverage($teachingGroup);
        $requiredCompetencies = $competencyOverview->forGroup($teachingGroup, $competencyResolver);
        $coverage['required_covered'] = $requiredCompetencies->where('covered_hours', '>', 0)->count();
        $coverage['required_total'] = $requiredCompetencies->count();

        return Inertia::render('YearPlans/Show', [
            'group' => $teachingGroup->load(['school:id,name', 'schoolYear:id,name,starts_on,ends_on', 'schoolYear.days', 'timetableSlots', 'gradeLevels:id,teaching_group_id,grade_level']),
            'plan' => $plan,
            'canUndoReflow' => $plan->revisions->contains(fn ($revision) => $revision->action === 'slot_reflow' && ! empty($revision->payload)),
            'unitTemplates' => UnitTemplate::where('organization_id', auth()->user()->organization_id)->where('is_active', true)->orderBy('title')->get(['id', 'title', 'expected_hours']),
            'checks' => $this->checks($teachingGroup, $plan),
            'calendar' => $this->calendar($teachingGroup),
            'holidayPeriods' => $teachingGroup->schoolYear->holidayPeriods()->orderBy('starts_on')->get(['id', 'starts_on', 'ends_on', 'name']),
            'workspace' => [
                'units' => $workspaceUnits,
                'curricula' => $curricula,
                'slots' => $teachingGroup->scheduleSlots()->with('scheduledLesson.lesson.unit')->orderBy('date')->orderBy('period_number')->get(),
                'coverage' => $coverage,
            ],
            'groupOptions' => TeachingGroup::where('organization_id', auth()->user()->organization_id)->with('schoolYear:id,name')->orderBy('name')->get(['id', 'name', 'school_year_id']),
            'availableUnits' => TeachingUnit::where('organization_id', auth()->user()->organization_id)
                ->where('teaching_group_id', '!=', $teachingGroup->id)
                ->with(['group:id,name,school_year_id', 'group.schoolYear:id,name'])
                ->orderBy('title')->get(['id', 'teaching_group_id', 'education_plan_id', 'title', 'notes']),
            'curriculumColumnOpen' => $curriculumColumnPreference['open'] ?? true,
            'materialItems' => MaterialItem::where('organization_id', auth()->user()->organization_id)->orderBy('name')->get(['id', 'name', 'material_number', 'storage_location', 'description']),
            'songs' => SongVersion::whereHas('song', fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', auth()->user()->organization_id))->with('song:id,title')->orderBy('name')->get(),
            'phaseTemplates' => PhaseTemplate::where('organization_id', auth()->user()->organization_id)->where('is_active', true)->with('socialForm:id,name')->orderBy('position')->orderBy('title')->get(['id', 'title', 'duration_minutes', 'social_form_id', 'material']),
            'socialForms' => SocialForm::where('organization_id', auth()->user()->organization_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateCurriculumColumnPreference(Request $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('view', $teachingGroup);
        $data = $request->validate(['open' => ['required', 'boolean']]);
        UserPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'key' => 'year-plan.'.$teachingGroup->id.'.curriculum-column'],
            ['value' => ['open' => (bool) $data['open']]],
        );

        return back();
    }

    public function importTeachingUnit(Request $request, TeachingGroup $teachingGroup, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['source_id' => ['required', 'integer']]);
        $source = TeachingUnit::where('organization_id', $teachingGroup->organization_id)->findOrFail($data['source_id']);
        $unit = $workspace->copyTeachingUnit($teachingGroup, $source);
        $this->revise($this->planFor($teachingGroup), $request->user()->id, 'teaching_unit_copied', 'Unterrichtseinheit „'.$unit->title.'“ aus einer anderen Planung übernommen.');

        return back()->with('success', 'Unterrichtseinheit wurde unabhängig übernommen.');
    }

    public function takeCurriculumUnit(Request $request, TeachingGroup $teachingGroup, CurriculumTopic $topic, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $curriculumIds = $teachingGroup->curricula()->pluck('curricula.id');
        abort_unless(CurriculumTopic::whereKey($topic->id)->whereHas('version', fn ($query) => $query->whereIn('curriculum_id', $curriculumIds))->exists(), 422, 'Das Curriculumthema gehört nicht zu dieser Unterrichtsgruppe.');
        $unit = $workspace->importCurriculumUnit($teachingGroup, $topic->load(['competencies' => fn ($query) => $query->forGroup($teachingGroup)]));
        $plan = $this->planFor($teachingGroup);
        $this->revise($plan, $request->user()->id, 'teaching_unit_imported', 'Curriculum-UE „'.$unit->title.'“ als eigene UE übernommen.');

        return back()->with('success', 'Die Curriculum-UE wurde als eigene Unterrichtseinheit übernommen.');
    }

    public function takeAllCurriculumUnits(Request $request, TeachingGroup $teachingGroup, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $curriculumIds = $teachingGroup->curricula()->pluck('curricula.id');
        $gradeLevels = $teachingGroup->gradeLevels()->pluck('grade_level')->map(fn ($grade) => (int) preg_replace('/\D+/', '', (string) $grade))->filter()->values();
        $topics = CurriculumTopic::query()
            ->whereHas('version', fn ($query) => $query->whereIn('curriculum_id', $curriculumIds))
            ->when($gradeLevels->isNotEmpty(), fn ($query) => $query->whereIn('year', $gradeLevels))
            ->with(['competencies' => fn ($query) => $query->forGroup($teachingGroup)])
            ->get();
        $existingTopicIds = $teachingGroup->teachingUnits()->whereNotNull('source_curriculum_topic_id')->pluck('source_curriculum_topic_id');
        $imported = 0;
        foreach ($topics->reject(fn ($topic) => $existingTopicIds->contains($topic->id)) as $topic) {
            $workspace->importCurriculumUnit($teachingGroup, $topic);
            $imported++;
        }

        return back()->with('success', $imported ? $imported.' Curriculum-UE(s) wurden übernommen.' : 'Alle Curriculum-UEs sind bereits im Plan.');
    }

    public function destroyTeachingUnit(TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $teachingUnit->delete();

        return back()->with('success', 'Unterrichtseinheit wurde aus diesem Plan entfernt.');
    }

    public function storeTeachingUnit(Request $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'keyword' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string']]);
        $teachingGroup->teachingUnits()->create($data + ['education_plan_id' => $this->educationPlanIdsForGroup($teachingGroup)->first(), 'organization_id' => $teachingGroup->organization_id, 'position' => ($teachingGroup->teachingUnits()->max('position') ?? 0) + 1]);

        return back()->with('success', 'Eigene Unterrichtseinheit wurde angelegt.');
    }

    public function updateTeachingUnit(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'keyword' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'], 'competency_ids' => ['sometimes', 'array'], 'competency_ids.*' => ['integer'], 'education_plan_competency_ids' => ['sometimes', 'array'], 'education_plan_competency_ids.*' => ['integer'], 'resource_links' => ['sometimes', 'array'], 'resource_links.*.id' => ['nullable', 'integer'], 'resource_links.*.local_key' => ['nullable', 'string'], 'resource_links.*.title' => ['required', 'string', 'max:255'], 'resource_links.*.url' => ['required', 'url', 'max:2000']]);
        $data['material_items'] = $request->validate(['material_items' => ['sometimes', 'array'], 'material_items.*.id' => ['nullable', 'integer'], 'material_items.*.local_key' => ['nullable', 'string'], 'material_items.*.name' => ['required', 'string', 'max:255'], 'material_items.*.material_number' => ['nullable', 'string', 'max:255'], 'material_items.*.storage_location' => ['nullable', 'string', 'max:255'], 'material_items.*.description' => ['nullable', 'string']])['material_items'] ?? [];
        $data['deleted_resource_link_ids'] = $request->validate(['deleted_resource_link_ids' => ['sometimes', 'array'], 'deleted_resource_link_ids.*' => ['integer']])['deleted_resource_link_ids'] ?? [];
        $data['deleted_material_item_ids'] = $request->validate(['deleted_material_item_ids' => ['sometimes', 'array'], 'deleted_material_item_ids.*' => ['integer']])['deleted_material_item_ids'] ?? [];
        $teachingUnit->update(collect($data)->only(['title', 'keyword', 'notes'])->all());
        foreach ($data['resource_links'] ?? [] as $link) {
            if (! empty($link['id'])) {
                ResourceLink::where('organization_id', $teachingGroup->organization_id)->whereKey($link['id'])->where('teaching_unit_id', $teachingUnit->id)->update(['title' => $link['title'], 'url' => $link['url']]);
            } else {
                ResourceLink::create(['organization_id' => $teachingGroup->organization_id, 'teaching_unit_id' => $teachingUnit->id, 'title' => $link['title'], 'url' => $link['url']]);
            }
        }
        ResourceLink::where('organization_id', $teachingGroup->organization_id)->where('teaching_unit_id', $teachingUnit->id)->whereIn('id', $data['deleted_resource_link_ids'])->delete();
        $materialItemIds = [];
        foreach ($data['material_items'] ?? [] as $item) {
            if (! empty($item['id'])) {
                $material = MaterialItem::where('organization_id', $teachingGroup->organization_id)->whereKey($item['id'])->firstOrFail();
                $material->update(['name' => $item['name'], 'material_number' => $item['material_number'] ?? null, 'storage_location' => $item['storage_location'] ?? null, 'description' => $item['description'] ?? null]);
            } else {
                $material = MaterialItem::firstOrCreate(['organization_id' => $teachingGroup->organization_id, 'name' => $item['name']], ['material_number' => $item['material_number'] ?? null, 'storage_location' => $item['storage_location'] ?? null, 'description' => $item['description'] ?? null]);
            }
            $materialItemIds[] = $material->id;
        }
        if (array_key_exists('material_items', $data)) $teachingUnit->materialItems()->sync($materialItemIds);
        if (array_key_exists('education_plan_competency_ids', $data)) {
            $educationIds = collect($data['education_plan_competency_ids'])->unique()->values();
            $validEducationIds = EducationPlanCompetency::whereIn('id', $educationIds)
                ->whereIn('education_plan_competence_area_id', fn ($query) => $query->select('id')->from('education_plan_competence_areas')->whereIn('education_plan_version_id', fn ($versions) => $versions->select('id')->from('education_plan_versions')->whereIn('education_plan_id', $this->educationPlanIdsForGroup($teachingGroup))))
                ->pluck('id');
            abort_unless($validEducationIds->count() === $educationIds->count(), 422, 'Eine Kompetenz gehört nicht zum Bildungsplan dieser Unterrichtsgruppe.');
            foreach ($validEducationIds as $educationId) {
                $data['competency_ids'][] = $teachingUnit->competencies()->firstOrCreate(['education_plan_competency_id' => $educationId], ['is_secondary' => false])->id;
            }
            $data['competency_ids'] = collect($data['competency_ids'])->unique()->values()->all();
        }
        if (array_key_exists('competency_ids', $data)) {
            $validIds = $teachingUnit->competencies()->whereIn('id', $data['competency_ids'])->pluck('id');
            abort_unless($validIds->count() === count($data['competency_ids']), 422, 'Eine Kompetenz gehört nicht zu dieser Unterrichtseinheit.');
            $removedCompetencies = $teachingUnit->competencies()->when($validIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $validIds))->get();
            foreach ($removedCompetencies as $competency) {
                $competency->lessons()->detach();
                $competency->delete();
            }
        }

        return back()->with('success', 'Unterrichtseinheit wurde gespeichert.');
    }

    public function updateTeachingUnitCompetencies(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['competency_ids' => ['array'], 'competency_ids.*' => ['integer']]);
        $validIds = $teachingUnit->competencies()->whereIn('id', $data['competency_ids'] ?? [])->pluck('id');
        abort_unless($validIds->count() === count($data['competency_ids'] ?? []), 422, 'Eine Kompetenz gehört nicht zu dieser Unterrichtseinheit.');
        $removedCompetencies = $teachingUnit->competencies()->when($validIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $validIds))->get();
        foreach ($removedCompetencies as $competency) {
            $competency->lessons()->detach();
        }
        if ($validIds->isEmpty()) {
            $teachingUnit->competencies()->delete();
        }

        return back()->with('success', 'Kompetenzen der Unterrichtseinheit wurden gespeichert.');
    }

    public function addTeachingUnitCompetency(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['education_plan_competency_id' => ['required', 'integer'], 'is_secondary' => ['sometimes', 'boolean']]);
        $competency = EducationPlanCompetency::whereKey($data['education_plan_competency_id'])
            ->whereIn('education_plan_competence_area_id', fn ($query) => $query->select('id')->from('education_plan_competence_areas')->whereIn('education_plan_version_id', fn ($versions) => $versions->select('id')->from('education_plan_versions')->whereIn('education_plan_id', $this->educationPlanIdsForGroup($teachingGroup))))
            ->firstOrFail();
        $teachingUnit->competencies()->firstOrCreate(['education_plan_competency_id' => $competency->id], ['is_secondary' => (bool) ($data['is_secondary'] ?? false)]);

        return back()->with('success', 'Kompetenz wurde hinzugefügt.');
    }

    public function addLessonCompetency(Request $request, TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['education_plan_competency_id' => ['nullable', 'integer', 'required_without:curriculum_topic_competency_id'], 'curriculum_topic_competency_id' => ['nullable', 'integer', 'required_without:education_plan_competency_id']]);
        $curriculumCompetency = isset($data['curriculum_topic_competency_id'])
            ? CurriculumTopicCompetency::whereKey($data['curriculum_topic_competency_id'])
                ->whereHas('topic.version', fn ($query) => $query->whereIn('curriculum_id', $teachingGroup->curricula()->pluck('curricula.id')))
                ->forGroup($teachingGroup)
                ->firstOrFail()
            : null;
        $competency = $curriculumCompetency?->educationPlanCompetency ?? EducationPlanCompetency::whereKey($data['education_plan_competency_id'])
            ->whereIn('education_plan_competence_area_id', fn ($query) => $query->select('id')->from('education_plan_competence_areas')->whereIn('education_plan_version_id', fn ($versions) => $versions->select('id')->from('education_plan_versions')->whereIn('education_plan_id', $this->educationPlanIdsForGroup($teachingGroup))))
            ->firstOrFail();
        $unitCompetency = $lesson->unit->competencies()
            ->where(fn ($query) => $query->where('curriculum_topic_competency_id', $curriculumCompetency?->id)->orWhere('education_plan_competency_id', $competency->id))
            ->first();
        if (! $unitCompetency) {
            $unitCompetency = $lesson->unit->competencies()->create([
                'curriculum_topic_competency_id' => $curriculumCompetency?->id,
                'education_plan_competency_id' => $competency->id,
                'is_secondary' => true,
            ]);
        }
        $lesson->competencies()->syncWithoutDetaching([$unitCompetency->id]);

        return back()->with('success', 'Kompetenz wurde als sekundäre UE-Kompetenz hinzugefügt.');
    }

    public function removeTeachingUnitCompetency(TeachingGroup $teachingGroup, TeachingUnit $teachingUnit, TeachingUnitCompetency $teachingUnitCompetency): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id && $teachingUnitCompetency->teaching_unit_id === $teachingUnit->id, 404);
        $teachingUnitCompetency->lessons()->detach();
        $teachingUnitCompetency->delete();

        return back()->with('success', 'Kompetenz wurde entfernt.');
    }

    private function educationPlanIdsForGroup(TeachingGroup $teachingGroup)
    {
        $versionIds = $teachingGroup->curricula()->with('versions:id,curriculum_id')->get()->flatMap->versions->pluck('id');

        return CurriculumEducationPlanBinding::whereIn('curriculum_version_id', $versionIds)->whereNotNull('education_plan_id')->pluck('education_plan_id')
            ->merge($teachingGroup->teachingUnits()->whereNotNull('education_plan_id')->pluck('education_plan_id'))
            ->unique()->values();
    }

    public function saveUnitAsTemplate(TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $teachingUnit->load(['template', 'lessons']);
        $attributes = [
            'title' => $teachingUnit->title,
            'description' => null,
            'expected_hours' => max(1, (int) $teachingUnit->lessons->sum('duration')),
            'notes' => $teachingUnit->notes,
        ];
        $template = $teachingUnit->template;
        if ($template) {
            $template->update($attributes + ['version' => $template->version + 1]);
        } else {
            $template = UnitTemplate::create($attributes + ['organization_id' => $teachingGroup->organization_id, 'version' => 1, 'is_active' => true]);
            $teachingUnit->update(['unit_template_id' => $template->id]);
        }

        return back()->with('success', 'UE-Vorlage wurde gespeichert.');
    }

    public function storeLesson(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'duration' => ['required', 'integer', 'min:1', 'max:12']]);
        $teachingUnit->lessons()->create($data + ['position' => ($teachingUnit->lessons()->max('position') ?? 0) + 1]);

        return back()->with('success', 'Stunde wurde angelegt.');
    }

    public function updateLesson(Request $request, TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'duration' => ['required', 'integer', 'min:1', 'max:12'], 'learning_goals' => ['nullable', 'string'], 'materials' => ['nullable', 'string'], 'homework' => ['nullable', 'string'], 'assessment_note' => ['nullable', 'string'], 'notes' => ['nullable', 'string'], 'competency_ids' => ['sometimes', 'array'], 'competency_ids.*' => ['integer'], 'education_plan_competency_ids' => ['sometimes', 'array'], 'education_plan_competency_ids.*' => ['integer'], 'resource_links' => ['sometimes', 'array'], 'resource_links.*.id' => ['nullable', 'integer'], 'resource_links.*.local_key' => ['nullable', 'string', 'max:100'], 'resource_links.*.title' => ['required', 'string', 'max:255'], 'resource_links.*.url' => ['required', 'url', 'max:2000'], 'resource_links.*.description' => ['nullable', 'string'], 'phases' => ['sometimes', 'array'], 'phases.*.id' => ['nullable', 'integer'], 'phases.*.phase_template_id' => ['nullable', 'integer', 'exists:phase_templates,id'], 'phases.*.title' => ['required', 'string', 'max:255'], 'phases.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:999'], 'phases.*.social_form' => ['nullable', 'string', 'max:100'], 'phases.*.teacher_interaction' => ['nullable', 'string'], 'phases.*.learner_activity' => ['nullable', 'string'], 'phases.*.differentiation' => ['nullable', 'string'], 'phases.*.didactic_comment' => ['nullable', 'string'], 'phases.*.materials' => ['nullable', 'string'], 'phases.*.media' => ['nullable', 'string'], 'phases.*.resource_ids' => ['sometimes', 'array'], 'phases.*.resource_link_ids' => ['sometimes', 'array'], 'phases.*.material_item_ids' => ['sometimes', 'array'], 'phases.*.song_ids' => ['sometimes', 'array']]);
        $data['material_items'] = $request->validate(['material_items' => ['sometimes', 'array'], 'material_items.*.id' => ['nullable', 'integer'], 'material_items.*.local_key' => ['nullable', 'string', 'max:100'], 'material_items.*.name' => ['required', 'string', 'max:255'], 'material_items.*.material_number' => ['nullable', 'string', 'max:255'], 'material_items.*.storage_location' => ['nullable', 'string', 'max:255'], 'material_items.*.description' => ['nullable', 'string']])['material_items'] ?? [];
        $data['deleted_resource_link_ids'] = $request->validate(['deleted_resource_link_ids' => ['sometimes', 'array'], 'deleted_resource_link_ids.*' => ['integer']])['deleted_resource_link_ids'] ?? [];
        $data['deleted_material_item_ids'] = $request->validate(['deleted_material_item_ids' => ['sometimes', 'array'], 'deleted_material_item_ids.*' => ['integer']])['deleted_material_item_ids'] ?? [];
        $phaseTemplateIds = collect($data['phases'] ?? [])->pluck('phase_template_id')->filter()->unique();
        abort_unless(PhaseTemplate::where('organization_id', $teachingGroup->organization_id)->whereIn('id', $phaseTemplateIds)->count() === $phaseTemplateIds->count(), 422, 'Eine Phasen-Vorlage gehört nicht zu dieser Organisation.');
        DB::transaction(function () use ($data, $lesson, $teachingGroup): void {
            $lesson->update(collect($data)->except(['competency_ids', 'education_plan_competency_ids', 'phases', 'resource_links', 'material_items', 'deleted_resource_link_ids', 'deleted_material_item_ids'])->all());
            $resourceLinkIds = [];
            $materialItemIdsByKey = [];
            foreach ($data['material_items'] ?? [] as $item) {
                if (! empty($item['id'])) {
                    $existing = MaterialItem::where('organization_id', $teachingGroup->organization_id)->whereKey($item['id'])->firstOrFail();
                    $existing->update(['name' => $item['name'], 'description' => $item['description'] ?? null]);
                    $materialItemIdsByKey[$item['local_key'] ?? 'id-'.$existing->id] = $existing->id;
                } else {
                    $created = MaterialItem::firstOrCreate(['organization_id' => $teachingGroup->organization_id, 'name' => $item['name']], ['description' => $item['description'] ?? null]);
                    $materialItemIdsByKey[$item['local_key'] ?? 'id-'.$created->id] = $created->id;
                }
            }
            foreach ($data['resource_links'] ?? [] as $link) {
                if (! empty($link['id'])) {
                    $existing = ResourceLink::where('organization_id', $teachingGroup->organization_id)->whereKey($link['id'])->firstOrFail();
                    $existing->update(['teaching_unit_id' => $lesson->teaching_unit_id, 'lesson_id' => $lesson->id, 'title' => $link['title'], 'url' => $link['url'], 'description' => $link['description'] ?? $existing->description]);
                    $resourceLinkIds[$link['local_key'] ?? 'id-'.$existing->id] = $existing->id;
                } else {
                    $created = ResourceLink::create(['organization_id' => $teachingGroup->organization_id, 'teaching_unit_id' => $lesson->teaching_unit_id, 'lesson_id' => $lesson->id, 'title' => $link['title'], 'url' => $link['url'], 'description' => $link['description'] ?? null]);
                    $resourceLinkIds[$link['local_key'] ?? 'id-'.$created->id] = $created->id;
                }
            }
            ResourceLink::where('organization_id', $teachingGroup->organization_id)->whereIn('id', $data['deleted_resource_link_ids'])->where(function ($query) use ($lesson): void {
                $query->where('teaching_unit_id', $lesson->teaching_unit_id)->orWhere('lesson_id', $lesson->id);
            })->delete();
            MaterialItem::where('organization_id', $teachingGroup->organization_id)->whereIn('id', $data['deleted_material_item_ids'])->delete();
            if (array_key_exists('material_items', $data)) {
                $lesson->materialItems()->sync(array_values($materialItemIdsByKey));
            }
            if (array_key_exists('phases', $data)) {
                $phases = collect($data['phases']);
                $existingIds = $lesson->phases()->pluck('id');
                abort_unless($phases->pluck('id')->filter()->diff($existingIds)->isEmpty(), 422, 'Eine Phase gehört nicht zu dieser Stunde.');
                $lesson->phases()->whereNotIn('id', $phases->pluck('id')->filter())->delete();
                foreach ($phases as $position => $phase) {
                    $resourceIds = array_values(array_filter($phase['resource_ids'] ?? [], 'is_numeric'));
                    $materialItemSelection = collect($phase['material_item_ids'] ?? [])->map(fn ($id) => is_numeric($id) ? (int) $id : ($materialItemIdsByKey[$id] ?? null))->filter()->values()->all();
                    $resourceLinkPhaseIds = collect($phase['resource_link_ids'] ?? [])->map(fn ($id) => is_numeric($id) ? (int) $id : ($resourceLinkIds[$id] ?? null))->filter()->values()->all();
                    $validResourceIds = ResourceReference::where('organization_id', $teachingGroup->organization_id)
                        ->whereIn('id', $resourceIds)
                        ->where(function ($query) use ($lesson): void {
                            $query->where('teaching_unit_id', $lesson->teaching_unit_id)
                                ->orWhere('lesson_id', $lesson->id);
                        })
                        ->pluck('id')->all();
                    if (! empty($phase['id'])) {
                        $phaseResourceIds = ResourceReference::where('organization_id', $teachingGroup->organization_id)
                            ->whereIn('id', $resourceIds)
                            ->whereHas('phases', fn ($query) => $query->where('lesson_id', $lesson->id)->whereKey($phase['id']))
                            ->pluck('id');
                        $validResourceIds = collect($validResourceIds)->merge($phaseResourceIds)->unique()->values()->all();
                    }
                    $validMaterialItemIds = MaterialItem::where('organization_id', $teachingGroup->organization_id)->whereIn('id', $materialItemSelection)->pluck('id')->all();
                    $songSelection = collect($phase['song_ids'] ?? [])->filter(fn ($id): bool => is_numeric($id))->map(fn ($id) => (int) $id)->values()->all();
                    $validSongIds = SongVersion::whereIn('id', $songSelection)->whereHas('song', fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $teachingGroup->organization_id))->pluck('id')->all();
                    $validResourceLinkIds = ResourceLink::where('organization_id', $teachingGroup->organization_id)->whereIn('id', $resourceLinkPhaseIds)->where(function ($query) use ($lesson): void {
                        $query->where('teaching_unit_id', $lesson->teaching_unit_id)->orWhere('lesson_id', $lesson->id);
                    })->pluck('id')->all();
                    abort_unless(count($validResourceIds) === count($resourceIds), 422, 'Eine Datei gehört nicht zu dieser Unterrichtseinheit.');
                    abort_unless(count($validResourceLinkIds) === count($resourceLinkPhaseIds), 422, 'Eine Webressource gehört nicht zu dieser Unterrichtseinheit.');
                    abort_unless(count($validMaterialItemIds) === count($materialItemSelection), 422, 'Ein Material gehört nicht zu dieser Unterrichtseinheit.');
                    abort_unless(count($validSongIds) === count($songSelection), 422, 'Ein Lied ist nicht verfügbar.');
                    $attributes = collect($phase)->except(['id', 'local_key', 'resource_ids', 'resource_link_ids', 'material_item_ids', 'materials', 'media'])->merge(['position' => $position + 1, 'materials' => null, 'media' => null])->all();
                    $socialFormName = trim((string) ($attributes['social_form'] ?? ''));
                    unset($attributes['social_form']);
                    $attributes['social_form_id'] = $socialFormName === '' ? null : SocialForm::firstOrCreate(['organization_id' => $lesson->unit->organization_id, 'name' => $socialFormName])->id;
                    $savedPhase = ! empty($phase['id']) ? tap($lesson->phases()->whereKey($phase['id'])->firstOrFail())->update($attributes) : $lesson->phases()->create($attributes);
                    if ($savedPhase instanceof LessonPhase) $savedPhase->resources()->sync($validResourceIds);
                    if ($savedPhase instanceof LessonPhase) $savedPhase->resourceLinks()->sync($validResourceLinkIds);
                    if ($savedPhase instanceof LessonPhase) $savedPhase->materialItems()->sync($validMaterialItemIds);
                    if ($savedPhase instanceof LessonPhase) $savedPhase->songs()->sync($validSongIds);
                    if ($validSongIds !== []) {
                        $songbook = $teachingGroup->songbook()->firstOrCreate([]);
                        foreach ($validSongIds as $songId) {
                            $songbook->entries()->firstOrCreate(['song_version_id' => $songId], ['song_number' => ((int) $songbook->entries()->max('song_number')) + 1, 'added_at' => now()]);
                        }
                    }
                }
                if ($phases->isNotEmpty()) $lesson->scheduledLessons()->where('status', ScheduledLesson::STATUS_ASSIGNED)->update(['status' => ScheduledLesson::STATUS_PLANNED]);
            }
        });
        if (array_key_exists('competency_ids', $data) || array_key_exists('education_plan_competency_ids', $data)) {
            $selectedIds = collect($data['competency_ids'] ?? []);
            if (array_key_exists('education_plan_competency_ids', $data)) {
                $educationIds = collect($data['education_plan_competency_ids'])->unique()->values();
                $validEducationIds = EducationPlanCompetency::whereIn('id', $educationIds)
                    ->whereIn('education_plan_competence_area_id', fn ($query) => $query->select('id')->from('education_plan_competence_areas')->whereIn('education_plan_version_id', fn ($versions) => $versions->select('id')->from('education_plan_versions')->whereIn('education_plan_id', $this->educationPlanIdsForGroup($teachingGroup))))
                    ->pluck('id');
                abort_unless($validEducationIds->count() === $educationIds->count(), 422, 'Eine Kompetenz gehört nicht zum Bildungsplan dieser Unterrichtsgruppe.');
                foreach ($validEducationIds as $educationId) {
                    $selectedIds->push($lesson->unit->competencies()->firstOrCreate(['education_plan_competency_id' => $educationId], ['is_secondary' => true])->id);
                }
            }
            $selectedIds = $selectedIds->unique()->values();
            $validIds = $lesson->unit->competencies()->whereIn('id', $selectedIds)->pluck('id');
            abort_unless($validIds->count() === $selectedIds->count(), 422, 'Eine Kompetenz gehört nicht zu dieser Unterrichtseinheit.');
            $lesson->competencies()->sync($validIds);
            $lesson->unit->competencies()
                ->where('is_secondary', true)
                ->whereDoesntHave('lessons')
                ->delete();
        }

        return back()->with('success', 'Stunde wurde gespeichert.');
    }

    public function destroyLesson(TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $lesson->delete();

        return back()->with('success', 'Stunde wurde aus der UE entfernt.');
    }

    public function saveLessonAsTemplate(TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $lesson->load(['unit.template', 'template']);
        $unitTemplate = $lesson->unit->template;
        if (! $unitTemplate) {
            $unitTemplate = UnitTemplate::create([
                'organization_id' => $teachingGroup->organization_id,
                'title' => $lesson->unit->title,
                'expected_hours' => max(1, (int) $lesson->unit->lessons()->sum('duration')),
                'notes' => $lesson->unit->notes,
                'version' => 1,
                'is_active' => true,
            ]);
            $lesson->unit->update(['unit_template_id' => $unitTemplate->id]);
        }
        $attributes = [
            'unit_template_id' => $unitTemplate->id,
            'title' => $lesson->title,
            'duration_minutes' => max(1, $lesson->duration * 45),
            'objective' => $lesson->learning_goals,
            'notes' => collect([$lesson->materials, $lesson->homework, $lesson->assessment_note, $lesson->notes])->filter()->implode("\n\n"),
        ];
        $template = $lesson->template;
        if ($template) {
            $template->update($attributes + ['version' => $template->version + 1]);
        } else {
            $template = LessonTemplate::create($attributes + ['organization_id' => $teachingGroup->organization_id, 'version' => 1, 'is_active' => true]);
            $lesson->update(['lesson_template_id' => $template->id]);
        }

        return back()->with('success', 'Stunden-Vorlage wurde gespeichert.');
    }

    public function savePhaseAsTemplate(TeachingGroup $teachingGroup, LessonPhase $phase): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $phase->load(['lesson.unit.template', 'lesson.template']);
        abort_unless($phase->lesson->unit->teaching_group_id === $teachingGroup->id, 404);

        $this->saveLessonAsTemplate($teachingGroup, $phase->lesson);
        $lesson = $phase->lesson->fresh('template');
        $phase->refresh();
        PhaseTemplate::create([
            'organization_id' => $teachingGroup->organization_id,
            'lesson_template_id' => $lesson->template->id,
            'title' => $phase->title,
            'duration_minutes' => $phase->duration_minutes,
            'social_form_id' => $phase->social_form_id,
            'teacher_interaction' => $phase->teacher_interaction,
            'learner_activity' => $phase->learner_activity,
            'differentiation' => $phase->differentiation,
            'didactic_comment' => $phase->didactic_comment,
            'material' => $phase->materials,
            'media' => $phase->media,
            'position' => ((int) PhaseTemplate::where('lesson_template_id', $lesson->template->id)->max('position')) + 1,
            'version' => 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Phasen-Vorlage wurde angelegt.');
    }

    public function updateLessonCompetencies(Request $request, TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['competency_ids' => ['array'], 'competency_ids.*' => ['integer']]);
        $validIds = $lesson->unit->competencies()->whereIn('id', $data['competency_ids'] ?? [])->pluck('id');
        abort_unless($validIds->count() === count($data['competency_ids'] ?? []), 422, 'Eine Kompetenz gehört nicht zu dieser Unterrichtseinheit.');
        $lesson->competencies()->sync($validIds);

        return back()->with('success', 'Kompetenzen der Stunde wurden gespeichert.');
    }

    public function updatePhase(Request $request, TeachingGroup $teachingGroup, LessonPhase $phase): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($phase->lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $phase->update($request->validate(['title' => ['required', 'string', 'max:255'], 'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:999'], 'social_form_id' => ['nullable', 'integer', 'exists:social_forms,id'], 'teacher_interaction' => ['nullable', 'string'], 'learner_activity' => ['nullable', 'string'], 'differentiation' => ['nullable', 'string'], 'didactic_comment' => ['nullable', 'string'], 'materials' => ['nullable', 'string'], 'media' => ['nullable', 'string']]));

        return back()->with('success', 'Phase wurde gespeichert.');
    }

    public function storePhase(StoreLessonPhaseRequest $request, TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validated();
        $template = ! empty($data['phase_template_id'])
            ? PhaseTemplate::where('organization_id', $teachingGroup->organization_id)->findOrFail($data['phase_template_id'])
            : null;
        $lesson->phases()->create([
            'phase_template_id' => $template?->id,
            'title' => $data['title'] ?? $template->title,
            'position' => ($lesson->phases()->max('position') ?? 0) + 1,
            'duration_minutes' => $data['duration_minutes'] ?? $template?->duration_minutes,
            'social_form_id' => $data['social_form_id'] ?? $template?->social_form_id,
            'description' => $data['description'] ?? $template?->description,
            'materials' => $data['materials'] ?? $template?->material,
        ]);
        $lesson->scheduledLessons()->where('status', ScheduledLesson::STATUS_ASSIGNED)->update(['status' => ScheduledLesson::STATUS_PLANNED]);

        return back()->with('success', 'Phase wurde hinzugefügt.');
    }

    public function destroyPhase(TeachingGroup $teachingGroup, LessonPhase $phase): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($phase->lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $phase->delete();

        return back()->with('success', 'Phase wurde entfernt.');
    }

    public function reorderPhases(Request $request, TeachingGroup $teachingGroup, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['phase_ids' => ['required', 'array'], 'phase_ids.*' => ['integer']]);
        $allowed = $lesson->phases()->pluck('id')->sort()->values()->all();
        abort_unless($allowed === collect($data['phase_ids'])->sort()->values()->all(), 422, 'Die Phasen gehören nicht vollständig zu dieser Stunde.');
        foreach ($data['phase_ids'] as $position => $phaseId) {
            $lesson->phases()->whereKey($phaseId)->update(['position' => $position + 1]);
        }

        return back()->with('success', 'Reihenfolge der Phasen wurde gespeichert.');
    }

    public function updateScheduledLessonStatus(UpdateScheduledLessonStatusRequest $request, TeachingGroup $teachingGroup, ScheduledLesson $scheduledLesson): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($scheduledLesson->lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $status = $request->validated()['status'];
        if (in_array($status, [ScheduledLesson::STATUS_PLANNED, ScheduledLesson::STATUS_READY], true) && ! $scheduledLesson->lesson->phases()->exists()) {
            abort(422, 'Eine Stunde benötigt mindestens eine Phase für diesen Status.');
        }
        $scheduledLesson->update(['status' => $status]);

        return back()->with('success', 'Stundenstatus wurde gespeichert.');
    }

    public function reorderLessons(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['lesson_ids' => ['required', 'array'], 'lesson_ids.*' => ['integer']]);
        $allowed = $teachingUnit->lessons()->pluck('id')->sort()->values()->all();
        abort_unless($allowed === collect($data['lesson_ids'])->sort()->values()->all(), 422, 'Die Stunden gehören nicht vollständig zu dieser Unterrichtseinheit.');
        foreach ($data['lesson_ids'] as $position => $lessonId) {
            $teachingUnit->lessons()->whereKey($lessonId)->update(['position' => $position + 1]);
        }

        return back()->with('success', 'Reihenfolge der Stunden wurde gespeichert.');
    }

    public function reorderUnits(Request $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['unit_ids' => ['required', 'array'], 'unit_ids.*' => ['integer']]);
        $allowed = $teachingGroup->teachingUnits()->pluck('id')->sort()->values()->all();
        abort_unless($allowed === collect($data['unit_ids'])->sort()->values()->all(), 422, 'Die Unterrichtseinheiten gehören nicht vollständig zu dieser Gruppe.');
        foreach ($data['unit_ids'] as $position => $unitId) {
            $teachingGroup->teachingUnits()->whereKey($unitId)->update(['position' => $position + 1]);
        }

        return back()->with('success', 'Reihenfolge der Unterrichtseinheiten wurde gespeichert.');
    }

    public function autoPlan(Request $request, TeachingGroup $teachingGroup, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate([
            'start_mode' => ['required', 'in:free,end'],
            'schedule_slot_id' => ['nullable', 'integer'],
            'keep_together' => ['required', 'boolean'],
        ]);
        $slot = null;
        if ($data['start_mode'] === 'free') {
            $slot = ScheduleSlot::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['schedule_slot_id'] ?? 0);
        } else {
            $available = $workspace->availableSlots($teachingGroup);
            $lastPlanned = $teachingGroup->scheduleSlots()->whereHas('scheduledLesson')->orderBy('date')->orderBy('period_number')->get()->last();
            $slot = $lastPlanned ? $available->first(fn (ScheduleSlot $candidate) => $candidate->date->gt($lastPlanned->date) || ($candidate->date->equalTo($lastPlanned->date) && $candidate->period_number > $lastPlanned->period_number)) : $available->first();
        }
        abort_unless($slot, 422, 'Es wurde kein geeigneter Startslot gefunden.');
        $result = $workspace->autoPlan($teachingGroup, $slot, (bool) $data['keep_together']);

        return back()->with($result['overflow'] ? 'warning' : 'success', $result['overflow'] ? $result['planned'].' Stunde(n) eingeplant; '.$result['overflow'].' Stunde(n) konnten nicht eingeplant werden.' : $result['planned'].' Stunde(n) wurden automatisch eingeplant.');
    }

    public function undoLastReflow(TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $revision = $this->planFor($teachingGroup)->revisions()->where('action', 'slot_reflow')->latest()->first();
        if (! $revision || empty($revision->payload)) {
            return back()->with('warning', 'Keine rückgängig machbare Verschiebung vorhanden.');
        }
        $lessonIds = $teachingGroup->teachingUnits()->with('lessons')->get()->flatMap->lessons->pluck('id');
        ScheduledLesson::whereIn('lesson_id', $lessonIds)->delete();
        foreach ($revision->payload['assignments'] as $assignment) {
            ScheduledLesson::create(['lesson_id' => $assignment['lesson_id'], 'schedule_slot_id' => $assignment['schedule_slot_id']]);
        }
        ScheduleSlot::whereKey($revision->payload['blocked_slot_id'])->update(['status' => $revision->payload['previous_status']]);
        $revision->update(['action' => 'slot_reflow_undone']);

        return back()->with('success', 'Die letzte Verschiebung wurde rückgängig gemacht.');
    }

    public function scheduleLesson(Request $request, TeachingGroup $teachingGroup, Lesson $lesson, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['schedule_slot_id' => ['nullable', 'integer']]);
        $slot = ! empty($data['schedule_slot_id']) ? ScheduleSlot::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['schedule_slot_id']) : null;
        $result = $workspace->scheduleLesson($teachingGroup, $lesson, $slot);

        return back()->with($result['overflow'] ? 'warning' : 'success', $result['overflow'] ? $result['overflow'].' Schulstunde(n) passen nicht mehr in verfügbare Termine.' : 'Stunde wurde eingeplant.');
    }

    public function scheduleUnit(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['schedule_slot_id' => ['nullable', 'integer']]);
        $slot = ! empty($data['schedule_slot_id']) ? ScheduleSlot::where('teaching_group_id', $teachingGroup->id)->findOrFail($data['schedule_slot_id']) : null;
        $result = $workspace->scheduleUnit($teachingGroup, $teachingUnit, $slot);

        return back()->with($result['overflow'] ? 'warning' : 'success', $result['overflow'] ? $result['overflow'].' Schulstunde(n) passen nicht mehr in verfügbare Termine.' : 'Unterrichtseinheit wurde eingeplant.');
    }

    public function insertAtSlot(Request $request, TeachingGroup $teachingGroup, ScheduleSlot $scheduleSlot, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($scheduleSlot->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['type' => ['required', 'in:lesson,unit'], 'source_id' => ['required', 'integer'], 'allow_overflow' => ['sometimes', 'boolean'], 'pull_following' => ['sometimes', 'boolean']]);
        $result = $workspace->insertAtSlot($teachingGroup, $data['type'], (int) $data['source_id'], $scheduleSlot, (bool) ($data['allow_overflow'] ?? false), (bool) ($data['pull_following'] ?? false));
        if ($result['requires_confirmation'] ?? false) {
            return back()->with('planning_overflow', $result['overflow']);
        }

        return back()->with('success', $data['type'] === 'unit' ? 'Unterrichtseinheit wurde eingefügt.' : 'Stunde wurde eingefügt.');
    }

    public function unscheduleLesson(Request $request, TeachingGroup $teachingGroup, Lesson $lesson, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($lesson->unit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['move_following' => ['sometimes', 'boolean']]);
        $workspace->removeScheduled($teachingGroup, 'lesson', $lesson->id, (bool) ($data['move_following'] ?? false));

        return back()->with('success', 'Stunde wurde aus dem Jahresplan entfernt.');
    }

    public function unscheduleUnit(Request $request, TeachingGroup $teachingGroup, TeachingUnit $teachingUnit, YearPlanningWorkspace $workspace): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($teachingUnit->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['move_following' => ['sometimes', 'boolean']]);
        $workspace->removeScheduled($teachingGroup, 'unit', $teachingUnit->id, (bool) ($data['move_following'] ?? false));

        return back()->with('success', 'Unterrichtseinheit wurde aus dem Jahresplan entfernt.');
    }

    public function updateSlot(Request $request, TeachingGroup $teachingGroup, ScheduleSlot $slot): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($slot->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['status' => ['required', 'in:free,buffer,absent,cancelled,blocked'], 'label' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'], 'is_pinned' => ['sometimes', 'boolean'], 'reflow_mode' => ['sometimes', 'in:move,remove']]);
        abort_unless(! ($data['is_pinned'] ?? false) || $slot->scheduledLesson()->exists(), 422, 'Nur belegte Stunden können fixiert werden.');
        $wasAvailable = in_array($slot->status, ['free', 'buffer'], true);
        $previousStatus = $slot->status;
        $willBeAvailable = in_array($data['status'], ['free', 'buffer'], true);
        $isPinned = (bool) ($data['is_pinned'] ?? $slot->is_pinned);
        if ($wasAvailable !== $willBeAvailable) {
            $assignments = $teachingGroup->teachingUnits()->with('lessons.scheduledLessons')->get()->flatMap->lessons->flatMap->scheduledLessons->map(fn ($assignment) => $assignment->only(['schedule_slot_id', 'lesson_id']))->all();
            $result = app(YearPlanningWorkspace::class)->blockAndReflow($teachingGroup, $slot, $data['status'], $data['reflow_mode'] ?? 'move');
            $slot->update(['label' => $data['label'] ?? null, 'notes' => $data['notes'] ?? null, 'is_pinned' => $isPinned]);
            $this->revise($this->planFor($teachingGroup), auth()->id(), 'slot_reflow', 'Terminstatus geändert und Jahresplan neu angeordnet.', ['assignments' => $assignments, 'blocked_slot_id' => $slot->id, 'previous_status' => $previousStatus]);

            return back()->with($result['overflow'] ? 'warning' : 'success', $result['overflow'] ? $result['overflow'].' Schulstunde(n) passen nicht mehr in verfügbare Termine.' : 'Terminstatus gespeichert und Jahresplan neu angeordnet.');
        }
        $slot->update(['status' => $data['status'], 'label' => $data['label'] ?? null, 'notes' => $data['notes'] ?? null, 'is_pinned' => $isPinned]);

        return back()->with('success', 'Terminstatus wurde gespeichert.');
    }

    public function storeUnit(StorePlannedUnitRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validated();
        abort_unless($this->dateInYear($teachingGroup, $data['starts_on']) && $this->dateInYear($teachingGroup, $data['ends_on']), 422, 'Die Planung muss innerhalb des Schuljahres liegen.');
        if (! empty($data['unit_template_id'])) {
            abort_unless(UnitTemplate::where('id', $data['unit_template_id'])->where('organization_id', $teachingGroup->organization_id)->exists(), 422);
        }
        $this->validateTopicScope($teachingGroup, $data['curriculum_topic_id'] ?? null);
        $plan = $this->planFor($teachingGroup);
        DB::transaction(function () use ($plan, $data, $request): void {
            $unit = $plan->units()->create($data + ['position' => $plan->units()->max('position') + 1]);
            $this->revise($plan, $request->user()->id, 'unit_created', 'Unterrichtseinheit „'.$unit->title.'“ eingeplant.');
        });

        return back()->with('success', 'Unterrichtseinheit wurde eingeplant.');
    }

    public function updateUnit(StorePlannedUnitRequest $request, TeachingGroup $teachingGroup, PlannedUnit $plannedUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($plannedUnit->plan->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validated();
        abort_unless($this->dateInYear($teachingGroup, $data['starts_on']) && $this->dateInYear($teachingGroup, $data['ends_on']), 422);
        $this->validateTopicScope($teachingGroup, $data['curriculum_topic_id'] ?? null);
        DB::transaction(function () use ($plannedUnit, $data, $request): void {
            $plannedUnit->update($data);
            $this->revise($plannedUnit->plan, $request->user()->id, 'unit_updated', 'Unterrichtseinheit „'.$plannedUnit->title.'“ geändert.');
        });

        return back()->with('success', 'Planung wurde gespeichert.');
    }

    public function destroyUnit(TeachingGroup $teachingGroup, PlannedUnit $plannedUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($plannedUnit->plan->teaching_group_id === $teachingGroup->id, 404);
        $plan = $plannedUnit->plan;
        $title = $plannedUnit->title;
        DB::transaction(function () use ($plannedUnit, $plan, $title): void {
            $plannedUnit->delete();
            $this->revise($plan, auth()->id(), 'unit_deleted', 'Unterrichtseinheit „'.$title.'“ entfernt.');
        });

        return back()->with('success', 'Unterrichtseinheit wurde entfernt.');
    }

    public function splitUnit(SplitPlannedUnitRequest $request, TeachingGroup $teachingGroup, PlannedUnit $plannedUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($plannedUnit->plan->teaching_group_id === $teachingGroup->id, 404);
        $splitOn = $request->validated()['split_on'];
        abort_unless($splitOn > $plannedUnit->starts_on->toDateString() && $splitOn <= $plannedUnit->ends_on->toDateString(), 422, 'Der Teilungstag muss innerhalb der Einheit liegen.');
        $plan = $plannedUnit->plan;
        $originalEnd = $plannedUnit->ends_on->toDateString();
        DB::transaction(function () use ($plannedUnit, $splitOn, $plan, $originalEnd): void {
            $oldEnd = now()->parse($splitOn)->subDay()->toDateString();
            $remainingHours = max(1, (int) floor($plannedUnit->planned_hours / 2));
            $newHours = max(1, $plannedUnit->planned_hours - $remainingHours);
            $plannedUnit->update(['ends_on' => $oldEnd, 'planned_hours' => $remainingHours, 'is_interrupted' => true]);
            $plan->units()->create([
                'unit_template_id' => $plannedUnit->unit_template_id,
                'curriculum_topic_id' => $plannedUnit->curriculum_topic_id,
                'title' => $plannedUnit->title.' (Teil 2)',
                'starts_on' => $splitOn,
                'ends_on' => $originalEnd,
                'planned_hours' => $newHours,
                'position' => $plan->units()->max('position') + 1,
                'is_interrupted' => true,
                'notes' => $plannedUnit->notes,
            ]);
            $this->revise($plan, auth()->id(), 'unit_split', 'Unterrichtseinheit wurde geteilt.');
        });

        return back()->with('success', 'Unterrichtseinheit wurde geteilt.');
    }

    public function interruptUnit(InterruptPlannedUnitRequest $request, TeachingGroup $teachingGroup, PlannedUnit $plannedUnit): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($plannedUnit->plan->teaching_group_id === $teachingGroup->id, 404);
        $plannedUnit->update($request->validated());
        $this->revise($plannedUnit->plan, auth()->id(), 'unit_interrupted', $plannedUnit->is_interrupted ? 'Unterrichtseinheit unterbrochen.' : 'Unterbrechung der Unterrichtseinheit aufgehoben.');

        return back()->with('success', 'Unterbrechung wurde gespeichert.');
    }

    public function generateLessons(TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $plan = $this->planFor($teachingGroup)->load('units');
        $dates = $this->instructionDates($teachingGroup);
        $periods = $teachingGroup->schoolPeriods()->get(['school_periods.id', 'period_number'])->mapWithKeys(fn ($period) => [$period->pivot->weekday.'-'.$period->period_number => true]);
        $available = collect($dates)->filter(fn ($date) => $periods->keys()->contains(fn ($key) => (int) explode('-', $key)[0] === $date->dayOfWeekIso));
        DB::transaction(function () use ($plan, $available): void {
            foreach ($plan->units as $unit) {
                $unitDates = $available->filter(fn ($date) => $date->toDateString() >= $unit->starts_on->toDateString() && $date->toDateString() <= $unit->ends_on->toDateString())->values();
                foreach ($unitDates->take($unit->planned_hours) as $position => $date) {
                    $lesson = $unit->lessons()->firstOrCreate(['position' => $position + 1], ['title' => $unit->title.' – '.($position + 1).'. Stunde']);
                    $lesson->occurrences()->firstOrCreate(['planned_on' => $date->toDateString()]);
                }
            }
            $this->revise($plan, auth()->id(), 'lessons_generated', 'Stunden aus dem Stundenplan erzeugt.');
        });

        return back()->with('success', 'Stunden wurden aus dem Stundenplan erzeugt.');
    }

    public function updateOccurrence(UpdateLessonOccurrenceRequest $request, TeachingGroup $teachingGroup, LessonOccurrence $occurrence): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($occurrence->plannedLesson->unit->plan->teaching_group_id === $teachingGroup->id, 404);
        $occurrence->update($request->validated());
        $this->revise($occurrence->plannedLesson->unit->plan, auth()->id(), 'occurrence_updated', 'Stundenstatus geändert.');

        return back()->with('success', 'Stundenstatus wurde gespeichert.');
    }

    private function planFor(TeachingGroup $group): GroupYearPlan
    {
        return GroupYearPlan::firstOrCreate(['teaching_group_id' => $group->id], ['organization_id' => $group->organization_id, 'school_year_id' => $group->school_year_id]);
    }

    private function dateInYear(TeachingGroup $group, string $date): bool
    {
        return $date >= $group->schoolYear->starts_on->toDateString() && $date <= $group->schoolYear->ends_on->toDateString();
    }

    private function instructionDates(TeachingGroup $group): array
    {
        $year = $group->schoolYear;
        $blocked = $year->days()->whereIn('kind', ['no_instruction', 'holiday'])->pluck('date')->map(fn ($date) => (string) $date)->all();
        $blocked = array_merge($blocked, $year->holidayPeriods()->get()->flatMap(fn ($holiday) => collect(CarbonPeriod::create($holiday->starts_on, $holiday->ends_on))->map->toDateString())->all());
        $blocked = array_merge($blocked, $year->calendarExceptions()->whereIn('kind', ['no_instruction', 'holiday'])->pluck('date')->map(fn ($date) => (string) $date)->all());

        return collect(CarbonPeriod::create($group->schoolYear->starts_on, $group->schoolYear->ends_on))
            ->filter(fn ($date) => $date->isWeekday() && ! in_array($date->toDateString(), $blocked, true))->values()->all();
    }

    private function checks(TeachingGroup $group, GroupYearPlan $plan): array
    {
        $units = $plan->units()->with('curriculumTopic.competencies')->get();
        $topics = $group->curricula()->with('versions.topics.competencies')->get()->flatMap(fn ($curriculum) => $curriculum->versions->flatMap->topics);
        $plannedTopicIds = $units->pluck('curriculum_topic_id')->filter();

        return [
            'available_hours' => $this->availableHours($group),
            'units_without_competencies' => $units->filter(fn ($unit) => ! $unit->curriculum_topic_id || $unit->curriculumTopic->competencies->isEmpty())->pluck('title')->values(),
            'topics_without_planned_unit' => $topics->whereNotIn('id', $plannedTopicIds)->pluck('title')->values(),
            'planned_competence_count' => $units->flatMap(fn ($unit) => $unit->curriculumTopic?->competencies ?? collect())->unique('id')->count(),
            'total_competence_count' => $topics->flatMap->competencies->unique('id')->count(),
            'uncovered_competencies' => $topics->whereNotIn('id', $plannedTopicIds)->flatMap->competencies->pluck('display')->filter()->unique()->values(),
        ];
    }

    private function calendar(TeachingGroup $group): array
    {
        return $group->schoolYear->days()->orderBy('date')->get(['date', 'kind', 'label'])->map(fn ($day) => [
            'date' => $day->date->toDateString(),
            'kind' => $day->kind,
            'label' => $day->label,
        ])->all();
    }

    private function validateTopicScope(TeachingGroup $group, ?int $topicId): void
    {
        if ($topicId === null) {
            return;
        }
        $curriculumIds = $group->curricula()->pluck('curricula.id');
        abort_unless(CurriculumTopic::whereKey($topicId)->whereHas('version', fn ($query) => $query->whereIn('curriculum_id', $curriculumIds))->exists(), 422, 'Das Curriculumthema gehört nicht zu dieser Unterrichtsgruppe.');
    }

    private function availableHours(TeachingGroup $group): int
    {
        $weekdays = $group->schoolPeriods()->get()->pluck('pivot.weekday')->unique()->all();

        return collect($this->instructionDates($group))->filter(fn ($date) => in_array($date->dayOfWeekIso, $weekdays, true))->count();
    }

    private function revise(GroupYearPlan $plan, int $userId, string $action, string $description, ?array $payload = null): void
    {
        $plan->increment('revision');
        $plan->revisions()->create(['user_id' => $userId, 'revision' => $plan->fresh()->revision, 'action' => $action, 'description' => $description, 'payload' => $payload]);
    }
}
