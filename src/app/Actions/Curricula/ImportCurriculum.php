<?php

namespace App\Actions\Curricula;

use App\Models\Curriculum;
use App\Models\CurriculumEducationPlanBinding;
use App\Models\CurriculumImportRun;
use App\Models\CurriculumTopic;
use App\Models\CurriculumTopicEducationPlanReference;
use App\Models\CurriculumTopicPerspective;
use App\Models\CurriculumTopicProfile;
use App\Models\CurriculumVersion;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
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
                $existingBindings = $version->bindings()->get()->keyBy(fn ($binding): string => implode('|', [$binding->denomination, $binding->role]));
                $version->topics()->delete();
                $version->bindings()->delete();
                foreach ($payload['education_plan_bindings'] ?? [] as $binding) {
                    $previousBinding = $existingBindings->get(implode('|', [$binding['denomination'] ?? null, $binding['role'] ?? null]));
                    $planCode = $binding['plan_code'] ?? $previousBinding?->plan_code;
                    $plan = ! empty($planCode) ? EducationPlan::where('external_identifier', $planCode)->first() : null;
                    CurriculumEducationPlanBinding::create([
                        'curriculum_version_id' => $version->id, 'education_plan_id' => $plan?->id,
                        'plan_code' => $planCode, 'role' => $binding['role'] ?? null,
                        'denomination' => $binding['denomination'] ?? null, 'subject' => $binding['subject'] ?? null,
                        'raw_data' => $binding,
                    ]);
                }
                $bindings = $version->bindings()->get();
                foreach ($payload['units'] as $position => $unit) {
                    $topic = CurriculumTopic::create([
                        'curriculum_version_id' => $version->id, 'external_identifier' => $unit['id'] ?? null,
                        'source_curriculum_version_id' => $version->id,
                        'year' => $existingYears->get($unit['id'] ?? null) ?? ($unit['year'] ?? null),
                        'number' => $unit['number'] ?? null, 'title' => $unit['title'], 'position' => $position,
                        'hours' => $unit['hours'] ?? null, 'preparation_questions' => $unit['preparation_questions'] ?? [],
                        'shared_plan' => $unit['shared_plan'] ?? [], 'raw_rows' => $unit['raw_rows'] ?? [],
                    ]);
                    foreach ($unit['perspectives'] ?? [] as $denomination => $text) {
                        CurriculumTopicPerspective::create([
                            'curriculum_topic_id' => $topic->id,
                            'denomination' => $denomination,
                            'text' => $text,
                        ]);
                    }
                    $competencyPosition = 0;
                    foreach ($unit['process_competencies'] ?? [] as $competency) {
                        $this->createCompetency($topic, $competency['denomination'] ?? null, 'process', $competency['id'] ?? null, $competencyPosition++, $bindings);
                    }
                    foreach ($unit['denominational_profiles'] ?? [] as $denomination => $profile) {
                        CurriculumTopicProfile::create(['curriculum_topic_id' => $topic->id, 'denomination' => $denomination, 'perspective' => $profile['perspective'] ?? []]);
                        foreach ($profile['content_competencies'] ?? [] as $competency) {
                            $references = $competency['references'] ?? [];
                            if ($references === []) {
                                $this->createCompetency($topic, $denomination, 'content', $competency['id'] ?? null, $competencyPosition++, $bindings);
                            }
                            foreach ($references as $reference) {
                                $this->createCompetency($topic, $denomination, 'content', $reference['id'] ?? null, $competencyPosition++, $bindings);
                            }
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

    private function createCompetency(CurriculumTopic $topic, ?string $denomination, string $kind, ?string $identifier, int $position, $bindings): void
    {
        CurriculumTopicEducationPlanReference::create([
            'curriculum_topic_id' => $topic->id,
            'education_plan_competency_id' => $this->resolveCompetency($topic, $identifier, $denomination, $bindings),
            'denomination' => $denomination,
            'competency_kind' => $kind,
            'position' => $position,
        ]);
    }

    private function resolveCompetency(CurriculumTopic $topic, ?string $identifier, ?string $denomination, $bindings): int
    {
        $denominationalPlanVersionIds = $bindings
            ->filter(fn (CurriculumEducationPlanBinding $binding): bool => $binding->denomination === $denomination && $binding->education_plan_id !== null)
            ->flatMap(fn (CurriculumEducationPlanBinding $binding) => EducationPlanVersion::where('education_plan_id', $binding->education_plan_id)->pluck('id'))
            ->unique()
            ->values();

        $competencies = $this->competenciesForVersions($identifier, $denominationalPlanVersionIds);

        if ($identifier === null || $competencies->count() !== 1) {
            $bindingSummary = $bindings->map(fn (CurriculumEducationPlanBinding $binding): string => sprintf('%s=%s', $binding->denomination ?: 'common', $binding->plan_code ?: 'nicht aufgelöst'))->implode(', ');
            throw new \RuntimeException(sprintf(
                'Kompetenzreferenz konnte nicht eindeutig aufgelöst werden (Thema „%s“, Kennung „%s“, Konfession „%s“, Bindungen: %s).',
                $topic->title,
                $identifier ?: 'leer',
                $denomination ?: 'common',
                $bindingSummary ?: 'keine',
            ));
        }

        return $competencies->first()->id;
    }

    private function competenciesForVersions(?string $identifier, $versionIds)
    {
        return EducationPlanCompetency::query()
            ->where('external_identifier', $identifier)
            ->whereHas('area', fn ($query) => $query->whereIn('education_plan_version_id', $versionIds))
            ->get();
    }

    private function assertPayload(array $payload): void
    {
        if (($payload['type'] ?? null) !== 'confessional_cooperative_curriculum' || ! isset($payload['schema_version'], $payload['metadata']['title'], $payload['units']) || ! is_array($payload['units'])) {
            throw new \InvalidArgumentException('Ungültiges Curriculum-Importformat.');
        }

        foreach ($payload['units'] as $unit) {
            foreach ($unit['perspectives'] ?? [] as $denomination => $text) {
                if (! is_string($denomination) || trim($denomination) === '' || ! is_string($text)) {
                    throw new \InvalidArgumentException('Perspektiven müssen nach Konfession verschlüsselt und als Text angegeben werden.');
                }
                if ($denomination !== 'common' && ! in_array($denomination, $payload['metadata']['denominations'] ?? [], true)) {
                    throw new \InvalidArgumentException('Eine Perspektive verweist auf eine unbekannte Konfession.');
                }
            }
            foreach ($unit['process_competencies'] ?? [] as $competency) {
                if (! is_string($competency['denomination'] ?? null) || trim($competency['denomination']) === '') {
                    throw new \InvalidArgumentException('Jede Prozesskompetenz muss eine Konfession enthalten.');
                }
            }
        }
    }
}
