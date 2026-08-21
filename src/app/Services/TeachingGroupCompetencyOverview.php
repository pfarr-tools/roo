<?php

namespace App\Services;

use App\Models\CurriculumEducationPlanBinding;
use App\Models\CurriculumTopicCompetency;
use App\Models\EducationPlanCompetency;
use App\Models\TeachingGroup;
use Illuminate\Support\Collection;

class TeachingGroupCompetencyOverview
{
    public function forGroup(TeachingGroup $teachingGroup, CompetencyResolver $competencyResolver): Collection
    {
        $teachingGroup->loadMissing(['gradeLevels', 'curricula:id,title,denominations']);
        $gradeLevels = $teachingGroup->gradeLevels->pluck('grade_level')->map(fn ($grade) => (int) preg_replace('/\D+/', '', (string) $grade))->filter();
        $curriculumIds = $teachingGroup->curricula->pluck('id');
        $planCompetencies = CurriculumTopicCompetency::query()
            ->whereHas('topic.version', fn ($query) => $query->whereIn('curriculum_id', $curriculumIds))
            ->whereHas('topic', fn ($query) => $query->whereIn('year', $gradeLevels))
            ->forGroup($teachingGroup)
            ->with(['topic:id,title,year', 'educationPlanCompetency.area:id,kind'])
            ->orderBy('competency_kind')->orderBy('position')->get();
        $plannedCompetencies = $teachingGroup->teachingUnits()->with(['lessons:id,teaching_unit_id,duration', 'lessons.competencies:id,teaching_unit_id,curriculum_topic_competency_id,education_plan_competency_id'])->get()
            ->flatMap(fn ($unit) => $unit->lessons->flatMap(fn ($lesson) => $lesson->competencies->map(fn ($competency) => ['curriculum_id' => $competency->curriculum_topic_competency_id, 'education_id' => $competency->education_plan_competency_id, 'hours' => $lesson->duration])))
            ->groupBy('curriculum_id');
        $coveredEducationHours = $plannedCompetencies->filter(fn ($items, $id) => $id === '' || $id === null)->flatten(1)->groupBy('education_id')->map(fn ($items) => $items->sum('hours'));
        $coveredHours = $plannedCompetencies->reject(fn ($items, $id) => $id === '' || $id === null)->map(fn ($items) => $items->sum('hours'));
        $competencies = $planCompetencies->map(function ($competency) use ($competencyResolver, $coveredHours, $coveredEducationHours): array {
            $presentation = $competencyResolver->present($competency);
            $text = $presentation['text'] ?: $competency->educationPlanCompetency?->text ?: $competency->text ?: $competency->raw_text;
            $presentation['text'] = $text;
            $presentation['label'] = $presentation['identifier'] && $text ? $presentation['identifier'].' – '.$text : ($text ?: $presentation['identifier']);

            return [
                'id' => $competency->id,
                'external_identifier' => $competency->external_identifier,
                'topic_id' => $competency->topic->id,
                'topic_title' => $competency->topic->title,
                'grade' => $competency->topic->year,
                'kind' => $competency->competency_kind,
                'denomination' => $competency->denomination,
                'education_plan_competency_id' => $competency->education_plan_competency_id,
                'presentation' => $presentation,
                'missing_from_curriculum' => false,
                'covered_hours' => $coveredHours->get($competency->id, 0) ?: $coveredEducationHours->get($competency->education_plan_competency_id, 0),
            ];
        })->values();
        $curriculumEducationIds = CurriculumTopicCompetency::query()
            ->whereHas('topic.version', fn ($query) => $query->whereIn('curriculum_id', $curriculumIds))
            ->forGroup($teachingGroup)->pluck('education_plan_competency_id')->filter()->unique();
        $curriculumCompetencyIdentifiers = CurriculumTopicCompetency::query()
            ->whereHas('topic.version', fn ($query) => $query->whereIn('curriculum_id', $curriculumIds))
            ->forGroup($teachingGroup)->pluck('external_identifier')->filter()->unique();
        $curriculumVersionIds = $teachingGroup->curricula()->with('versions:id,curriculum_id')->get()->flatMap->versions->pluck('id');
        $educationPlanIds = CurriculumEducationPlanBinding::whereIn('curriculum_version_id', $curriculumVersionIds)->whereNotNull('education_plan_id')->pluck('education_plan_id')->unique();
        $educationPlanAreas = EducationPlanCompetency::query()
            ->whereHas('area.version', fn ($query) => $query->whereIn('education_plan_id', $educationPlanIds))
            ->with('area:id,kind,external_identifier,title')->get()
            ->flatMap(function ($competency) use ($competencyResolver): array {
                $identifier = $competencyResolver->identifier($competency);
                return [$identifier => $competency->area, $competency->external_identifier => $competency->area];
            });
        $missingCompetencies = EducationPlanCompetency::query()
            ->whereHas('area', function ($query) use ($educationPlanIds, $gradeLevels): void {
                $query->whereHas('version', fn ($version) => $version->whereIn('education_plan_id', $educationPlanIds))
                    ->where(function ($stage) use ($gradeLevels): void {
                        $stage->whereNull('education_plan_stage_id')->orWhereHas('stage.gradeLevels', fn ($grades) => $grades->whereIn('numeric_value', $gradeLevels));
                    });
            })
            ->whereNotIn('id', $curriculumEducationIds)
            ->with(['area:id,education_plan_stage_id,kind', 'variants:id,education_plan_competency_id,text,position'])
            ->orderBy('external_identifier')->get()
            ->reject(fn ($competency) => $curriculumCompetencyIdentifiers->contains($competency->external_identifier))
            ->map(function ($competency) use ($competencyResolver, $coveredEducationHours): array {
                return [
                    'id' => 'education-'.$competency->id,
                    'external_identifier' => $competency->external_identifier,
                    'education_plan_competency_id' => $competency->id,
                    'topic_id' => null,
                    'topic_title' => null,
                    'grade' => null,
                    'kind' => $competency->area->kind,
                    'denomination' => null,
                    'presentation' => $competencyResolver->present($competency),
                    'missing_from_curriculum' => true,
                    'covered_hours' => $coveredEducationHours->get($competency->id, 0),
                ];
            })->values();
        return $competencies->concat($missingCompetencies)->map(function (array $competency) use ($educationPlanAreas): array {
            $area = $educationPlanAreas->get($competency['presentation']['identifier']);
            $competency['area'] = $area ? ['identifier' => $area->external_identifier, 'title' => $area->title, 'kind' => $area->kind] : null;
            return $competency;
        })->groupBy(fn (array $competency) => $competency['presentation']['identifier'] ?: $competency['id'])
            ->map(function ($items): array {
                $competency = $items->first();
                $competency['covered_hours'] = $items->sum('covered_hours');
                $competency['missing_from_curriculum'] = $items->every(fn (array $item) => $item['missing_from_curriculum']);
                return $competency;
            })->values();
    }
}
