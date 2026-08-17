<?php

namespace App\Actions\Curricula;

use App\Models\Curriculum;
use App\Models\CurriculumEducationPlanBinding;
use App\Models\CurriculumImportRun;
use App\Models\CurriculumTopic;
use App\Models\CurriculumTopicCompetency;
use App\Models\CurriculumTopicProfile;
use App\Models\CurriculumVersion;
use App\Models\EducationPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportCurriculum
{
    /** @return array{curriculum: Curriculum, version: CurriculumVersion, import_run: CurriculumImportRun} */
    public function execute(string $path, ?int $organizationId = null): array
    {
        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertPayload($payload);
        $metadata = $payload['metadata'];
        $checksum = hash_file('sha256', $path);

        return DB::transaction(function () use ($path, $checksum, $payload, $metadata, $organizationId): array {
            $run = CurriculumImportRun::create([
                'organization_id' => $organizationId,
                'source_path' => $path,
                'source_checksum' => $checksum,
                'status' => 'running',
                'started_at' => now(),
            ]);
            try {
                $identifier = pathinfo($path, PATHINFO_FILENAME);
                $curriculum = Curriculum::updateOrCreate(
                    ['organization_id' => $organizationId, 'external_identifier' => $identifier],
                    [
                        'title' => $metadata['title'], 'country' => $metadata['country'] ?? null,
                        'state' => $metadata['state'] ?? null, 'school_type' => $metadata['school_type'] ?? null,
                        'grades' => $metadata['grades'] ?? [], 'variant' => $metadata['variant'] ?? null,
                        'cooperation_model' => $metadata['cooperation_model'] ?? null,
                        'denominations' => $metadata['denominations'] ?? [],
                    ],
                );
                $version = CurriculumVersion::updateOrCreate(
                    ['curriculum_id' => $curriculum->id, 'external_identifier' => 'import-'.$payload['schema_version']],
                    [
                        'schema_version' => $payload['schema_version'], 'source_url' => $metadata['source']['url'] ?? null,
                        'source_format' => $metadata['source']['format'] ?? null, 'is_editable' => false,
                        'is_complete' => $metadata['conversion']['complete'] ?? true,
                        'conversion_metadata' => $metadata['conversion'] ?? null, 'raw_payload' => $payload,
                    ],
                );
                $existingYears = $version->topics()->pluck('year', 'external_identifier');
                $version->topics()->delete();
                $version->bindings()->delete();
                foreach ($payload['education_plan_bindings'] ?? [] as $binding) {
                    $plan = ! empty($binding['plan_code']) ? EducationPlan::where('external_identifier', $binding['plan_code'])->first() : null;
                    CurriculumEducationPlanBinding::create([
                        'curriculum_version_id' => $version->id, 'education_plan_id' => $plan?->id,
                        'plan_code' => $binding['plan_code'] ?? null, 'role' => $binding['role'] ?? null,
                        'denomination' => $binding['denomination'] ?? null, 'subject' => $binding['subject'] ?? null,
                        'raw_data' => $binding,
                    ]);
                }
                foreach ($payload['units'] as $position => $unit) {
                    $topic = CurriculumTopic::create([
                        'curriculum_version_id' => $version->id, 'external_identifier' => $unit['id'] ?? null,
                        'source_curriculum_version_id' => $version->id,
                        'year' => $existingYears->get($unit['id'] ?? null) ?? ($unit['year'] ?? null),
                        'number' => $unit['number'] ?? null, 'title' => $unit['title'], 'position' => $position,
                        'hours' => $unit['hours'] ?? null, 'preparation_questions' => $unit['preparation_questions'] ?? [],
                        'shared_plan' => $unit['shared_plan'] ?? [], 'raw_rows' => $unit['raw_rows'] ?? [],
                    ]);
                    $competencyPosition = 0;
                    foreach ($unit['process_competencies'] ?? [] as $competency) {
                        $this->createCompetency($topic, null, 'process', $competency, $competencyPosition++);
                    }
                    foreach ($unit['denominational_profiles'] ?? [] as $denomination => $profile) {
                        CurriculumTopicProfile::create(['curriculum_topic_id' => $topic->id, 'denomination' => $denomination, 'perspective' => $profile['perspective'] ?? []]);
                        foreach ($profile['content_competencies'] ?? [] as $competency) {
                            $this->createCompetency($topic, $denomination, 'content', $competency, $competencyPosition++);
                        }
                    }
                }
                $statistics = ['topics' => count($payload['units']), 'bindings' => count($payload['education_plan_bindings'] ?? [])];
                $run->update(['curriculum_version_id' => $version->id, 'status' => 'completed', 'statistics' => $statistics, 'finished_at' => now()]);

                return ['curriculum' => $curriculum, 'version' => $version->fresh(), 'import_run' => $run->fresh()];
            } catch (\Throwable $exception) {
                $run->update(['status' => 'failed', 'error_message' => Str::limit($exception->getMessage(), 1000), 'finished_at' => now()]);
                throw $exception;
            }
        });
    }

    private function createCompetency(CurriculumTopic $topic, ?string $denomination, string $kind, array $data, int $position): void
    {
        CurriculumTopicCompetency::create([
            'curriculum_topic_id' => $topic->id, 'denomination' => $denomination, 'competency_kind' => $kind,
            'external_identifier' => $data['id'] ?? ($data['references'][0]['id'] ?? null),
            'display' => $data['display'] ?? ($data['references'][0]['display'] ?? null),
            'raw_text' => $data['raw'] ?? null, 'position' => $position,
        ]);
    }

    private function assertPayload(array $payload): void
    {
        if (($payload['type'] ?? null) !== 'confessional_cooperative_curriculum' || ! isset($payload['schema_version'], $payload['metadata']['title'], $payload['units']) || ! is_array($payload['units'])) {
            throw new \InvalidArgumentException('Ungültiges Curriculum-Importformat.');
        }
    }
}
