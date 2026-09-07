<?php

namespace App\Http\Controllers;

use App\Documents\AssessmentDocument;
use App\Documents\AssessmentResultDocument;
use App\Documents\DocumentOutputFormat;
use App\Http\Requests\AssessmentBookletAssignmentRequest;
use App\Http\Requests\AssessmentScanFragmentRequest;
use App\Http\Requests\AssessmentScanPageRequest;
use App\Http\Requests\AssessmentScanSessionRequest;
use App\Http\Requests\AssessmentTaskReviewRequest;
use App\Models\Assessment;
use App\Models\AssessmentBooklet;
use App\Models\AssessmentBookletFragment;
use App\Models\AssessmentScanMaterialization;
use App\Models\AssessmentStudentResultStatus;
use App\Models\AssessmentTask;
use App\Models\EducationPlanCompetency;
use App\Models\Student;
use App\Models\StudentAssessmentResult;
use App\Models\TeachingGroup;
use App\Services\AssessmentEvaluation\AssignAssessmentBooklet;
use App\Services\AssessmentEvaluation\MaterializeAssessmentScan;
use App\Services\AssessmentEvaluation\SaveAssessmentTaskReview;
use App\Services\AssessmentEvaluation\SentenceBuilderWordOrder;
use App\Services\AssessmentScan\AssessmentPdfScanner;
use App\Services\AssessmentScan\AssessmentScanPageProcessor;
use App\Services\AssessmentScan\AssessmentScanSessionStore;
use App\Services\CompetencyResolver;
use App\Services\PhpOfficeDocumentRenderer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    public function download(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, PhpOfficeDocumentRenderer $renderer): Response
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);

        $assessment->load(['tasks.expectations', 'tasks.levels', 'tasks.images.resource']);
        $teachingGroup->loadMissing(['school', 'schoolYear']);
        $options = $request->validate([
            'level' => ['nullable', 'in:G,M,E'],
            'format' => ['nullable', 'in:odt,docx'],
            'template' => ['nullable', 'in:primary-school-lower-secondary'],
        ]);
        $differentiated = $assessment->is_differentiated;
        $level = $differentiated ? ($options['level'] ?? 'M') : null;
        $format = DocumentOutputFormat::from($options['format'] ?? 'odt');
        $title = $assessment->title;
        if ($differentiated) {
            $title .= " ({$level})";
        }

        $document = new AssessmentDocument(
            title: $title,
            tasks: $assessment->tasks->reject(fn (AssessmentTask $task): bool => $task->task_type === 'expectation_list')->filter(fn (AssessmentTask $task): bool => $level === null || $task->levels->isEmpty() || $task->levels->contains('level', $level))->map(fn (AssessmentTask $task): array => [
                'task_id' => (string) $task->getKey(),
                'title' => $task->title,
                'task_type' => $task->task_type,
                'content' => $this->downloadTaskContent($task),
                'max_points' => $task->maximumPoints(),
                'levels' => $task->levels->pluck('level')->values()->all() ?: collect([$task->level])->filter()->values()->all(),
            ])->values()->all(),
            gradeLevel: (string) ($teachingGroup->gradeLevels()->orderBy('id')->value('grade_level') ?? ''),
            metadata: [
                'author' => auth()->user()?->name,
                'assessment_id' => (string) $assessment->getKey(),
                'level' => $level,
                'roo_version' => config('app.version', '0.1.0'),
                'year' => now()->year,
                'school' => $teachingGroup->school?->name,
                'school_year' => $teachingGroup->schoolYear?->name,
                'group' => $teachingGroup->name,
                'footer_title' => $assessment->title.($differentiated ? " ({$level})" : ''),
                'date' => $assessment->assessed_on?->format('d.m.Y'),
            ],
        );
        $contents = $renderer->render($document, $format);
        $filename = $this->downloadFilename($assessment->title);
        $mimeType = $format === DocumentOutputFormat::DOCX
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/vnd.oasis.opendocument.text';

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$format->value.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function resultReport(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, PhpOfficeDocumentRenderer $renderer): Response
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);
        $options = $request->validate([
            'student' => ['nullable', 'in:all,'.$teachingGroup->students()->pluck('students.id')->implode(',')],
            'format' => ['nullable', 'in:odt,docx'],
            'template' => ['nullable', 'in:default'],
        ]);
        $studentId = ($options['student'] ?? 'all') === 'all' ? null : (int) $options['student'];
        $students = $teachingGroup->students()->orderBy('last_name')->orderBy('first_name')->get();
        $teachingGroup->loadMissing('school');
        if ($studentId !== null) {
            $students = $students->where('id', $studentId)->values();
        }

        $assessment->load(['tasks.results', 'tasks.levels', 'tasks.expectations', 'tasks.reviews.booklet.student', 'tasks.reviews.items', 'tasks.competency', 'tasks.educationPlanCompetency.variants.level']);
        $reports = $students->map(fn (Student $student): array => $this->resultReportForStudent($assessment, $teachingGroup, $student))->all();
        $format = DocumentOutputFormat::from($options['format'] ?? 'odt');
        $contents = $renderer->render(new AssessmentResultDocument($assessment->title, $reports), $format);
        $filename = $this->downloadFilename($assessment->title).'_Ergebnisse';
        $mimeType = $format === DocumentOutputFormat::DOCX
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/vnd.oasis.opendocument.text';

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$format->value.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function resultReportForStudent(Assessment $assessment, TeachingGroup $teachingGroup, Student $student): array
    {
        $tasks = $assessment->tasks->reject(fn (AssessmentTask $task): bool => $task->task_type === 'expectation_list');
        $taskReports = $tasks->map(function (AssessmentTask $task) use ($student, $assessment): array {
            $result = $task->results->first(fn ($result): bool => (int) $result->student_id === (int) $student->id && ((int) ($result->assessment_id ?? $assessment->id) === (int) $assessment->id));
            $maxPoints = $task->maximumPoints();
            $reviewItems = $task->reviews
                ->filter(fn ($review): bool => (int) $review->booklet?->assessment_id === (int) $assessment->id && (int) $review->booklet?->student_id === (int) $student->id)
                ->flatMap->items
                ->groupBy('assessment_task_expectation_id');
            $level = $result?->level ?: ($task->levels->count() === 1 ? $task->levels->first()->level : 'M');

            return [
                'title' => $task->title,
                'points' => $result?->points,
                'max_points' => $maxPoints,
                'points_label' => $result?->points === null || $maxPoints === null ? '– / '.($maxPoints ?? '–') : $result->points.' / '.$maxPoints,
                'percentage' => round_percentage($result?->points === null || ! $maxPoints ? 0 : min(100, max(0, ((float) $result->points / $maxPoints) * 100))),
                'competency' => $this->resultCompetencyText($task, $level),
                'weight' => (int) ($task->pivot->weight ?? 50),
                'grade' => $result?->numeric_grade,
                'level' => $level,
                'expectations' => $task->expectations->map(function ($expectation) use ($reviewItems, $result, $task): array {
                    $maximum = (float) $expectation->points * max(1, (int) ($expectation->repetitions ?: 1));
                    $awarded = $reviewItems->get($expectation->id)?->sum(fn ($item): float => (float) $item->awarded_points);
                    if ($awarded === null && $task->expectations->count() === 1) {
                        $awarded = $result?->points === null ? null : (float) $result->points;
                    }

                    return [
                        'text' => $expectation->text,
                        'points' => $awarded,
                        'max_points' => $maximum,
                    ];
                })->values()->all(),
            ];
        })->values();
        $competencies = $taskReports->groupBy('competency')->map(fn ($items, $title): array => [
            'title' => $title,
            'percentage' => round_percentage($items->sum(fn (array $item): float => $item['percentage'] * $item['weight']) / max(1, $items->sum('weight'))),
        ])->values()->all();
        $maxTotal = $taskReports->sum('max_points');
        $pointsTotal = $taskReports->sum(fn (array $task): float => (float) ($task['points'] ?? 0));
        $levels = $taskReports->filter(fn (array $task): bool => filled($task['grade']))->pluck('grade');
        $studentLevels = $tasks->flatMap(fn (AssessmentTask $task) => $task->levels->pluck('level')->filter())->unique()->implode('/');

        return [
            'title' => $assessment->title,
            'student_name' => trim($student->first_name.' '.$student->last_name),
            'level' => $studentLevels ?: ($assessment->is_differentiated ? 'M' : ''),
            'tasks' => $taskReports->all(),
            'competencies' => $competencies,
            'percentage' => round_percentage($maxTotal ? $pointsTotal / $maxTotal * 100 : 0),
            'grade' => percentage_to_grade($maxTotal ? $pointsTotal / $maxTotal * 100 : 0),
            'place' => $teachingGroup->school?->name ?: '',
            'date' => ($assessment->assessed_on ?: now())->format('d.m.Y'),
            'author' => auth()->user()?->name ?: '',
        ];
    }

    private function resultCompetencyText(AssessmentTask $task, string $level): string
    {
        $competency = $task->educationPlanCompetency;
        if ($competency !== null) {
            $variant = $competency->variants->first(fn ($variant): bool => strtoupper((string) $variant->level?->external_identifier) === strtoupper($level));
            $text = $variant?->text ?: $competency->text;

            return trim((string) preg_replace('/^\s*(?:Du kannst\s+)?/iu', 'Du kannst ', $this->withoutCompetencyIdentifier($text)));
        }

        return $this->withoutCompetencyIdentifier($task->competency?->local_wording ?: 'Ohne Kompetenzzuordnung');
    }

    private function withoutCompetencyIdentifier(?string $text): string
    {
        return trim((string) preg_replace('/^\s*\d+(?:\.\d+){2,4}(?:\s*\(\d+\))?\s*[-–:]?\s*/u', '', (string) $text));
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

    public function evaluation(TeachingGroup $teachingGroup, Assessment $assessment)
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);

        $assessment->load([
            'tasks.expectations',
            'tasks.levels',
            'tasks.results',
            'tasks.educationPlanCompetency.variants',
            'tasks.images.resource',
            'booklets.student',
            'booklets.fragments',
            'booklets.reviews.items',
            'booklets.reviews.options',
            'scanMaterializations',
        ]);
        $students = $teachingGroup->students()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['students.id', 'students.first_name', 'students.last_name', 'students.class_name']);
        $booklets = $assessment->booklets->sortBy('number')->values();
        $resultStatuses = AssessmentStudentResultStatus::query()
            ->where('assessment_id', $assessment->id)
            ->get()
            ->keyBy('student_id');
        $levelOrder = ['G' => 1, 'M' => 2, 'E' => 3];
        $results = $students->map(function (Student $student) use ($assessment, $levelOrder, $resultStatuses): array {
            $status = $resultStatuses->get($student->id)?->status;
            $competencies = collect();
            $studentLevels = collect();
            $hasResult = false;
            foreach ($assessment->tasks as $task) {
                $result = $task->results->firstWhere('student_id', $student->id);
                if ($result !== null && $result->points !== null && $task->maximumPoints()) {
                    $hasResult = true;
                } elseif ($status !== 'missing' || ! $task->maximumPoints()) {
                    continue;
                }
                $taskLevels = $task->levels->pluck('level')->filter()->values();
                if ($taskLevels->isEmpty() && filled($task->level)) {
                    $taskLevels = collect([$task->level]);
                }
                $studentLevel = $result?->level ?? ($taskLevels->count() === 1 ? $taskLevels->first() : null);
                if ($studentLevel !== null) {
                    $studentLevels->push($studentLevel);
                }
                $competency = $task->educationPlanCompetency;
                $key = $competency ? 'education-plan-'.$competency->id : 'task-'.$task->id;
                $group = $competencies->get($key, ['key' => $key, 'title' => $competency ? $this->resolvedCompetencyText($competency) : 'Ohne Kompetenzzuordnung', 'tasks' => []]);
                $percentage = $result === null ? 0 : min(100, max(0, ((float) $result->points / $task->maximumPoints()) * 100));
                $group['tasks'][] = ['title' => $task->title, 'percentage' => round($percentage, 2), 'weight' => (int) ($task->pivot->weight ?? 50)];
                $competencies->put($key, $group);
            }
            $competencies = $competencies->map(function (array $group): array {
                $weightTotal = collect($group['tasks'])->sum('weight');
                $group['percentage'] = round($weightTotal > 0 ? collect($group['tasks'])->sum(fn (array $task): float => $task['percentage'] * $task['weight']) / $weightTotal : collect($group['tasks'])->avg('percentage'), 2);

                return $group;
            })->values();

            return [
                'student_id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'level' => $studentLevels->unique()->sortBy(fn ($level) => $levelOrder[$level] ?? 99)->implode('/'),
                'has_results' => $hasResult,
                'competencies' => $hasResult || $status === 'missing' ? $competencies : [],
                'result_status' => $hasResult ? null : $status,
                'needs_result_decision' => ! $hasResult && $status === null,
            ];
        })->values();
        $scanWarnings = $assessment->scanMaterializations
            ->flatMap(fn (AssessmentScanMaterialization $materialization): array => $materialization->warnings ?? [])
            ->unique()
            ->values();
        $taskFragments = $booklets
            ->where('status', 'open')
            ->flatMap(function (AssessmentBooklet $booklet) use ($teachingGroup, $assessment): array {
                return $booklet->fragments->map(function (AssessmentBookletFragment $fragment) use ($booklet, $teachingGroup, $assessment): array {
                    $review = $booklet->reviews->firstWhere('assessment_task_id', $fragment->assessment_task_id);

                    return [
                        'id' => $fragment->id,
                        'booklet_id' => $booklet->id,
                        'assessment_task_id' => $fragment->assessment_task_id,
                        'image_url' => route('assessments.booklet-fragments.show', [$teachingGroup, $assessment, $fragment]),
                        'page' => $fragment->page,
                        'end_page' => $fragment->end_page,
                        'start_y_cm' => $fragment->start_y_cm,
                        'end_y_cm' => $fragment->end_y_cm,
                        'review' => $review === null ? null : [
                            'id' => $review->id,
                            'extra_points' => $review->extra_points,
                            'extra_note' => $review->extra_note,
                            'sorting_sequence' => $review->sorting_sequence,
                            'student_sentence' => $review->student_sentence,
                            'items' => $review->items->map(fn ($item): array => [
                                'expectation_id' => $item->assessment_task_expectation_id,
                                'occurrence' => $item->occurrence,
                                'awarded_points' => $item->awarded_points,
                                'note' => $item->note,
                            ])->values(),
                            'options' => $review->options->map(fn ($option): array => [
                                'option_id' => $option->option_id,
                                'selected' => $option->selected,
                            ])->values(),
                        ],
                    ];
                })->all();
            })
            ->shuffle()
            ->values();
        $taskFragments = $taskFragments->concat($booklets
            ->where('status', 'open')
            ->where('source', 'manual')
            ->flatMap(fn (AssessmentBooklet $booklet): array => $assessment->tasks->map(fn (AssessmentTask $task): array => [
                'id' => "manual-{$booklet->id}-{$task->id}",
                'booklet_id' => $booklet->id,
                'assessment_task_id' => $task->id,
                'image_url' => null,
                'student_name' => $booklet->student?->last_name.', '.$booklet->student?->first_name,
                'page' => null,
                'end_page' => null,
                'start_y_cm' => null,
                'end_y_cm' => null,
                'review' => null,
            ])->all()))->shuffle()->values();
        $bookletNumbers = $booklets->mapWithKeys(fn (AssessmentBooklet $booklet): array => [$booklet->id => $booklet->number]);

        return Inertia::render('Assessment/Assess', [
            'group' => ['id' => $teachingGroup->id, 'name' => $teachingGroup->name],
            'assessment' => ['id' => $assessment->id, 'title' => $assessment->title],
            'scan' => [
                'booklets' => $booklets->map(fn (AssessmentBooklet $booklet): array => [
                    'number' => $booklet->number,
                    'start_page' => $booklet->fragments->min('page') ?? 1,
                    'markers' => [],
                ])->values(),
                'warnings' => $scanWarnings,
            ],
            'fragments' => $taskFragments->map(fn (array $fragment): array => [
                'fragment_id' => $fragment['id'],
                'booklet' => $bookletNumbers->get($fragment['booklet_id']),
                'task_id' => $fragment['assessment_task_id'],
                'page' => $fragment['page'],
                'url' => $fragment['image_url'],
            ])->values(),
            'students' => $students->map(fn ($student): array => [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'class_name' => $student->class_name,
            ])->values(),
            'tasks' => $assessment->tasks->map(fn (AssessmentTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'task_type' => $task->task_type,
                'max_points' => $task->maximumPoints(),
                'checkbox_scoring_mode' => $task->task_type === 'checkbox' ? $task->checkboxScoringMode() : null,
                'content' => [
                    'subtasks' => data_get($task->content, 'subtasks', []),
                    'options' => data_get($task->content, 'options', []),
                    'categories' => data_get($task->content, 'categories', []),
                    'rows' => data_get($task->content, 'rows', []),
                    'questions' => data_get($task->content, 'questions', []),
                    'sorting_order' => data_get($task->content, 'sorting_order', []),
                    'points_per_sentence' => data_get($task->content, 'points_per_sentence'),
                    'words' => data_get($task->content, 'words', ''),
                    'shuffled_words' => data_get($task->content, 'shuffled_words', []),
                    'solution' => $task->solution,
                    'points_per_correct_answer' => in_array($task->task_type, ['checkbox', 'image_matching', 'image_labeling'], true) ? $task->pointsPerCorrectAnswer() : data_get($task->content, 'points_per_correct_answer'),
                    'matching_scoring_mode' => $task->task_type === 'matching_table' ? data_get($task->content, 'matching_scoring_mode', 'per_category') : null,
                    'checkbox_scoring_mode' => $task->task_type === 'checkbox' ? $task->checkboxScoringMode() : null,
                    'image_width_cm' => in_array($task->task_type, ['image_matching', 'image_answer_table'], true) ? $task->imageWidthCm() : null,
                    'images' => $task->images->map(fn ($image): array => ['identifier' => $image->identifier, 'path' => Storage::disk('local')->path($image->resource->storage_path), 'label' => $image->label, 'answer' => $image->answer])->values()->all(),
                ],
                'images' => $task->images->map(fn ($image): array => ['id' => $image->identifier, 'label' => $image->label, 'answer' => $image->answer, 'image_url' => route('resources.library.files.preview', $image->resource)])->values(),
                'label_options' => $task->images->flatMap->labels->map(fn ($label): array => ['id' => (string) $label->id, 'text' => $label->solution])->values(),
                'evaluation_mode' => data_get($task->content, 'evaluation_mode'),
                'expectations' => $task->expectations->map(function ($expectation) use ($task): array {
                    $subtask = collect(data_get($task->content, 'subtasks', []))->firstWhere('key', $expectation->subtask_key);
                    $image = $task->task_type === 'image_answer_table'
                        ? $task->images->firstWhere('identifier', data_get($subtask, 'image_identifier'))
                        : null;

                    return [
                        'id' => $expectation->id,
                        'subtask_key' => $expectation->subtask_key,
                        'text' => $expectation->text,
                        'points' => $expectation->points,
                        'repetitions' => $expectation->repetitions,
                        'thumbnail' => $image?->resource === null ? null : route('resources.library.files.preview', $image->resource),
                        'thumbnail_alt' => $image?->resource?->original_name,
                    ];
                })->values(),
            ])->values(),
            'booklets' => $booklets->map(fn (AssessmentBooklet $booklet): array => [
                'id' => $booklet->id,
                'number' => $booklet->number,
                'status' => $booklet->status,
                'source' => $booklet->source,
                'student_id' => $booklet->student_id,
                'name_fragment_url' => $booklet->name_fragment_path === null ? null : route('assessments.booklets.name-fragment.show', [$teachingGroup, $assessment, $booklet]),
                'fragment_count' => $booklet->fragments->count(),
                'reviewed_fragment_count' => $booklet->reviews->count(),
            ])->values(),
            'results' => $results,
            'taskFragments' => $taskFragments,
            'progress' => [
                'total_booklets' => $booklets->count(),
                'open_booklets' => $booklets->where('status', 'open')->count(),
                'discarded_booklets' => $booklets->where('status', 'discarded')->count(),
                'assigned_booklets' => $booklets->where('status', 'open')->whereNotNull('student_id')->count(),
                'unassigned_booklets' => $booklets->where('status', 'open')->whereNull('student_id')->count(),
                'reviewable_fragments' => $taskFragments->count(),
                'reviewed_fragments' => $taskFragments->filter(fn (array $fragment): bool => $fragment['review'] !== null)->count(),
            ],
        ]);
    }

    public function updateStudentResultStatus(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, Student $student)
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);
        abort_unless($teachingGroup->students()->whereKey($student->id)->exists(), 404);

        $data = $request->validate(['status' => ['required', 'in:not_evaluated,missing']]);
        AssessmentStudentResultStatus::updateOrCreate(
            ['assessment_id' => $assessment->id, 'student_id' => $student->id],
            ['status' => $data['status']],
        );

        return back()->with('success', 'Bewertungsstatus wurde gespeichert.');
    }

    public function updateBookletAssignment(AssessmentBookletAssignmentRequest $request, TeachingGroup $teachingGroup, Assessment $assessment, AssessmentBooklet $booklet, AssignAssessmentBooklet $assignment)
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);
        $this->ensureBookletBelongsToAssessment($booklet, $assessment);
        $assignment->handle($booklet, $teachingGroup, $request->validated('student_id'));

        return back()->with('success', 'Booklet-Zuordnung wurde gespeichert.');
    }

    public function storeManualBooklet(Request $request, TeachingGroup $teachingGroup, Assessment $assessment)
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);
        $data = $request->validate(['student_id' => ['required', 'integer']]);
        if (! $teachingGroup->students()->whereKey($data['student_id'])->exists()) {
            throw ValidationException::withMessages(['student_id' => 'Die ausgewählte Schüler:in gehört nicht zu dieser Unterrichtsgruppe.']);
        }

        DB::transaction(function () use ($assessment, $data): void {
            $number = ((int) $assessment->booklets()->lockForUpdate()->max('number')) + 1;
            $assessment->booklets()->create(['student_id' => $data['student_id'], 'number' => $number, 'status' => 'open', 'source' => 'manual']);
        });

        return back()->with('success', 'Manuelles Exemplar wurde angelegt.');
    }

    public function updateBookletStatus(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, AssessmentBooklet $booklet, AssignAssessmentBooklet $assignment)
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);
        $this->ensureBookletBelongsToAssessment($booklet, $assessment);
        $data = $request->validate(['status' => ['required', 'in:open,discarded']]);
        $assignment->updateStatus($booklet, $data['status']);

        return back()->with('success', $data['status'] === 'discarded' ? 'Booklet wurde verworfen.' : 'Booklet wurde wiederhergestellt.');
    }

    public function updateTaskReview(AssessmentTaskReviewRequest $request, TeachingGroup $teachingGroup, Assessment $assessment, AssessmentBooklet $booklet, AssessmentTask $assessmentTask, SaveAssessmentTaskReview $reviews)
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);
        $this->ensureBookletBelongsToAssessment($booklet, $assessment);
        abort_unless($assessment->tasks()->whereKey($assessmentTask->getKey())->exists(), 404);
        abort_unless($booklet->source === 'manual' || $booklet->fragments()->where('assessment_task_id', $assessmentTask->getKey())->exists(), 404);
        $reviews->handle($booklet, $assessmentTask, $request->validated());

        return back()->with('success', 'Aufgabenbewertung wurde gespeichert.');
    }

    public function showBookletNameFragment(TeachingGroup $teachingGroup, Assessment $assessment, AssessmentBooklet $booklet)
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);
        $this->ensureBookletBelongsToAssessment($booklet, $assessment);
        abort_unless($booklet->name_fragment_path !== null && Storage::disk('documents')->exists($booklet->name_fragment_path), 404);

        return response()->file(Storage::disk('documents')->path($booklet->name_fragment_path), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
        ])->setPrivate();
    }

    public function showBookletFragment(TeachingGroup $teachingGroup, Assessment $assessment, AssessmentBookletFragment $fragment)
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);
        $fragment->loadMissing('booklet');
        abort_unless($fragment->booklet !== null && $fragment->booklet->assessment_id === $assessment->id, 404);
        abort_unless(Storage::disk('documents')->exists($fragment->image_path), 404);

        return response()->file(Storage::disk('documents')->path($fragment->image_path), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
        ])->setPrivate();
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

    public function storeScanPage(AssessmentScanPageRequest $request, TeachingGroup $teachingGroup, Assessment $assessment, string $session, AssessmentScanSessionStore $sessions, AssessmentScanPageProcessor $processor)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        $manifest = $sessions->manifest($session);
        abort_unless($manifest !== null && $manifest['assessment_id'] === (string) $assessment->getKey(), 404);
        $data = $request->validated();
        $page = $sessions->storePage($session, $data['page'], $data['image']);
        $markers = $processor->markers($page['path'], $page['page']);
        $sessions->storePageMarkers($session, $page['page'], $markers);

        return response()->json(['page' => $page['page'], 'markers' => $markers], 201);
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

    public function completeScanSession(Request $request, TeachingGroup $teachingGroup, Assessment $assessment, string $session, AssessmentScanSessionStore $sessions, MaterializeAssessmentScan $materializer)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
        if (AssessmentScanMaterialization::query()
            ->where('assessment_id', $assessment->getKey())
            ->where('session_id', $session)
            ->exists()) {
            return response()->json([
                'redirect_url' => route('assessments.evaluation', [$teachingGroup, $assessment]),
            ]);
        }
        $manifest = $sessions->manifest($session);
        abort_unless($manifest !== null && $manifest['assessment_id'] === (string) $assessment->getKey(), 404);
        $data = $request->validate(['scan' => ['sometimes', 'array'], 'fragment_ids' => ['sometimes', 'array']]);
        if ($sessions->pages($session) !== []) {
            $sessions->complete($session, ['booklets' => [], 'warnings' => []], []);
            $materializer->handle($assessment, $session);

            return response()->json([
                'redirect_url' => route('assessments.evaluation', [$teachingGroup, $assessment]),
            ]);
        }

        $sessions->complete($session, $data['scan'] ?? ['booklets' => [], 'warnings' => []], $data['fragment_ids'] ?? []);

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
        $data = $this->validatedAssessment($request, $teachingGroup);
        DB::transaction(function () use ($data, $teachingGroup): void {
            $assessment = $teachingGroup->assessments()->create(['organization_id' => $teachingGroup->organization_id, 'report_period_id' => $data['report_period_id'] ?? null, 'grade_component_id' => $data['grade_component_id'] ?? null, 'grade_component_label' => $data['grade_component_label'] ?? null, 'title' => $data['title'], 'assessed_on' => $data['assessed_on'] ?? null, 'notes' => $data['notes'] ?? null]);
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
        $data = $this->validatedAssessment($request, $teachingGroup);
        DB::transaction(function () use ($data, $assessment, $teachingGroup): void {
            $assessment->update(collect($data)->only(['report_period_id', 'grade_component_id', 'grade_component_label', 'title', 'assessed_on', 'notes'])->all());
            if (array_key_exists('tasks', $data)) {
                $this->syncTasks($assessment, $teachingGroup, $data['tasks'] ?? []);
            }
        });

        return $this->redirectAfterSave($teachingGroup, $data)->with('success', 'Lernstandserhebung wurde gespeichert.');
    }

    public function destroy(TeachingGroup $teachingGroup, Assessment $assessment)
    {
        $this->authorize('update', $teachingGroup);
        $this->ensureAssessmentBelongsToGroup($assessment, $teachingGroup);
        $assessment->delete();

        return to_route('teaching-groups.show', ['teachingGroup' => $teachingGroup, 'tab' => 'assessments'])
            ->with('success', 'Lernstandserhebung wurde gelöscht.');
    }

    public function updateResult(Request $request, TeachingGroup $teachingGroup, AssessmentTask $assessmentTask)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($assessmentTask->assessments()->where('teaching_group_id', $teachingGroup->id)->exists(), 404);
        $data = $request->validate(['student_id' => ['required', 'integer'], 'points' => ['nullable', 'numeric'], 'level' => ['nullable', 'in:G,M,E'], 'numeric_grade' => ['nullable', 'regex:/^[1-6](?:[+-])?$/'], 'note' => ['nullable', 'string', 'max:2000']]);
        abort_unless($teachingGroup->students()->whereKey($data['student_id'])->exists(), 422);
        StudentAssessmentResult::updateOrCreate(['assessment_id' => null, 'assessment_task_id' => $assessmentTask->id, 'student_id' => $data['student_id']], collect($data)->except('student_id')->all());

        return back()->with('success', 'Ergebnis wurde gespeichert.');
    }

    private function formProps(TeachingGroup $teachingGroup, ?Assessment $assessment = null, string $returnTab = 'assessments', string $returnTo = 'group'): array
    {
        $slot = $assessment?->scheduleSlots()->where('status', 'lse')->orderBy('date')->orderBy('period_number')->first();
        $assessmentDate = ($slot?->date ?? $assessment?->assessed_on)?->toImmutable();
        $assessmentTasks = $this->assessmentTasksForWindow($teachingGroup, $assessmentDate, $assessment);
        $assessmentCompetencies = $this->assessmentCompetenciesForWindow($teachingGroup, $assessmentDate);

        return ['group' => $teachingGroup, 'assessment' => $assessment, 'gradeComponents' => $teachingGroup->gradeComponents->values(), 'slot' => $slot ? ['date' => $slot->date->toDateString(), 'period_number' => $slot->period_number] : null, 'assessmentTasks' => $assessmentTasks, 'assessmentCompetencies' => $assessmentCompetencies, 'returnTab' => $returnTab, 'returnTo' => in_array($returnTo, ['group', 'year-plan'], true) ? $returnTo : 'group'];
    }

    private function ensureAssessmentBelongsToGroup(Assessment $assessment, TeachingGroup $teachingGroup): void
    {
        abort_unless($assessment->teaching_group_id === $teachingGroup->id, 404);
    }

    private function ensureBookletBelongsToAssessment(AssessmentBooklet $booklet, Assessment $assessment): void
    {
        abort_unless($booklet->assessment_id === $assessment->id, 404);
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
                ->with(['competencies.educationPlanCompetency.area', 'competencies.educationPlanCompetency.variants'])])
            ->get()
            ->flatMap->lessons
            ->flatMap->competencies
            ->filter(fn ($competency) => $competency->educationPlanCompetency?->area?->kind === 'content'
                || $competency->educationPlanCompetency?->area?->kind === 'content')
            ->map(function ($competency): array {
                $educationPlanCompetency = $competency->educationPlanCompetency
                    ?? $competency->educationPlanCompetency;

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

        $selectedTasks = $assessment?->tasks()->get() ?? collect();
        $selectedTaskIds = $selectedTasks->pluck('id')->all();
        $selectedTaskData = $selectedTasks->mapWithKeys(fn (AssessmentTask $selectedTask): array => [$selectedTask->id => [
            'position' => (int) $selectedTask->pivot->position,
            'weight' => (int) ($selectedTask->pivot->weight ?? 50),
        ]]);
        $windowTaskIds = $tasks->pluck('id')->all();

        if ($assessment) {
            $tasks = $tasks->merge($assessment->tasks()->with(['levels', 'expectations', 'competency.unit', 'competency.educationPlanCompetency.variants', 'educationPlanCompetency.area', 'educationPlanCompetency.variants'])->get())->unique('id')->sortBy('title')->values();
        }

        return $tasks->map(function (AssessmentTask $task) use ($selectedTaskIds, $selectedTaskData, $windowTaskIds): array {
            $educationPlanCompetency = $task->educationPlanCompetency;
            $competencyId = $educationPlanCompetency?->id;

            return [
                'id' => $task->id,
                'title' => $task->title,
                'max_points' => $task->maximumPoints(),
                'levels' => $task->levels->pluck('level')->values()->all() ?: collect([$task->level])->filter()->values()->all(),
                'competency_id' => $competencyId,
                'education_plan_competency_id' => $educationPlanCompetency?->id,
                'competency_key' => $educationPlanCompetency
                    ? 'education-plan-'.$educationPlanCompetency->id
                    : 'text-'.md5((string) $educationPlanCompetency?->text),
                'competency' => $educationPlanCompetency ? $this->resolvedCompetencyText($educationPlanCompetency) : null,
                'edit_url' => route('resources.library.assessment-tasks.edit', $task->id),
                'checked' => in_array($task->id, $selectedTaskIds, true),
                'position' => $selectedTaskData->get($task->id)['position'] ?? null,
                'weight' => $selectedTaskData->get($task->id)['weight'] ?? 50,
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

    /** @return array<string, mixed> */
    private function downloadTaskContent(AssessmentTask $task): array
    {
        $content = is_array($task->content) ? $task->content : [];

        if ($task->task_type === 'sentence_builder' && empty($content['shuffled_words'])) {
            $content['words'] = (string) ($task->solution ?? $content['words'] ?? '');
            $content = app(SentenceBuilderWordOrder::class)->apply($content, $content);
            $task->updateQuietly(['content' => $content]);
        }

        if (in_array($task->task_type, ['free_text', 'drawing', 'image_matching', 'image_answer_table'], true)) {
            $content['images'] = $task->images
                ->filter(fn ($image): bool => $image->resource !== null)
                ->map(fn ($image): array => [
                    'identifier' => $image->identifier,
                    'path' => Storage::disk('local')->path($image->resource->storage_path),
                    'answer' => $image->answer,
                    'copyright' => $image->resource->copyrights,
                ])
                ->values()
                ->all();
        }

        if ($task->task_type === 'image_labeling') {
            $image = $task->images->first();
            if ($image?->resource !== null) {
                $content['image'] = [
                    'path' => Storage::disk('local')->path($image->resource->storage_path),
                    'copyright' => $image->resource->copyrights,
                    'labels' => $image->labels->map(fn ($label): array => [
                        'x_percent' => (float) $label->x_percent,
                        'y_percent' => (float) $label->y_percent,
                        'solution' => $label->solution,
                        'lines' => $label->lines,
                    ])->values()->all(),
                ];
            }
        }

        return $content;
    }

    private function validatedAssessment(Request $request, TeachingGroup $teachingGroup): array
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'report_period_id' => ['nullable', 'integer'], 'grade_component_id' => ['present', 'nullable', 'integer'], 'assessed_on' => ['nullable', 'date'], 'return_tab' => ['nullable', 'in:assessments'], 'return_to' => ['nullable', 'in:group,year-plan'], 'notes' => ['nullable', 'string'], 'tasks' => ['sometimes', 'array'], 'tasks.*.task_id' => ['nullable', 'integer'], 'tasks.*.title' => ['nullable', 'string', 'max:255'], 'tasks.*.solution' => ['nullable', 'string'], 'tasks.*.max_points' => ['nullable', 'integer', 'min:1'], 'tasks.*.competency_id' => ['nullable', 'integer'], 'tasks.*.level' => ['nullable', 'in:G,M,E'], 'tasks.*.levels' => ['sometimes', 'array'], 'tasks.*.levels.*' => ['in:G,M,E'], 'tasks.*.weight' => ['nullable', 'integer', 'between:0,100']]);
        $component = $data['grade_component_id'] === null ? null : $teachingGroup->gradeComponents()->whereKey($data['grade_component_id'])->first();
        if ($data['grade_component_id'] !== null && $component === null) {
            throw ValidationException::withMessages([
                'grade_component_id' => 'Die Kategorie gehört nicht zu dieser Unterrichtsgruppe oder ist nicht aktiv.',
            ]);
        }

        return $data + ['grade_component_label' => $component?->label];
    }

    private function syncTasks(Assessment $assessment, TeachingGroup $teachingGroup, array $tasks): void
    {
        $groupCompetencies = $teachingGroup->teachingUnits()->with('competencies:id,teaching_unit_id,education_plan_competency_id')->get()->flatMap->competencies;
        $educationPlanCompetencyIds = $groupCompetencies->pluck('education_plan_competency_id')->filter();
        $attach = [];
        foreach ($tasks as $position => $task) {
            $levels = collect($task['levels'] ?? (($task['level'] ?? null) ? [$task['level']] : []))->unique()->values();
            if ($this->isDifferentiated($teachingGroup)) {
                abort_unless($levels->isNotEmpty(), 422, 'Für differenzierte Gruppen muss mindestens ein G/M/E-Niveau gewählt werden.');
            }
            if (! empty($task['task_id'])) {
                $model = AssessmentTask::where('organization_id', $teachingGroup->organization_id)->whereKey($task['task_id'])->firstOrFail();
                $assignedToGroup = $model->lessons()->whereHas('unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->exists();
                $alreadyAssigned = $assessment->tasks()->whereKey($model->id)->exists();
                abort_unless($educationPlanCompetencyIds->contains($model->education_plan_competency_id) || $assignedToGroup || $alreadyAssigned, 422);
                if ($levels->isNotEmpty()) {
                    $model->levels()->delete();
                    $model->levels()->createMany($levels->map(fn ($level) => ['level' => $level])->all());
                    $model->update(['level' => $levels->first()]);
                }
            } else {
                abort_unless(! empty($task['education_plan_competency_id']) && $educationPlanCompetencyIds->contains($task['education_plan_competency_id']), 422);
                $model = AssessmentTask::create(['organization_id' => $teachingGroup->organization_id, 'education_plan_competency_id' => $task['education_plan_competency_id'], 'education_plan_id' => EducationPlanCompetency::findOrFail($task['education_plan_competency_id'])->area->version->education_plan_id, 'title' => $task['title'], 'solution' => $task['solution'] ?? null, 'max_points' => $task['max_points'] ?? null, 'level' => $levels->first()]);
                $model->levels()->delete();
                $model->levels()->createMany($levels->map(fn ($level) => ['level' => $level])->all());
            }
            $attach[$model->id] = ['position' => $position + 1, 'weight' => $task['weight'] ?? 50];
        }
        $assessment->tasks()->sync($attach);
    }

    private function isDifferentiated(TeachingGroup $teachingGroup): bool
    {
        return $teachingGroup->gradeLevels()->pluck('grade_level')->contains(fn ($level) => preg_match('/(?:^|[\s\/])(?:G|M|E)(?:$|[\s\/])/', strtoupper((string) $level)) === 1);
    }
}
