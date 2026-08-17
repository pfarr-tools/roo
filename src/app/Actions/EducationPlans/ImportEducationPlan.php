<?php

namespace App\Actions\EducationPlans;

use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetenceVariant;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanGradeLevel;
use App\Models\EducationPlanImportRun;
use App\Models\EducationPlanLevel;
use App\Models\EducationPlanStage;
use App\Models\EducationPlanVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ImportEducationPlan
{
    /** @return array{plan: EducationPlan, version: EducationPlanVersion, import_run: EducationPlanImportRun} */
    public function execute(string $path, ?int $organizationId = null): array
    {
        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertPayload($payload);
        $checksum = hash_file('sha256', $path);
        $metadata = $payload['metadata'];

        return DB::transaction(function () use ($path, $checksum, $payload, $metadata, $organizationId): array {
            $run = EducationPlanImportRun::create([
                'organization_id' => $organizationId,
                'source_path' => $path,
                'source_checksum' => $checksum,
                'schema_version' => $payload['schema_version'],
                'status' => 'running',
                'started_at' => now(),
            ]);

            try {
                $plan = EducationPlan::updateOrCreate(
                    ['organization_id' => $organizationId, 'external_identifier' => $metadata['plan_code']],
                    [
                        'country' => $metadata['country'] ?? null,
                        'state' => $metadata['state'] ?? null,
                        'subject' => $metadata['subject'],
                        'title' => $metadata['title'],
                    ],
                );
                $versionIdentifier = (string) ($metadata['version'] ?? 'unversioned');
                $version = EducationPlanVersion::updateOrCreate(
                    ['education_plan_id' => $plan->id, 'external_identifier' => $versionIdentifier],
                    [
                        'schema_version' => $payload['schema_version'],
                        'title' => $metadata['title'],
                        'version_date' => $metadata['version_date'] ?? null,
                        'source_url' => $metadata['source_url'] ?? null,
                        'is_complete' => $metadata['conversion']['complete'] ?? true,
                        'conversion_metadata' => $metadata['conversion'] ?? null,
                        'raw_payload' => $payload,
                        'supplementary_content_raw' => $payload['supplementary_content_raw'] ?? null,
                    ],
                );

                $this->clearVersion($version);
                $counts = ['stages' => 0, 'areas' => 0, 'competencies' => 0, 'variants' => 0, 'relations' => 0];
                $levelIds = [];

                foreach ($metadata['school_types'] ?? [] as $position => $schoolType) {
                    DB::table('education_plan_school_types')->insert([
                        'education_plan_version_id' => $version->id,
                        'external_identifier' => Str::slug($schoolType),
                        'label' => $schoolType,
                        'position' => $position,
                    ]);
                }

                foreach ($payload['stages'] as $stagePosition => $stageData) {
                    $stage = EducationPlanStage::create([
                        'education_plan_version_id' => $version->id,
                        'external_identifier' => $stageData['id'],
                        'label' => $stageData['label'],
                        'course_identifier' => $stageData['course']['id'] ?? null,
                        'course_label' => $stageData['course']['label'] ?? null,
                        'position' => $stagePosition,
                        'raw_data' => $stageData,
                    ]);
                    $counts['stages']++;

                    foreach ($stageData['grades'] ?? [] as $gradePosition => $grade) {
                        $grade = (string) $grade;
                        $gradeLevel = EducationPlanGradeLevel::firstOrCreate(
                            ['education_plan_version_id' => $version->id, 'external_identifier' => $grade],
                            ['label' => 'Klasse '.$grade, 'numeric_value' => is_numeric($grade) ? (int) $grade : null, 'position' => $gradePosition],
                        );
                        $stage->gradeLevels()->syncWithoutDetaching([$gradeLevel->id]);
                    }

                    foreach ($stageData['levels'] ?? [] as $levelPosition => $levelData) {
                        $level = EducationPlanLevel::firstOrCreate(
                            ['education_plan_version_id' => $version->id, 'external_identifier' => $levelData['id']],
                            ['label' => $levelData['label'], 'position' => $levelPosition],
                        );
                        $levelIds[$levelData['id']] = $level->id;
                        $stage->levels()->syncWithoutDetaching([$level->id]);
                    }

                    foreach ($stageData['domains'] ?? [] as $areaPosition => $areaData) {
                        $area = $this->createArea($version, $stage, $areaData, 'content', $areaPosition);
                        $counts['areas']++;
                        $this->createCompetencies($area, $areaData['competencies'] ?? [], $levelIds, $counts);
                    }
                }

                foreach ($payload['process_competencies'] ?? [] as $areaPosition => $areaData) {
                    $area = $this->createArea($version, null, $areaData, 'process', $areaPosition);
                    $counts['areas']++;
                    $this->createCompetencies($area, $areaData['competencies'] ?? [], $levelIds, $counts);
                }

                foreach ($payload['guiding_principles'] ?? [] as $position => $principle) {
                    DB::table('education_plan_guiding_principles')->insert([
                        'education_plan_version_id' => $version->id,
                        'external_identifier' => $principle['id'] ?? null,
                        'title' => $principle['title'],
                        'text' => $principle['text'],
                        'position' => $position,
                    ]);
                }

                $run->update(['education_plan_version_id' => $version->id, 'status' => 'completed', 'statistics' => $counts, 'finished_at' => now()]);

                return ['plan' => $plan, 'version' => $version->fresh(), 'import_run' => $run->fresh()];
            } catch (\Throwable $exception) {
                $run->update(['status' => 'failed', 'error_message' => Str::limit($exception->getMessage(), 1000), 'finished_at' => now()]);
                throw $exception;
            }
        });
    }

    private function createArea(EducationPlanVersion $version, ?EducationPlanStage $stage, array $data, string $kind, int $position): EducationPlanCompetenceArea
    {
        return EducationPlanCompetenceArea::create([
            'education_plan_version_id' => $version->id,
            'education_plan_stage_id' => $stage?->id,
            'kind' => $kind,
            'external_identifier' => $data['id'],
            'title' => $data['title'],
            'introduction' => $data['introduction'] ?? null,
            'notes' => $data['notes'] ?? null,
            'source_raw' => $data['source_raw'] ?? null,
            'position' => $position,
        ]);
    }

    private function createCompetencies(EducationPlanCompetenceArea $area, array $items, array $levelIds, array &$counts): void
    {
        foreach ($items as $position => $data) {
            $competency = EducationPlanCompetency::create([
                'education_plan_competence_area_id' => $area->id,
                'external_identifier' => $data['id'],
                'number' => $data['number'] ?? null,
                'text' => $data['text'] ?? null,
                'position' => $position,
            ]);
            $counts['competencies']++;

            foreach ($data['variants'] ?? [] as $variantPosition => $variant) {
                EducationPlanCompetenceVariant::create([
                    'education_plan_competency_id' => $competency->id,
                    'education_plan_level_id' => $variant['level'] === null ? null : ($levelIds[$variant['level']] ?? null),
                    'text' => $variant['text'],
                    'position' => $variantPosition,
                ]);
                $counts['variants']++;
            }

            foreach ($data['references_raw'] ?? [] as $referencePosition => $reference) {
                DB::table('education_plan_competence_relations')->insert([
                    'source_competency_id' => $competency->id,
                    'raw_reference' => $reference,
                    'position' => $referencePosition,
                ]);
                $counts['relations']++;
            }
        }
    }

    private function clearVersion(EducationPlanVersion $version): void
    {
        DB::table('education_plan_school_types')->where('education_plan_version_id', $version->id)->delete();
        DB::table('education_plan_guiding_principles')->where('education_plan_version_id', $version->id)->delete();
        DB::table('education_plan_competence_areas')->where('education_plan_version_id', $version->id)->delete();
        DB::table('education_plan_stage_grade_level')->whereIn('education_plan_stage_id', $version->stages()->pluck('id'))->delete();
        DB::table('education_plan_stage_level')->whereIn('education_plan_stage_id', $version->stages()->pluck('id'))->delete();
        DB::table('education_plan_stages')->where('education_plan_version_id', $version->id)->delete();
        DB::table('education_plan_levels')->where('education_plan_version_id', $version->id)->delete();
        DB::table('education_plan_grade_levels')->where('education_plan_version_id', $version->id)->delete();
    }

    private function assertPayload(mixed $payload): void
    {
        if (! is_array($payload)
            || (($payload['type'] ?? null) !== 'education_plan')
            || ! isset($payload['schema_version'], $payload['metadata']['plan_code'], $payload['metadata']['subject'], $payload['metadata']['title'])) {
            throw new RuntimeException('Ungültiges Bildungsplan-Importformat.');
        }
    }
}
