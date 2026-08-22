<?php

namespace App\Http\Controllers;

use App\Documents\AssessmentDocument;
use App\Documents\DocumentOutputFormat;
use App\Http\Requests\AssessmentScanFragmentRequest;
use App\Http\Requests\AssessmentScanSessionRequest;
use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\StudentAssessmentResult;
use App\Models\TeachingGroup;
use App\Services\AssessmentScan\AssessmentPdfScanner;
use App\Services\AssessmentScan\AssessmentScanSessionStore;
use App\Services\CompetencyResolver;
use App\Services\PhpOfficeDocumentRenderer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AssessmentController extends Controller
{
    public function __construct(private readonly CompetencyResolver $competencyResolver) {}

    public function create(TeachingGroup $teachingGroup)
    {
        $this->authorize('update', $teachingGroup);

        return Inertia::render('Assessments/Form', $this->formProps($teachingGroup, null, request('return_tab', 'assessments'), request('return_to', 'group')));
    }

    public function edit(TeachingGroup $teachingGroup, Assessment $assessment)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);

        $assessment->load('tasks.levels', 'tasks.competency');

        return Inertia::render('Assessments/Form', $this->formProps($teachingGroup, $assessment, request('return_tab', 'assessments'), request('return_to', 'group')));
    }

    public function download(TeachingGroup $teachingGroup, Assessment $assessment, PhpOfficeDocumentRenderer $renderer): Response
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);

        $assessment->load(['tasks.expectations', 'tasks.levels']);
        $teachingGroup->loadMissing(['school', 'schoolYear']);
        $differentiated = $assessment->is_differentiated;
        $title = $assessment->title;
        if ($differentiated) {
            $title .= ' (M)';
        }

        $document = new AssessmentDocument(
            title: $title,
            tasks: $assessment->tasks->filter(fn (AssessmentTask $task): bool => $task->levels->isEmpty() || $task->levels->contains('level', 'M'))->map(fn (AssessmentTask $task): array => [
                'task_id' => (string) $task->getKey(),
                'title' => $task->title,
                'task_type' => $task->task_type,
                'content' => $task->content ?? [],
                'max_points' => $task->expectations->isNotEmpty()
                    ? $task->expectations->sum(fn ($expectation): int => (int) $expectation->points * (int) ($expectation->repetitions ?: 1))
                    : $task->max_points,
                'levels' => $task->levels->pluck('level')->values()->all() ?: collect([$task->level])->filter()->values()->all(),
            ])->values()->all(),
            gradeLevel: (string) ($teachingGroup->gradeLevels()->orderBy('id')->value('grade_level') ?? ''),
            metadata: [
                'author' => auth()->user()?->name,
                'assessment_id' => (string) $assessment->getKey(),
                'level' => $differentiated ? 'M' : null,
                'roo_version' => config('app.version', '0.1.0'),
                'year' => now()->year,
                'school' => $teachingGroup->school?->name,
                'school_year' => $teachingGroup->schoolYear?->name,
                'group' => $teachingGroup->name,
                'footer_title' => $assessment->title.($differentiated ? ' (M)' : ''),
                'date' => $assessment->assessed_on?->format('d.m.Y'),
            ],
        );
        $contents = $renderer->render($document, DocumentOutputFormat::ODT);
        $filename = $this->downloadFilename($assessment->title);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.oasis.opendocument.text',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.odt"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function assess(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, AssessmentPdfScanner $scanner)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);

        $data = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);
        $scan = $scanner->scan($data['pdf']->getRealPath());

        return Inertia::render('Assessment/Assess', [
            'group' => $teachingGroup,
            'assessment' => $assessment,
            'scan' => $scan->toArray(),
        ]);
    }

    public function createScanSession(AssessmentScanSessionRequest $request, TeachingGroup $teachingGroup, Assessment $assessment, AssessmentScanSessionStore $sessions)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);

        return response()->json($sessions->create($assessment), 201);
    }

    public function storeScanFragment(AssessmentScanFragmentRequest $request, TeachingGroup $teachingGroup, Assessment $assessment, string $session, AssessmentScanSessionStore $sessions)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        $manifest = $sessions->manifest($session);
        abort_unless($manifest !== null && $manifest['assessment_id'] === (string) $assessment->getKey(), 404);

        $data = $request->validated();
        $result = $sessions->storeFragment($session, collect($data)->except('fragment')->all(), $data['fragment']);

        return response()->json($result, 201);
    }

    public function deleteScanSession(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, string $session, AssessmentScanSessionStore $sessions)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        $manifest = $sessions->manifest($session);
        abort_unless($manifest !== null && $manifest['assessment_id'] === (string) $assessment->getKey(), 404);
        $sessions->delete($session);

        return response()->noContent();
    }

    public function completeScanSession(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, string $session, AssessmentScanSessionStore $sessions)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        $manifest = $sessions->manifest($session);
        abort_unless($manifest !== null && $manifest['assessment_id'] === (string) $assessment->getKey(), 404);
        $data = $request->validate([
            'scan' => ['required', 'array'],
            'scan.booklets' => ['present', 'array'],
            'scan.warnings' => ['present', 'array'],
            'fragment_ids' => ['present', 'array'],
        ]);
        $sessions->complete($session, $data['scan'], $data['fragment_ids']);

        return response()->json([
            'redirect_url' => route('assessments.scan-sessions.result', [$teachingGroup, $assessment, $session]),
        ]);
    }

    public function scanSessionResult(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, string $session, AssessmentScanSessionStore $sessions)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        $manifest = $sessions->manifest($session);
        abort_unless($manifest !== null && $manifest['assessment_id'] === (string) $assessment->getKey() && ($manifest['status'] ?? null) === 'completed', 404);

        $fragments = collect($sessions->fragments($session))->map(fn (array $fragment): array => [
            'fragment_id' => $fragment['fragment_id'],
            'booklet' => $fragment['metadata']['booklet'],
            'task_id' => $fragment['metadata']['task_id'],
            'page' => $fragment['metadata']['page'],
            'url' => route('assessments.scan-sessions.fragments.show', [$teachingGroup, $assessment, $session, $fragment['fragment_id']]),
        ])->values()->all();

        return Inertia::render('Assessment/Assess', [
            'group' => $teachingGroup,
            'assessment' => $assessment,
            'scan' => $manifest['scan'],
            'fragment_ids' => $manifest['fragment_ids'],
            'fragments' => $fragments,
        ]);
    }

    public function showScanFragment(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, string $session, string $fragment, AssessmentScanSessionStore $sessions)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        $manifest = $sessions->manifest($session);
        abort_unless($manifest !== null && $manifest['assessment_id'] === (string) $assessment->getKey(), 404);
        $stored = $sessions->fragment($session, $fragment);
        abort_unless($stored !== null && Storage::disk('temporary')->exists($stored['path']), 404);

        return response()->file(Storage::disk('temporary')->path($stored['path']), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(Request $request, TeachingGroup $teachingGroup)
    {
        $this->authorize('update', $teachingGroup);
        $data = $this->validatedAssessment($request);
        DB::transaction(function () use ($data, $teachingGroup): void {
            $assessment = $teachingGroup->assessments()->create(['organization_id' => $teachingGroup->organization_id, 'report_period_id' => $data['report_period_id'] ?? null, 'title' => $data['title'], 'assessed_on' => $data['assessed_on'] ?? null, 'notes' => $data['notes'] ?? null]);
            if (array_key_exists('tasks', $data)) {
                $this->syncTasks($assessment, $teachingGroup, $data['tasks'] ?? []);
            }
        });

        return $this->redirectAfterSave($teachingGroup, $data)->with('success', 'Lernstandserhebung wurde angelegt.');
    }

    public function update(Request $request, TeachingGroup $teachingGroup, Assessment $assessment)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        $data = $this->validatedAssessment($request);
        DB::transaction(function () use ($data, $assessment, $teachingGroup): void {
            $assessment->update(collect($data)->only(['report_period_id', 'title', 'assessed_on', 'notes'])->all());
            if (array_key_exists('tasks', $data)) {
                $assessment->tasks()->sync([]);
                $this->syncTasks($assessment, $teachingGroup, $data['tasks'] ?? []);
            }
        });

        return $this->redirectAfterSave($teachingGroup, $data)->with('success', 'Lernstandserhebung wurde gespeichert.');
    }

    public function updateResult(Request $request, TeachingGroup $teachingGroup, AssessmentTask $assessmentTask)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessmentTask->assessments()->where('teaching_group_id', $teachingGroup->id)->exists(), 404);
        $data = $request->validate(['student_id' => ['required', 'integer'], 'points' => ['nullable', 'integer', 'min:0'], 'level' => ['nullable', 'in:G,M,E'], 'numeric_grade' => ['nullable', 'regex:/^[1-6](?:[+-])?$/'], 'note' => ['nullable', 'string', 'max:2000']]);
        abort_unless($teachingGroup->students()->whereKey($data['student_id'])->exists(), 422);
        StudentAssessmentResult::updateOrCreate(['assessment_task_id' => $assessmentTask->id, 'student_id' => $data['student_id']], collect($data)->except('student_id')->all());

        return back()->with('success', 'Ergebnis wurde gespeichert.');
    }

    private function formProps(TeachingGroup $teachingGroup, ?Assessment $assessment = null, string $returnTab = 'assessments', string $returnTo = 'group'): array
    {
        $slot = $assessment?->scheduleSlots()->where('status', 'lse')->orderBy('date')->orderBy('period_number')->first();
        $assessmentDate = ($slot?->date ?? $assessment?->assessed_on)?->toImmutable();
        $assessmentTasks = $this->assessmentTasksForWindow($teachingGroup, $assessmentDate, $assessment);
        $assessmentCompetencies = $this->assessmentCompetenciesForWindow($teachingGroup, $assessmentDate);

        return ['group' => $teachingGroup, 'assessment' => $assessment, 'slot' => $slot ? ['date' => $slot->date->toDateString(), 'period_number' => $slot->period_number] : null, 'assessmentTasks' => $assessmentTasks, 'assessmentCompetencies' => $assessmentCompetencies, 'returnTab' => $returnTab, 'returnTo' => in_array($returnTo, ['group', 'year-plan'], true) ? $returnTo : 'group'];
    }

    private function assessmentCompetenciesForWindow(TeachingGroup $teachingGroup, ?CarbonInterface $assessmentDate): array
    {
        if (! $assessmentDate) {
            return [];
        }

        $assessmentDate = $assessmentDate->toImmutable();
        $previousLseDate = $teachingGroup->scheduleSlots()
            ->where('status', 'lse')
            ->whereDate('date', '<', $assessmentDate->toDateString())
            ->orderByDesc('date')
            ->value('date');
        $fromDate = $previousLseDate
            ? CarbonImmutable::parse($previousLseDate)->addDay()
            : $teachingGroup->schoolYear->starts_on->toImmutable();

        $slotFilter = fn ($query) => $query
            ->where('teaching_group_id', $teachingGroup->id)
            ->whereDate('date', '>=', $fromDate->toDateString())
            ->whereDate('date', '<=', $assessmentDate->toDateString());

        return $teachingGroup->teachingUnits()
            ->with(['lessons' => fn ($query) => $query
                ->whereHas('scheduledLessons.slot', $slotFilter)
                ->with(['competencies.educationPlanCompetency.area', 'competencies.educationPlanCompetency.variants', 'competencies.curriculumCompetency.educationPlanCompetency.area', 'competencies.curriculumCompetency.educationPlanCompetency.variants'])])
            ->get()
            ->flatMap->lessons
            ->flatMap->competencies
            ->filter(fn ($competency) => $competency->educationPlanCompetency?->area?->kind === 'content'
                || $competency->curriculumCompetency?->competency_kind === 'content')
            ->map(function ($competency): array {
                $educationPlanCompetency = $competency->educationPlanCompetency
                    ?? $competency->curriculumCompetency?->educationPlanCompetency;

                return [
                    'key' => $educationPlanCompetency
                        ? 'education-plan-'.$educationPlanCompetency->id
                        : 'teaching-unit-'.$competency->id,
                    'title' => $this->resolvedCompetencyText($educationPlanCompetency ?? $competency),
                ];
            })
            ->unique('key')
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function resolvedCompetencyText(?Model $competency): ?string
    {
        if (! $competency || ! $this->competencyResolver->textOnly($competency)) {
            return null;
        }

        return $this->competencyResolver->duKannst($competency);
    }

    private function assessmentTasksForWindow(TeachingGroup $teachingGroup, ?CarbonInterface $assessmentDate, ?Assessment $assessment): array
    {
        if (! $assessmentDate) {
            return [];
        }

        $assessmentDate = $assessmentDate->toImmutable();

        $previousLseDate = $teachingGroup->scheduleSlots()
            ->where('status', 'lse')
            ->whereDate('date', '<', $assessmentDate->toDateString())
            ->orderByDesc('date')
            ->value('date');
        $fromDate = $previousLseDate
            ? CarbonImmutable::parse($previousLseDate)->addDay()
            : $teachingGroup->schoolYear->starts_on->toImmutable();

        $slotFilter = fn ($query) => $query
            ->where('teaching_group_id', $teachingGroup->id)
            ->whereDate('date', '>=', $fromDate->toDateString())
            ->whereDate('date', '<=', $assessmentDate->toDateString());

        $tasks = AssessmentTask::query()
            ->where('organization_id', $teachingGroup->organization_id)
            ->whereHas('lessons', fn ($query) => $query
                ->whereHas('unit', fn ($unitQuery) => $unitQuery->where('teaching_group_id', $teachingGroup->id))
                ->whereHas('scheduledLessons.slot', $slotFilter))
            ->with([
                'levels', 'expectations', 'competency.unit', 'competency.educationPlanCompetency.variants', 'educationPlanCompetency.area', 'educationPlanCompetency.variants',
                'lessons' => fn ($query) => $query
                    ->whereHas('unit', fn ($unitQuery) => $unitQuery->where('teaching_group_id', $teachingGroup->id))
                    ->with(['scheduledLessons' => fn ($scheduledQuery) => $scheduledQuery->whereHas('slot', $slotFilter)->with('slot:id,date')]),
            ])
            ->orderBy('title')
            ->get();

        $selectedTaskIds = $assessment?->tasks()->pluck('assessment_tasks.id')->all() ?? [];
        $windowTaskIds = $tasks->pluck('id')->all();

        if ($assessment) {
            $tasks = $tasks->merge($assessment->tasks()->with(['levels', 'expectations', 'competency.unit', 'competency.educationPlanCompetency.variants', 'educationPlanCompetency.area', 'educationPlanCompetency.variants'])->get())->unique('id')->sortBy('title')->values();
        }

        return $tasks->map(function (AssessmentTask $task) use ($selectedTaskIds, $windowTaskIds): array {
            $educationPlanCompetency = $task->educationPlanCompetency ?? $task->competency?->educationPlanCompetency;
            $competencyId = $task->teaching_unit_competency_id ?? $educationPlanCompetency?->id;

            return [
                'id' => $task->id,
                'title' => $task->title,
                'max_points' => $task->expectations->isNotEmpty()
                    ? $task->expectations->sum(fn ($expectation): int => (int) $expectation->points * (int) ($expectation->repetitions ?: 1))
                    : $task->max_points,
                'levels' => $task->levels->pluck('level')->values()->all() ?: collect([$task->level])->filter()->values()->all(),
                'competency_id' => $competencyId,
                'education_plan_competency_id' => $educationPlanCompetency?->id,
                'competency_key' => $educationPlanCompetency
                    ? 'education-plan-'.$educationPlanCompetency->id
                    : ($task->teaching_unit_competency_id
                        ? 'teaching-unit-'.$task->teaching_unit_competency_id
                        : 'text-'.md5((string) $task->competency?->local_wording)),
                'competency' => $this->resolvedCompetencyText($educationPlanCompetency ?? $task->competency),
                'edit_url' => route('resources.library.assessment-tasks.edit', $task->id),
                'checked' => in_array($task->id, $selectedTaskIds, true),
                'source' => in_array($task->id, $windowTaskIds, true) ? 'hours' : 'manual',
                'date' => $task->lessons->flatMap->scheduledLessons->map(fn ($scheduledLesson) => $scheduledLesson->slot?->date?->toDateString())->filter()->sort()->first(),
            ];
        })->all();
    }

    private function redirectAfterSave(TeachingGroup $teachingGroup, array $data)
    {
        if (($data['return_to'] ?? 'group') === 'year-plan') {
            return redirect()->route('year-plans.show', $teachingGroup);
        }

        return redirect()->route('teaching-groups.show', ['teachingGroup' => $teachingGroup, 'tab' => $data['return_tab'] ?? 'assessments']);
    }

    private function downloadFilename(string $title): string
    {
        $filename = preg_replace('/[^\pL\pN._-]+/u', '_', trim($title)) ?: 'lernstandserhebung';

        return trim($filename, '._-') ?: 'lernstandserhebung';
    }

    private function validatedAssessment(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:255'], 'report_period_id' => ['nullable', 'integer'], 'assessed_on' => ['nullable', 'date'], 'return_tab' => ['nullable', 'in:assessments'], 'return_to' => ['nullable', 'in:group,year-plan'], 'notes' => ['nullable', 'string'], 'tasks' => ['sometimes', 'array'], 'tasks.*.task_id' => ['nullable', 'integer'], 'tasks.*.title' => ['nullable', 'string', 'max:255'], 'tasks.*.solution' => ['nullable', 'string'], 'tasks.*.max_points' => ['nullable', 'integer', 'min:1'], 'tasks.*.competency_id' => ['nullable', 'integer'], 'tasks.*.level' => ['nullable', 'in:G,M,E'], 'tasks.*.levels' => ['sometimes', 'array'], 'tasks.*.levels.*' => ['in:G,M,E']]);
    }

    private function syncTasks(Assessment $assessment, TeachingGroup $teachingGroup, array $tasks): void
    {
        $competencyIds = $teachingGroup->teachingUnits()->with('competencies:id,teaching_unit_id')->get()->flatMap->competencies->pluck('id');
        $attach = [];
        foreach ($tasks as $position => $task) {
            $levels = collect($task['levels'] ?? (($task['level'] ?? null) ? [$task['level']] : []))->unique()->values();
            if ($this->isDifferentiated($teachingGroup)) {
                abort_unless($levels->isNotEmpty(), 422, 'Für differenzierte Gruppen muss mindestens ein G/M/E-Niveau gewählt werden.');
            }
            if (! empty($task['task_id'])) {
                $model = AssessmentTask::where('organization_id', $teachingGroup->organization_id)->whereKey($task['task_id'])->firstOrFail();
                $assignedToGroup = $model->lessons()->whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->exists();
                abort_unless($competencyIds->contains($model->teaching_unit_competency_id) || $assignedToGroup, 422);
                if ($levels->isNotEmpty()) {
                    $model->levels()->delete();
                    $model->levels()->createMany($levels->map(fn ($level) => ['level' => $level])->all());
                    $model->update(['level' => $levels->first()]);
                }
            } else {
                abort_unless(! empty($task['competency_id']) && $competencyIds->contains($task['competency_id']), 422);
                $model = AssessmentTask::create(['organization_id' => $teachingGroup->organization_id, 'teaching_unit_competency_id' => $task['competency_id'], 'title' => $task['title'], 'solution' => $task['solution'] ?? null, 'max_points' => $task['max_points'] ?? null, 'level' => $levels->first()]);
                $model->levels()->delete();
                $model->levels()->createMany($levels->map(fn ($level) => ['level' => $level])->all());
            }
            $attach[$model->id] = ['position' => $position + 1];
        }
        $assessment->tasks()->sync($attach);
    }

    private function isDifferentiated(TeachingGroup $teachingGroup): bool
    {
        return $teachingGroup->gradeLevels()->pluck('grade_level')->contains(fn ($level) => preg_match('/(?:^|[\s\/])(?:G|M|E)(?:$|[\s\/])/', strtoupper((string) $level)) === 1);
    }
}
