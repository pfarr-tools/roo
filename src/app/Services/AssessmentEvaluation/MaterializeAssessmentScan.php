<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\Assessment;
use App\Models\AssessmentBooklet;
use App\Models\AssessmentBookletFragment;
use App\Services\AssessmentScan\AssessmentScanFragmentBuilder;
use App\Services\AssessmentScan\AssessmentScanSessionStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class MaterializeAssessmentScan
{
    public function __construct(
        private readonly AssessmentScanSessionStore $sessions,
        private readonly AssessmentScanFragmentBuilder $fragments,
        private readonly AssessmentTemplateCropper $cropper,
    ) {}

    /** @return Collection<int, AssessmentBooklet> */
    public function handle(Assessment $assessment, string $sessionId): Collection
    {
        $manifest = $this->sessions->manifest($sessionId);
        if ($manifest === null || $manifest['assessment_id'] !== (string) $assessment->getKey() || ($manifest['status'] ?? null) !== 'completed') {
            throw new RuntimeException('Die Scan-Session kann nicht materialisiert werden.');
        }

        $storedPaths = [];
        try {
            $booklets = DB::transaction(function () use ($assessment, $sessionId, &$storedPaths): Collection {
                $lockedAssessment = Assessment::query()->lockForUpdate()->findOrFail($assessment->getKey());
                $taskIds = $lockedAssessment->tasks()->pluck('assessment_tasks.id')->mapWithKeys(fn (int $id): array => [$id => true])->all();
                $nextNumber = ((int) $lockedAssessment->booklets()->max('number')) + 1;
                $materialized = new Collection;

                foreach ($this->fragments->booklets($sessionId) as $scanBooklet) {
                    $booklet = AssessmentBooklet::create([
                        'assessment_id' => $lockedAssessment->id,
                        'number' => $nextNumber++,
                        'status' => 'open',
                    ]);
                    $namePath = $this->pathFor($booklet, 'name.png');
                    $this->store($namePath, $this->cropper->nameFragment($this->sessions->pageContents($sessionId, $scanBooklet['start_page'])));
                    $storedPaths[] = $namePath;
                    $booklet->update(['name_fragment_path' => $namePath]);

                    foreach ($scanBooklet['fragments'] as $fragment) {
                        $taskId = (int) $fragment['task_id'];
                        if (! isset($taskIds[$taskId])) {
                            continue;
                        }

                        $path = $this->pathFor($booklet, "task-{$taskId}-{$fragment['page']}.png");
                        $this->store($path, $this->cropper->taskFragment(
                            $this->sessions->pageContents($sessionId, $fragment['page']),
                            (float) $fragment['start_y_cm'],
                            (float) $fragment['end_y_cm'],
                        ));
                        $storedPaths[] = $path;
                        AssessmentBookletFragment::create([
                            'assessment_booklet_id' => $booklet->id,
                            'assessment_task_id' => $taskId,
                            'image_path' => $path,
                            'page' => $fragment['page'],
                            'start_y_cm' => $fragment['start_y_cm'],
                            'end_y_cm' => $fragment['end_y_cm'],
                        ]);
                    }

                    $materialized->push($booklet->load('fragments'));
                }

                return $materialized;
            });
        } catch (Throwable $exception) {
            Storage::disk('documents')->delete($storedPaths);

            throw $exception;
        }

        $this->sessions->delete($sessionId);

        return $booklets;
    }

    private function pathFor(AssessmentBooklet $booklet, string $filename): string
    {
        return "assessment-booklets/{$booklet->assessment_id}/{$booklet->id}/{$filename}";
    }

    private function store(string $path, string $contents): void
    {
        if (! Storage::disk('documents')->put($path, $contents)) {
            throw new RuntimeException('Der Ausschnitt konnte nicht dauerhaft gespeichert werden.');
        }
    }
}
