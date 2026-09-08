<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentTaskType;
use App\Http\Requests\UpdateLessonExecutionRequest;
use App\Models\AssessmentTask;
use App\Models\AttendanceRecord;
use App\Models\CompetenceEvidence;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetency;
use App\Models\LessonTemplate;
use App\Models\Observation;
use App\Models\ObservationType;
use App\Models\PhaseTemplate;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
use App\Models\ScheduleSlot;
use App\Models\SocialForm;
use App\Models\SongVersion;
use App\Models\Student;
use App\Services\Assessment\ClozeTaskNormalizer;
use App\Services\AssessmentEvaluation\SentenceBuilderWordOrder;
use App\Services\AssessmentEvaluation\SortingTaskOrder;
use App\Services\CompetencyResolver;
use App\Services\SongbookContentsResolver;
use App\Services\SongbookPdfExporter;
use App\Services\WscDocInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LessonWorkspaceController extends Controller
{
    public function createAssessmentTask(Request $request, ScheduleSlot $scheduleSlot): Response
    {
        $group = $scheduleSlot->group;
        $this->authorize('update', $group);
        abort_unless($scheduleSlot->scheduledLesson?->lesson, 404);

        return $this->assessmentTaskForm($request, $scheduleSlot, null);
    }

    public function editAssessmentTask(Request $request, ScheduleSlot $scheduleSlot, AssessmentTask $assessmentTask): Response
    {
        $group = $scheduleSlot->group;
        $this->authorize('update', $group);
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson && $lesson->assessmentTasks()->whereKey($assessmentTask->id)->exists() && $assessmentTask->organization_id === $group->organization_id, 404);

        $assessmentTask->load(['educationPlanCompetency.variants', 'levels', 'expectations', 'images.resource', 'images.labels']);
        $assessmentTask->setRelation('images', $assessmentTask->images->map(fn ($image): array => [
            'resource_reference_id' => $image->resource_reference_id,
            'identifier' => $image->identifier,
            'position' => $image->position,
            'label' => $image->label,
            'answer' => $image->answer,
            'resource' => ['original_name' => $image->resource?->original_name],
            'preview_url' => $image->resource === null ? null : route('resources.library.files.preview', $image->resource),
            'labels' => $image->labels->map(fn ($label): array => ['id' => $label->id, 'position' => $label->position, 'x_percent' => (float) $label->x_percent, 'y_percent' => (float) $label->y_percent, 'solution' => $label->solution, 'lines' => $label->lines])->values()->all(),
        ]));
        $assessmentTask->setAttribute('has_differentiation', $assessmentTask->educationPlanCompetency?->variants?->contains(fn ($variant) => filled($variant->education_plan_level_id)) ?? false);

        return $this->assessmentTaskForm($request, $scheduleSlot, $assessmentTask);
    }

    private function assessmentTaskForm(Request $request, ScheduleSlot $scheduleSlot, ?AssessmentTask $task): Response
    {
        $initialCompetency = null;
        $initialEducationPlanId = null;
        if (! $task && $request->filled('education_plan_competency_id')) {
            $candidate = $scheduleSlot->scheduledLesson?->lesson?->educationPlanCompetencies()
                ->with(['variants', 'area.version'])
                ->where('education_plan_competencies.id', $request->integer('education_plan_competency_id'))
                ->first();
            $educationPlanCompetency = $candidate;
            $initialEducationPlanId = $educationPlanCompetency?->area?->version?->education_plan_id;
            if ($educationPlanCompetency && (! $request->filled('education_plan_id') || (int) $request->input('education_plan_id') === $initialEducationPlanId)) {
                $initialCompetency = $educationPlanCompetency->only(['id', 'external_identifier', 'number', 'text', 'is_active', 'position']);
                $initialCompetency['variants'] = $educationPlanCompetency->variants->map(fn ($variant): array => ['education_plan_level_id' => $variant->education_plan_level_id])->values()->all();
            } else {
                $initialEducationPlanId = null;
            }
        }

        return Inertia::render('AssessmentTask/Edit', [
            'scheduleSlotId' => $scheduleSlot->id,
            'backUrl' => route('lessons.show', $scheduleSlot).'?tab=assessment',
            'submitUrl' => $task
                ? route('lessons.assessment-tasks.update', [$scheduleSlot, $task])
                : route('lessons.assessment-tasks.store', $scheduleSlot),
            'method' => $task ? 'put' : 'post',
            'task' => $task,
            'initialEducationPlanId' => $initialEducationPlanId,
            'initialCompetency' => $initialCompetency,
            'imageLibrary' => ResourceReference::where('organization_id', $request->user()->organization_id)->where('mime_type', 'like', 'image/%')->orderBy('original_name')->get(['id', 'original_name'])->map(fn (ResourceReference $image): array => ['id' => $image->id, 'name' => $image->original_name, 'preview_url' => route('resources.library.files.preview', $image)])->values(),
            'imageUploadUrl' => route('resources.library.images.store'),
            'educationPlans' => EducationPlan::whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function storeAssessmentTask(Request $request, ScheduleSlot $scheduleSlot): RedirectResponse
    {
        $group = $scheduleSlot->group;
        $this->authorize('update', $group);
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson, 404);
        $expectations = $this->validatedExpectations($request);
        $data = $request->validate([
            'education_plan_id' => ['required', 'integer'],
            'education_plan_competency_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'task_type' => ['required', Rule::in(AssessmentTaskType::values())],
            'content' => ['nullable', 'array'],
            'content.prompt' => ['nullable', 'string', 'max:10000'],
            'content.lines' => ['nullable', 'integer', 'min:0', 'max:200'],
            'content.lineated' => ['sometimes', 'boolean'],
            'content.optional_reading_text' => ['nullable', 'string', 'max:50000'],
            'content.show_solutions' => ['sometimes', 'boolean'], 'content.points_per_correct_answer' => ['nullable', 'integer', 'min:0', 'max:10000'], 'content.matching_scoring_mode' => ['nullable', Rule::in(['per_category', 'complete_row'])], 'content.categories' => ['nullable', 'array'], 'content.categories.*.id' => ['required_if:task_type,matching_table', 'string', 'max:100'], 'content.categories.*.text' => ['required_if:task_type,matching_table', 'string', 'max:2000'], 'content.rows' => ['nullable', 'array'], 'content.rows.*.id' => ['required_if:task_type,matching_table', 'string', 'max:100'], 'content.rows.*.text' => ['required_if:task_type,matching_table', 'string', 'max:2000'], 'content.rows.*.category_ids' => ['required_if:task_type,matching_table', 'array'], 'content.rows.*.category_ids.*' => ['string', 'max:100'],
            'content.subtasks' => ['nullable', 'array'],
            'content.subtasks.*.key' => ['required_with:content.subtasks', 'string', 'max:100'],
            'content.subtasks.*.label' => [Rule::requiredIf(fn () => $request->input('task_type') === 'subtask_table'), 'nullable', 'string', 'max:2000'], 'content.subtasks.*.image_identifier' => [Rule::requiredIf(fn () => $request->input('task_type') === 'image_answer_table'), 'nullable', 'string', 'max:100'],
            'content.subtasks.*.solution' => ['nullable', 'string', 'max:2000'],
            'content.subtasks.*.lines' => ['required_with:content.subtasks', 'integer', 'min:0', 'max:200'],
            'content.subtasks.*.points' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'content.options' => ['nullable', 'array'],
            'content.points_per_correct_answer' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'content.checkbox_scoring_mode' => ['nullable', Rule::in(['correct_states', 'correct_selections'])],
            'content.options.*.id' => ['required_with:content.options', 'string', 'max:100'],
            'content.options.*.text' => ['required_with:content.options', 'string', 'max:2000'],
            'content.options.*.correct' => ['sometimes', 'boolean'],
            'content.columns' => ['nullable', 'array'],
            'content.columns.*' => ['nullable', 'array'], 'content.columns.*.*' => ['nullable'],
            'content.rows' => ['nullable', 'array'], 'content.rows.*' => ['array'], 'content.rows.*.*' => ['nullable'],
            'content.rows.*.label' => ['nullable'],
            'content.rows.*.answer' => ['nullable'],
            'content.images' => ['prohibited'],
            'content.image_width_cm' => ['nullable', 'numeric', 'min:1.5', 'max:4'],
            'images' => ['nullable', 'array'],
            'images.*.identifier' => ['nullable', 'string', 'max:100'],
            'images.*.resource_id' => ['required', 'integer'],
            'images.*.label' => ['nullable', 'string', 'max:255'],
            'images.*.answer' => ['nullable', 'string', 'max:2000'],
            'content.questions' => ['nullable', 'array'],
            'content.questions.*.label' => ['required_with:content.questions', 'string', 'max:2000'],
            'content.questions.*.lines' => ['nullable', 'integer', 'min:0', 'max:200'],
            'content.words' => ['nullable', 'string', 'max:5000'],
            'solution' => ['nullable', 'string'],
            'max_points' => ['nullable', 'integer', 'min:1'],
            'levels' => ['sometimes', 'array'],
            'levels.*' => ['in:G,M,E'],
        ]);
        $data['content'] = array_replace($data['content'] ?? [], $this->validatedDrawingContent($request, $data['task_type']));
        if ($data['task_type'] === 'cloze') {
            $data['content']['instruction'] = (string) $request->input('content.instruction', '');
        }
        $data['content'] = array_replace($data['content'] ?? [], $this->validatedClozeFields($request, $data['task_type']));
        $cloze = $this->normalizedCloze($data['task_type'], $data['content'] ?? []);
        $data['content'] = array_replace($data['content'] ?? [], $cloze['content']);
        $sortingContent = $request->validate(['content.points_per_sentence' => ['nullable', 'integer', 'min:0', 'max:10000'], 'content.questions.*.id' => ['required_with:content.questions', 'string', 'max:100']])['content'] ?? [];
        $data['content'] = array_replace_recursive($data['content'] ?? [], $sortingContent);
        if ($data['task_type'] === 'sentence_builder') {
            $data['content']['words'] = (string) ($data['solution'] ?? '');
            $data['content'] = app(SentenceBuilderWordOrder::class)->apply($data['content']);
        }
        if ($data['task_type'] === 'sorting') {
            $data['content'] = app(SortingTaskOrder::class)->apply($data['content']);
        }
        $expectations = array_merge($cloze['expectations'], $expectations);
        $expectations = $this->subtaskExpectations($data['task_type'], data_get($data, 'content', []), $expectations);
        $data['content']['optional_reading_text'] = $request->validate(['content.optional_reading_text' => ['nullable', 'string', 'max:50000']])['content']['optional_reading_text'] ?? null;
        $data['content']['rating_scale'] = $request->validate(['content.rating_scale' => ['nullable', Rule::in(['stars', 'likert'])]])['content']['rating_scale'] ?? null;
        $data['content']['rating_scale_label'] = $request->validate(['content.rating_scale_label' => ['nullable', 'string', 'max:255']])['content']['rating_scale_label'] ?? null;
        $labeling = $this->validatedImageLabeling($request, $data['task_type']);
        $data['content'] = ($data['content'] ?? []) + $labeling['content'] + ['lineated' => $request->boolean('content.lineated')];
        $attributes = [
            'organization_id' => $group->organization_id,
            'title' => $data['title'],
            'task_type' => $data['task_type'],
            'content' => $data['content'] ?? null,
            'solution' => $data['solution'] ?? null,
            'max_points' => $data['task_type'] === 'sentence_builder' ? ($data['max_points'] ?? null) : ($expectations ? collect($expectations)->sum(fn ($expectation) => $expectation['points'] * $expectation['repetitions']) : null),
            'level' => collect($data['levels'] ?? [])->first(),
        ];
        abort_unless(EducationPlan::whereKey($data['education_plan_id'])->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $group->organization_id))->exists(), 422, 'Der Bildungsplan ist nicht verfügbar.');
        abort_unless(EducationPlanCompetency::whereKey($data['education_plan_competency_id'])->whereHas('area.version', fn ($query) => $query->where('education_plan_id', $data['education_plan_id']))->exists(), 422, 'Die Kompetenz gehört nicht zum gewählten Bildungsplan.');
        $attributes += ['education_plan_id' => $data['education_plan_id'], 'education_plan_competency_id' => $data['education_plan_competency_id']];
        $task = AssessmentTask::create($attributes);
        $task->expectations()->createMany($expectations);
        $task->load('expectations');
        $task->update(['max_points' => $task->maximumPoints()]);
        $task->levels()->createMany(collect($data['levels'] ?? [])->map(fn ($level) => ['level' => $level])->all());
        $lesson->assessmentTasks()->syncWithoutDetaching([$task->id]);
        $this->syncTaskImages($task, $this->orderedTaskImages($request, $data['images'] ?? []), $group->organization_id);
        $this->syncTaskImageLabels($task, $labeling['labels']);
        $task->update(['max_points' => $task->maximumPoints()]);

        return back()->with('success', 'Prüfungsaufgabe wurde angelegt und der Stunde zugeordnet.');
    }

    public function updateAssessmentTask(Request $request, ScheduleSlot $scheduleSlot, AssessmentTask $assessmentTask): RedirectResponse
    {
        $group = $scheduleSlot->group;
        $this->authorize('update', $group);
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson && $lesson->assessmentTasks()->whereKey($assessmentTask->id)->exists() && $assessmentTask->organization_id === $group->organization_id, 404);
        $expectations = $this->validatedExpectations($request);
        $request->validate(['education_plan_id' => ['required', 'integer'], 'education_plan_competency_id' => ['required', 'integer']]);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'task_type' => ['required', Rule::in(AssessmentTaskType::values())], 'content' => ['nullable', 'array'], 'content.prompt' => ['nullable', 'string', 'max:10000'], 'content.lines' => ['nullable', 'integer', 'min:0', 'max:200'], 'content.show_solutions' => ['sometimes', 'boolean'], 'content.points_per_correct_answer' => ['nullable', 'integer', 'min:0', 'max:10000'], 'content.matching_scoring_mode' => ['nullable', Rule::in(['per_category', 'complete_row'])], 'content.categories' => ['nullable', 'array'], 'content.categories.*.id' => ['required_if:task_type,matching_table', 'string', 'max:100'], 'content.categories.*.text' => ['required_if:task_type,matching_table', 'string', 'max:2000'], 'content.rows' => ['nullable', 'array'], 'content.rows.*.id' => ['required_if:task_type,matching_table', 'string', 'max:100'], 'content.rows.*.text' => ['required_if:task_type,matching_table', 'string', 'max:2000'], 'content.rows.*.category_ids' => ['required_if:task_type,matching_table', 'array'], 'content.rows.*.category_ids.*' => ['string', 'max:100'], 'content.subtasks' => ['nullable', 'array'], 'content.subtasks.*.key' => ['required_with:content.subtasks', 'string', 'max:100'], 'content.subtasks.*.label' => [Rule::requiredIf(fn () => $request->input('task_type') === 'subtask_table'), 'nullable', 'string', 'max:2000'], 'content.subtasks.*.image_identifier' => [Rule::requiredIf(fn () => $request->input('task_type') === 'image_answer_table'), 'nullable', 'string', 'max:100'], 'content.subtasks.*.solution' => ['nullable', 'string', 'max:2000'], 'content.subtasks.*.lines' => ['required_with:content.subtasks', 'integer', 'min:0', 'max:200'], 'content.subtasks.*.points' => ['nullable', 'integer', 'min:1', 'max:10000'], 'content.reading_text' => ['nullable', 'string', 'max:50000'], 'content.options' => ['nullable', 'array'], 'content.options.*.text' => ['required_with:content.options', 'string', 'max:2000'], 'content.options.*.correct' => ['sometimes', 'boolean'], 'content.columns' => ['nullable', 'array'], 'content.columns.*' => ['nullable', 'array'], 'content.columns.*.*' => ['nullable'], 'content.rows' => ['nullable', 'array'], 'content.rows.*' => ['array'], 'content.rows.*.*' => ['nullable'], 'content.rows.*.label' => ['nullable'], 'content.rows.*.answer' => ['nullable'], 'content.images' => ['prohibited'], 'content.image_width_cm' => ['nullable', 'numeric', 'min:1.5', 'max:4'], 'images' => ['nullable', 'array'], 'images.*.identifier' => ['nullable', 'string', 'max:100'], 'images.*.resource_id' => ['required', 'integer'], 'images.*.label' => ['nullable', 'string', 'max:255'], 'images.*.answer' => ['nullable', 'string', 'max:2000'], 'content.questions' => ['nullable', 'array'], 'content.questions.*.label' => ['required_with:content.questions', 'string', 'max:2000'], 'content.questions.*.lines' => ['nullable', 'integer', 'min:0', 'max:200'], 'content.words' => ['nullable', 'string', 'max:5000'], 'solution' => ['nullable', 'string'], 'max_points' => ['nullable', 'integer', 'min:1'], 'education_plan_id' => ['nullable', 'integer'], 'education_plan_competency_id' => ['nullable', 'integer'], 'levels' => ['sometimes', 'array'], 'levels.*' => ['in:G,M,E']]);
        $data['content'] = array_replace($data['content'] ?? [], $this->validatedDrawingContent($request, $data['task_type']));
        if ($data['task_type'] === 'cloze') {
            $data['content']['instruction'] = (string) $request->input('content.instruction', '');
        }
        $data['content'] = array_replace($data['content'] ?? [], $this->validatedClozeFields($request, $data['task_type']));
        $cloze = $this->normalizedCloze($data['task_type'], $data['content'] ?? [], $assessmentTask->content ?? []);
        $data['content'] = array_replace($data['content'] ?? [], $cloze['content']);
        $sortingContent = $request->validate(['content.points_per_sentence' => ['nullable', 'integer', 'min:0', 'max:10000'], 'content.questions.*.id' => ['required_with:content.questions', 'string', 'max:100']])['content'] ?? [];
        $data['content'] = array_replace_recursive($data['content'] ?? [], $sortingContent);
        if ($data['task_type'] === 'sentence_builder') {
            $data['content']['words'] = (string) ($data['solution'] ?? '');
            $data['content'] = app(SentenceBuilderWordOrder::class)->apply($data['content'], $assessmentTask->content ?? []);
        }
        if ($data['task_type'] === 'sorting') {
            $data['content'] = app(SortingTaskOrder::class)->apply($data['content'], $assessmentTask->content ?? []);
        }
        $expectations = array_merge($cloze['expectations'], $expectations);
        $expectations = $this->subtaskExpectations($data['task_type'], data_get($data, 'content', []), $expectations);
        $data['content']['rating_scale'] = $request->validate(['content.rating_scale' => ['nullable', Rule::in(['stars', 'likert'])]])['content']['rating_scale'] ?? null;
        $data['content']['rating_scale_label'] = $request->validate(['content.rating_scale_label' => ['nullable', 'string', 'max:255']])['content']['rating_scale_label'] ?? null;
        $checkboxContent = $request->validate(['content.points_per_correct_answer' => ['nullable', 'integer', 'min:0', 'max:10000'], 'content.checkbox_scoring_mode' => ['nullable', Rule::in(['correct_states', 'correct_selections'])], 'content.options.*.id' => ['required_with:content.options', 'string', 'max:100']])['content'] ?? [];
        $labeling = $this->validatedImageLabeling($request, $data['task_type']);
        $data['content'] = ($data['content'] ?? []) + $checkboxContent + $labeling['content'];
        $data['content'] = ($data['content'] ?? []) + ['lineated' => $request->boolean('content.lineated'), 'optional_reading_text' => $request->input('content.optional_reading_text'), 'rating_scale' => $request->input('content.rating_scale'), 'rating_scale_label' => $request->input('content.rating_scale_label')];
        $attributes = ['title' => $data['title'], 'task_type' => $data['task_type'], 'content' => $data['content'] ?? null, 'solution' => $data['solution'] ?? null, 'max_points' => $data['task_type'] === 'sentence_builder' ? ($data['max_points'] ?? null) : ($expectations ? collect($expectations)->sum(fn ($expectation) => $expectation['points'] * $expectation['repetitions']) : null), 'level' => collect($data['levels'] ?? [])->first()];
        if (filled($data['education_plan_id'] ?? null) && filled($data['education_plan_competency_id'] ?? null)) {
            abort_unless(EducationPlan::whereKey($data['education_plan_id'])->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $group->organization_id))->exists(), 422, 'Der Bildungsplan ist nicht verfügbar.');
            abort_unless(EducationPlanCompetency::whereKey($data['education_plan_competency_id'])->whereHas('area.version', fn ($query) => $query->where('education_plan_id', $data['education_plan_id']))->exists(), 422, 'Die Kompetenz gehört nicht zum gewählten Bildungsplan.');
            $attributes += ['education_plan_id' => $data['education_plan_id'], 'education_plan_competency_id' => $data['education_plan_competency_id']];
        }
        $assessmentTask->update($attributes);
        $assessmentTask->expectations()->delete();
        $assessmentTask->expectations()->createMany($expectations);
        $assessmentTask->load('expectations');
        $assessmentTask->update(['max_points' => $assessmentTask->maximumPoints()]);
        $assessmentTask->levels()->delete();
        $assessmentTask->levels()->createMany(collect($data['levels'] ?? [])->map(fn ($level) => ['level' => $level])->all());
        $this->syncTaskImages($assessmentTask, $this->orderedTaskImages($request, $data['images'] ?? []), $group->organization_id);
        $this->syncTaskImageLabels($assessmentTask, $labeling['labels']);
        $assessmentTask->update(['max_points' => $assessmentTask->maximumPoints()]);

        return back()->with('success', 'Prüfungsaufgabe wurde gespeichert.');
    }

    private function validatedExpectations(Request $request): array
    {
        return $request->validate([
            'expectations' => ['nullable', 'array'],
            'expectations.*.subtask_key' => ['nullable', 'string', 'max:100'],
            'expectations.*.text' => ['required_with:expectations', 'string', 'max:5000'],
            'expectations.*.points' => ['required_with:expectations', 'integer', 'min:1', 'max:10000'],
            'expectations.*.repetitions' => ['required_with:expectations', 'integer', 'min:1', 'max:10000'],
        ])['expectations'] ?? [];
    }

    /** @return array<string, mixed> */
    private function validatedDrawingContent(Request $request, string $taskType): array
    {
        if ($taskType !== 'drawing') {
            return [];
        }

        $content = $request->validate([
            'content.height_cm' => [Rule::requiredIf(fn (): bool => count((array) $request->input('images', [])) === 0), 'numeric', 'min:1', 'max:50'],
            'content.bordered' => ['sometimes', 'boolean'],
            'images' => ['nullable', 'array', 'max:1'],
        ])['content'] ?? [];
        $content['bordered'] ??= true;

        return $content;
    }

    /** @return array{content: array<string, mixed>, expectations: list<array<string, mixed>>} */
    private function normalizedCloze(string $taskType, array $content, array $previousContent = []): array
    {
        if ($taskType !== 'cloze') {
            return ['content' => [], 'expectations' => []];
        }

        try {
            return app(ClozeTaskNormalizer::class)->normalize($content, $previousContent);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['content.prompt' => $exception->getMessage()]);
        }
    }

    /** @return array<string, mixed> */
    private function validatedClozeFields(Request $request, string $taskType): array
    {
        if ($taskType !== 'cloze') {
            return [];
        }

        return $request->validate([
            'content.lineated' => ['sometimes', 'boolean'],
            'content.split_blank_words' => ['sometimes', 'boolean'],
            'content.blanks' => ['nullable', 'array'],
            'content.blanks.*.id' => ['required_with:content.blanks', 'string', 'max:50'],
            'content.blanks.*.solution' => ['required_with:content.blanks', 'string', 'max:5000'],
            'content.blanks.*.points' => ['required_with:content.blanks', 'integer', 'min:1', 'max:10000'],
        ])['content'] ?? [];
    }

    private function subtaskExpectations(string $taskType, array $content, array $expectations): array
    {
        if ($taskType === 'heading_table') {
            return $this->headingTableExpectations($content, $expectations);
        }

        if (! in_array($taskType, ['subtask_table', 'image_answer_table'], true)) {
            return $expectations;
        }

        $manual = collect($expectations)->groupBy(fn (array $expectation): string => (string) ($expectation['subtask_key'] ?? ''));

        return collect($content['subtasks'] ?? [])->flatMap(function (array $subtask) use ($manual): array {
            $key = (string) $subtask['key'];
            $solution = trim((string) ($subtask['solution'] ?? ''));
            if ($solution !== '') {
                return [['subtask_key' => $key, 'text' => 'Lösung: '.$solution, 'points' => (int) ($subtask['points'] ?? 1), 'repetitions' => 1]];
            }

            $manualExpectations = $manual->get($key, collect());
            if ($manualExpectations->isEmpty()) {
                throw ValidationException::withMessages(['expectations' => 'Für jede Teilaufgabe ohne Lösung muss mindestens eine Erwartung angegeben werden.']);
            }

            return $manualExpectations->all();
        })->values()->all();
    }

    private function headingTableExpectations(array $content, array $expectations): array
    {
        $manual = collect($expectations)->keyBy(fn (array $expectation): string => (string) ($expectation['subtask_key'] ?? ''));
        $rowCount = count($content['rows'] ?? []);
        $cells = collect($content['columns'] ?? [])->merge(
            collect($content['rows'] ?? [])->flatMap(function (array $row) use ($rowCount): array {
                return $rowCount > 1
                    ? [($row['header'] ?? []), ...($row['cells'] ?? [])]
                    : ($row['cells'] ?? []);
            }),
        );

        return $cells->flatMap(function (array $cell) use ($manual): array {
            if (trim((string) ($cell['heading'] ?? '')) !== '') {
                return [];
            }
            $key = (string) ($cell['key'] ?? '');
            $solution = trim((string) ($cell['solution'] ?? ''));
            if ($solution !== '') {
                return [['subtask_key' => $key, 'text' => 'Lösung: '.$solution, 'points' => (int) ($cell['points'] ?? 1), 'repetitions' => 1]];
            }

            return $manual->has($key) ? [$manual->get($key)] : [];
        })->values()->all();
    }

    private function syncTaskImages(AssessmentTask $task, array $images, int $organizationId): void
    {
        $ids = collect($images)->pluck('resource_id')->filter()->unique()->values();
        $resources = ResourceReference::where('organization_id', $organizationId)->whereIn('id', $ids)->where('mime_type', 'like', 'image/%')->pluck('id');
        abort_unless($resources->count() === $ids->count(), 422, 'Das Bild ist nicht verfügbar.');
        $task->images()->delete();
        $images = collect($images)->sortBy(fn (array $image, int $index): int => (int) ($image['position'] ?? $index))->values();
        foreach ($images as $position => $image) {
            $task->images()->create(['resource_reference_id' => $image['resource_id'], 'identifier' => $image['identifier'] ?? 'pair-'.Str::uuid(), 'position' => $position, 'label' => $image['label'] ?? null, 'answer' => $image['answer'] ?? null]);
        }
    }

    /** @return array{content: array<string, mixed>, labels: list<array<string, mixed>>} */
    private function validatedImageLabeling(Request $request, string $taskType): array
    {
        if ($taskType !== 'image_labeling') {
            return ['content' => [], 'labels' => []];
        }

        $data = $request->validate([
            'content.image_label_width_cm' => ['required', 'numeric', 'min:4', 'max:8'],
            'content.points_per_correct_answer' => ['required', 'numeric', 'min:0', 'max:10000'],
            'content.show_solutions' => ['required', 'boolean'],
            'image_labels' => ['array'],
            'image_labels.*.position' => ['required', 'integer', 'min:0'],
            'image_labels.*.x_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'image_labels.*.y_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'image_labels.*.solution' => ['required', 'string', 'max:2000'],
            'image_labels.*.lines' => ['sometimes', 'integer', 'min:1', 'max:9'],
        ]);

        return ['content' => $data['content'] ?? [], 'labels' => $data['image_labels'] ?? []];
    }

    /** @param list<array<string, mixed>> $labels */
    private function syncTaskImageLabels(AssessmentTask $task, array $labels): void
    {
        if ($task->task_type !== 'image_labeling') {
            return;
        }

        $image = $task->images()->first();
        abort_unless($image !== null && $task->images()->count() === 1, 422, 'Für diese Aufgabe muss genau ein Bild ausgewählt werden.');
        $image->labels()->delete();
        $image->labels()->createMany(collect($labels)->sortBy('position')->values()->map(fn (array $label, int $position): array => [
            'position' => $position,
            'x_percent' => $label['x_percent'],
            'y_percent' => $label['y_percent'],
            'solution' => $label['solution'],
            'lines' => $label['lines'] ?? 1,
        ])->all());
    }

    private function orderedTaskImages(Request $request, array $images): array
    {
        $submittedImages = $request->input('images', []);

        return collect($images)->values()->map(function (array $image, int $index) use ($submittedImages): array {
            $image['position'] = isset($submittedImages[$index]['position']) && is_numeric($submittedImages[$index]['position'])
                ? (int) $submittedImages[$index]['position']
                : $index;

            return $image;
        })->all();
    }

    public function removeAssessmentTask(Request $request, ScheduleSlot $scheduleSlot, AssessmentTask $assessmentTask): RedirectResponse
    {
        $group = $scheduleSlot->group;
        $this->authorize('update', $group);
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson && $lesson->assessmentTasks()->whereKey($assessmentTask->id)->exists() && $assessmentTask->organization_id === $group->organization_id, 404);
        $lesson->assessmentTasks()->detach($assessmentTask->id);

        return back()->with('success', 'Prüfungsaufgabe wurde aus der Stunde entfernt.');
    }

    public function show(Request $request, ScheduleSlot $scheduleSlot, CompetencyResolver $competencyResolver, WscDocInspector $inspector): Response
    {
        $group = $scheduleSlot->group;
        $this->authorize('view', $group);
        $scheduleSlot->load([
            'group.school:id,name,short_name',
            'group.schoolYear:id,name',
            'scheduledLesson.lesson.unit.resources.lesson',
            'scheduledLesson.lesson.unit.materialItems',
            'scheduledLesson.lesson.resources',
            'scheduledLesson.lesson.galleryImages.resource',
            'scheduledLesson.lesson.materialItems',
            'scheduledLesson.lesson.assessmentTasks.competency',
            'scheduledLesson.lesson.assessmentTasks.educationPlanCompetency.variants.level',
            'scheduledLesson.lesson.assessmentTasks.levels',
            'scheduledLesson.lesson.songs.song:id,title,author,composer,copyright_notice',
            'scheduledLesson.lesson.unit.educationPlanCompetencies.area.version',
            'scheduledLesson.lesson.unit.educationPlanCompetencies.variants',
            'scheduledLesson.lesson.phases.socialForm',
            'scheduledLesson.lesson.phases.resources',
            'scheduledLesson.lesson.phases.resourceLinks',
            'scheduledLesson.lesson.phases.materialItems',
            'scheduledLesson.lesson.phases.songs.song:id,title,author,composer,copyright_notice',
            'scheduledLesson.lesson.phases.songs.parts',
            'scheduledLesson.lesson.educationPlanCompetencies.area.version',
            'scheduledLesson.lesson.educationPlanCompetencies.variants',
        ]);
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson, 404, 'Für diesen Termin ist keine Unterrichtsstunde eingeplant.');
        $lesson->unit->educationPlanCompetencies->each(fn ($competency) => $competency->setAttribute('competency_presentation', $competencyResolver->present($competency)));
        $lesson->educationPlanCompetencies->each(fn ($competency) => $competency->setAttribute('competency_presentation', $competencyResolver->present($competency)));
        $lesson->unit->setRelation('competencies', $lesson->unit->educationPlanCompetencies);
        $lesson->setRelation('competencies', $lesson->educationPlanCompetencies);
        $lesson->resources->each(function ($resource) use ($lesson, $inspector): void {
            if ($resource->page_count === null && strtolower(pathinfo($resource->original_name, PATHINFO_EXTENSION)) === 'wscdoc') {
                $resource->page_count = $inspector->pageCount(Storage::disk('local')->path($resource->storage_path));
            }
            $resource->setAttribute('display_name', $this->resourceFilename($lesson->unit, $resource));
        });
        $galleryImages = $lesson->galleryImages->map(fn ($image): array => [
            'id' => $image->id,
            'name' => $image->resource?->original_name ?: 'Bild',
            'preview_url' => $image->resource ? route('resources.library.files.preview', $image->resource) : null,
        ])->values();
        $lesson->unsetRelation('galleryImages');
        $lesson->setAttribute('gallery_images', $galleryImages);
        $lesson->phases->each(function ($phase): void {
            $phase->setAttribute('resource_ids', $phase->resources->pluck('id')->values());
            $phase->setAttribute('resource_link_ids', $phase->resourceLinks->pluck('id')->values());
            $phase->setAttribute('material_item_ids', $phase->materialItems->pluck('id')->values());
            $phase->setAttribute('song_ids', $phase->songs->pluck('id')->values());
        });
        $scheduledLesson = $scheduleSlot->scheduledLesson;
        $nextScheduledLesson = $group->scheduleSlots()
            ->where(function ($query) use ($scheduleSlot): void {
                $query->where('date', '>', $scheduleSlot->date)
                    ->orWhere(fn ($query) => $query->where('date', $scheduleSlot->date)->where('period_number', '>', $scheduleSlot->period_number));
            })
            ->whereHas('scheduledLesson', function ($query) use ($lesson): void {
                $query->where('lesson_id', '!=', $lesson->id)->whereNotIn('status', ['cancelled', 'postponed']);
            })
            ->with('scheduledLesson.lesson:id,title')
            ->orderBy('date')->orderBy('period_number')
            ->first();
        $groupStudents = $group->students()->orderBy('last_name')->orderBy('first_name')->get(['students.id', 'first_name', 'last_name', 'class_name']);
        $observationTypes = ObservationType::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))
            ->where('is_active', true)->orderBy('position')->orderBy('label')->get(['id', 'label', 'symbol', 'color']);
        if ($observationTypes->isEmpty()) {
            $defaults = [['label' => 'Material fehlt', 'symbol' => 'M'], ['label' => 'Hausaufgabe fehlt', 'symbol' => 'H'], ['label' => 'Mitarbeit', 'symbol' => '★']];
            foreach ($defaults as $position => $default) {
                $observationTypes->push(ObservationType::firstOrCreate(['organization_id' => $request->user()->organization_id, 'label' => $default['label']], $default + ['position' => $position]));
            }
        }
        $targetCompetencies = $lesson->competencies
            ->map(fn ($competency) => $competencyResolver->present($competency) + [
                'education_plan_competency_id' => $competency->id,
                'education_plan_id' => $competency->area?->version?->education_plan_id,
                'source_identifier' => $competency->external_identifier,
            ])
            ->groupBy('kind')
            ->map(fn ($competencies) => $competencies->values())
            ->all();
        $customProcessCompetences = $group->grading_model === 'observation_scales'
            ? $group->school->customProcessCompetences()->where('is_active', true)->get(['id', 'text', 'position'])
            : collect();

        return Inertia::render('Lessons/Show', [
            'slot' => $scheduleSlot,
            'nextScheduledLesson' => $nextScheduledLesson ? ['id' => $nextScheduledLesson->scheduledLesson->id, 'date' => $nextScheduledLesson->date->toDateString(), 'period_number' => $nextScheduledLesson->period_number, 'lesson_title' => $nextScheduledLesson->scheduledLesson->lesson->title] : null,
            'group' => $group,
            'lesson' => $lesson,
            'unit' => $lesson->unit,
            'phaseTemplates' => PhaseTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->with('socialForm:id,name')->orderBy('position')->orderBy('title')->get(['id', 'title', 'duration_minutes', 'social_form_id', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'material', 'media']),
            'socialForms' => SocialForm::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(['id', 'name']),
            'materialItems' => $lesson->unit->materialItems->merge($lesson->materialItems)->unique('id')->values(),
            'assessmentTasks' => $lesson->assessmentTasks->each(function ($task): void {
                $task->setAttribute('competency_identifier', $task->educationPlanCompetency?->external_identifier);
                $task->setAttribute('has_differentiation', $task->educationPlanCompetency?->variants?->contains(fn ($variant) => filled($variant->education_plan_level_id)) ?? false);
            }),
            'songs' => SongVersion::whereHas('song', fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->with('song:id,title,author,composer,copyright_notice')->orderBy('name')->get(),
            'resourceLinks' => ResourceLink::where('organization_id', $request->user()->organization_id)->where(function ($query) use ($lesson): void {
                $query->where('teaching_unit_id', $lesson->teaching_unit_id)->orWhere('lesson_id', $lesson->id);
            })->orderBy('title')->get(['id', 'teaching_unit_id', 'lesson_id', 'title', 'url', 'description']),
            'lessonTemplates' => LessonTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'targetCompetencies' => ['process' => $targetCompetencies['process'] ?? [], 'content' => $targetCompetencies['content'] ?? []],
            'customProcessCompetences' => $customProcessCompetences,
            'customProcessCompetenceScaleIntervalCount' => $group->school->observation_scale_interval_count,
            'observationStudents' => $groupStudents,
            'observationTypes' => $observationTypes,
            'attendanceRecords' => $scheduledLesson->attendanceRecords()->get(['student_id', 'status', 'note']),
            'observations' => $scheduledLesson->observations()->get(['student_id', 'observation_type_id', 'note']),
            'competenceEvidences' => $scheduledLesson->competenceEvidences()->get(['student_id', 'education_plan_competency_id', 'custom_process_competence_id', 'scale', 'custom_scale_level', 'custom_scale_status', 'note']),
        ]);
    }

    public function updateObservations(Request $request, ScheduleSlot $scheduleSlot)
    {
        $group = $scheduleSlot->group;
        $this->authorize('view', $group);
        $scheduledLesson = $scheduleSlot->scheduledLesson;
        abort_unless($scheduledLesson, 404);
        $students = $group->students()->pluck('students.id');
        $data = $request->validate([
            'students' => ['required', 'array'],
            'students.*.student_id' => ['required', 'integer'],
            'students.*.attendance' => ['nullable', 'in:present,absent,late'],
            'students.*.note' => ['nullable', 'string', 'max:2000'],
            'students.*.observation_type_ids' => ['sometimes', 'array'],
            'students.*.observation_type_ids.*' => ['integer'],
            'students.*.evidences' => ['sometimes', 'array'],
            'students.*.evidences.*.competency_id' => ['nullable', 'integer'],
            'students.*.evidences.*.custom_process_competence_id' => ['nullable', 'integer'],
            'students.*.evidences.*.scale' => ['nullable', 'string', 'max:32'],
            'students.*.evidences.*.custom_scale_level' => ['nullable', 'integer'],
            'students.*.evidences.*.custom_scale_status' => ['nullable', 'in:ne'],
            'students.*.evidences.*.note' => ['nullable', 'string', 'max:2000'],
        ]);
        $studentIds = collect($data['students'])->pluck('student_id');
        abort_unless($studentIds->unique()->count() === $studentIds->count() && $studentIds->diff($students)->isEmpty(), 422);
        $typeIds = ObservationType::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->pluck('id');
        $competencyIds = $scheduledLesson->lesson->educationPlanCompetencies()->pluck('education_plan_competencies.id');
        $customCompetences = $group->school->customProcessCompetences()->where('is_active', true)->get(['id']);
        foreach ($data['students'] as $student) {
            foreach ($student['evidences'] ?? [] as $evidence) {
                $hasImported = filled($evidence['competency_id'] ?? null);
                $hasCustom = filled($evidence['custom_process_competence_id'] ?? null);
                abort_unless($hasImported xor $hasCustom, 422);
                if ($hasCustom) {
                    abort_unless($group->grading_model === 'observation_scales' && $customCompetences->contains('id', $evidence['custom_process_competence_id']), 422);
                    $hasLevel = filled($evidence['custom_scale_level'] ?? null);
                    $hasStatus = ($evidence['custom_scale_status'] ?? null) === 'ne';
                    abort_unless($hasLevel xor $hasStatus, 422);
                    abort_unless(! $hasLevel || ((int) $evidence['custom_scale_level'] >= 1 && (int) $evidence['custom_scale_level'] <= $group->school->observation_scale_interval_count), 422);
                }
            }
        }

        DB::transaction(function () use ($data, $scheduledLesson, $typeIds, $competencyIds): void {
            foreach ($data['students'] as $student) {
                AttendanceRecord::updateOrCreate(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student['student_id']], ['status' => $student['attendance'] ?? 'present', 'note' => $student['note'] ?? null]);
                Observation::where('scheduled_lesson_id', $scheduledLesson->id)->where('student_id', $student['student_id'])->delete();
                foreach (collect($student['observation_type_ids'] ?? [])->intersect($typeIds) as $typeId) {
                    Observation::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student['student_id'], 'observation_type_id' => $typeId, 'note' => $student['note'] ?? null]);
                }
                CompetenceEvidence::where('scheduled_lesson_id', $scheduledLesson->id)->where('student_id', $student['student_id'])->delete();
                foreach ($student['evidences'] ?? [] as $evidence) {
                    if (filled($evidence['competency_id'] ?? null) && $competencyIds->contains($evidence['competency_id'])) {
                        CompetenceEvidence::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student['student_id'], 'education_plan_competency_id' => $evidence['competency_id'], 'scale' => $evidence['scale'] ?? null, 'note' => $evidence['note'] ?? null]);
                    } elseif (filled($evidence['custom_process_competence_id'] ?? null)) {
                        CompetenceEvidence::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student['student_id'], 'custom_process_competence_id' => $evidence['custom_process_competence_id'], 'custom_scale_level' => $evidence['custom_scale_level'] ?? null, 'custom_scale_status' => $evidence['custom_scale_status'] ?? null, 'note' => $evidence['note'] ?? null]);
                    }
                }
            }
        });

        return $this->observationResponse($request, 'Beobachtungen wurden gespeichert.');
    }

    public function updateStudentObservation(Request $request, ScheduleSlot $scheduleSlot, Student $student): RedirectResponse|JsonResponse
    {
        $group = $scheduleSlot->group;
        $this->authorize('view', $group);
        abort_unless($scheduleSlot->scheduledLesson && $group->students()->whereKey($student->id)->exists(), 404);
        $data = $request->validate([
            'attendance' => ['nullable', 'in:present,absent,late'],
            'note' => ['nullable', 'string', 'max:2000'],
            'observation_type_ids' => ['sometimes', 'array'],
            'observation_type_ids.*' => ['integer'],
            'evidences' => ['sometimes', 'array'],
            'evidences.*.competency_id' => ['nullable', 'integer'],
            'evidences.*.custom_process_competence_id' => ['nullable', 'integer'],
            'evidences.*.scale' => ['nullable', 'integer', 'between:1,5'],
            'evidences.*.custom_scale_level' => ['nullable', 'integer'],
            'evidences.*.custom_scale_status' => ['nullable', 'in:ne'],
        ]);
        $this->persistStudentObservation($scheduleSlot, $group, $student, $data);

        return $this->observationResponse($request, 'Beobachtung wurde gespeichert.');
    }

    public function bulkRateObservations(Request $request, ScheduleSlot $scheduleSlot): RedirectResponse|JsonResponse
    {
        $group = $scheduleSlot->group;
        $this->authorize('view', $group);
        $scheduledLesson = $scheduleSlot->scheduledLesson;
        abort_unless($scheduledLesson, 404);
        $data = $request->validate([
            'scale' => ['required', 'integer', 'between:1,5'],
            'custom_scale_level' => ['nullable', 'integer'],
            'custom_scale_status' => ['nullable', 'in:ne'],
        ]);
        $competencies = $scheduledLesson->lesson->educationPlanCompetencies()->get(['education_plan_competencies.id']);
        $customCompetences = $group->grading_model === 'observation_scales' ? $group->school->customProcessCompetences()->where('is_active', true)->get(['id']) : collect();
        $includeCustomCompetences = $customCompetences->isNotEmpty();
        if ($includeCustomCompetences) {
            abort_unless(filled($data['custom_scale_level'] ?? null) xor (($data['custom_scale_status'] ?? null) === 'ne'), 422);
            abort_unless(! filled($data['custom_scale_level'] ?? null) || ((int) $data['custom_scale_level'] >= 1 && (int) $data['custom_scale_level'] <= $group->school->observation_scale_interval_count), 422);
        }

        DB::transaction(function () use ($data, $group, $scheduledLesson, $competencies, $customCompetences, $includeCustomCompetences): void {
            $absentStudentIds = AttendanceRecord::where('scheduled_lesson_id', $scheduledLesson->id)->where('status', 'absent')->pluck('student_id');
            $students = $group->students()->whereNotIn('students.id', $absentStudentIds)->get(['students.id']);
            foreach ($students as $student) {
                foreach ($competencies as $competency) {
                    $evidence = CompetenceEvidence::firstOrNew(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student->id, 'education_plan_competency_id' => $competency->id]);
                    if (! filled($evidence->scale)) {
                        $evidence->scale = (string) $data['scale'];
                        $evidence->save();
                    }
                }
                if ($includeCustomCompetences) {
                    foreach ($customCompetences as $competence) {
                        $evidence = CompetenceEvidence::firstOrNew(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student->id, 'custom_process_competence_id' => $competence->id]);
                        if (! filled($evidence->custom_scale_level) && $evidence->custom_scale_status !== 'ne') {
                            $evidence->custom_scale_level = $data['custom_scale_level'] ?? null;
                            $evidence->custom_scale_status = $data['custom_scale_status'] ?? null;
                            $evidence->save();
                        }
                    }
                }
            }
        });

        return $this->observationResponse($request, 'Noch nicht gesetzte Bewertungen wurden eingetragen.');
    }

    private function observationResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    private function persistStudentObservation(ScheduleSlot $scheduleSlot, $group, Student $student, array $data): void
    {
        $scheduledLesson = $scheduleSlot->scheduledLesson;
        $typeIds = ObservationType::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', request()->user()->organization_id))->pluck('id');
        $competencyIds = $scheduledLesson->lesson->educationPlanCompetencies()->pluck('education_plan_competencies.id');
        $customCompetences = $group->school->customProcessCompetences()->where('is_active', true)->get(['id']);
        foreach ($data['evidences'] ?? [] as $evidence) {
            $hasImported = filled($evidence['competency_id'] ?? null);
            $hasCustom = filled($evidence['custom_process_competence_id'] ?? null);
            abort_unless($hasImported xor $hasCustom, 422);
            if ($hasImported) {
                abort_unless($competencyIds->contains($evidence['competency_id']), 422);
            }
            if ($hasCustom) {
                abort_unless($group->grading_model === 'observation_scales' && $customCompetences->contains('id', $evidence['custom_process_competence_id']), 422);
                abort_unless(filled($evidence['custom_scale_level'] ?? null) xor (($evidence['custom_scale_status'] ?? null) === 'ne'), 422);
                abort_unless(! filled($evidence['custom_scale_level'] ?? null) || ((int) $evidence['custom_scale_level'] >= 1 && (int) $evidence['custom_scale_level'] <= $group->school->observation_scale_interval_count), 422);
            }
        }
        DB::transaction(function () use ($data, $scheduledLesson, $student, $typeIds, $competencyIds): void {
            AttendanceRecord::updateOrCreate(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student->id], ['status' => $data['attendance'] ?? 'present', 'note' => $data['note'] ?? null]);
            Observation::where('scheduled_lesson_id', $scheduledLesson->id)->where('student_id', $student->id)->delete();
            foreach (collect($data['observation_type_ids'] ?? [])->intersect($typeIds) as $typeId) {
                Observation::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student->id, 'observation_type_id' => $typeId, 'note' => $data['note'] ?? null]);
            }
            CompetenceEvidence::where('scheduled_lesson_id', $scheduledLesson->id)->where('student_id', $student->id)->delete();
            foreach ($data['evidences'] ?? [] as $evidence) {
                $attributes = ['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student->id];
                if (filled($evidence['competency_id'] ?? null) && $competencyIds->contains($evidence['competency_id'])) {
                    $attributes['education_plan_competency_id'] = $evidence['competency_id'];
                } elseif (filled($evidence['custom_process_competence_id'] ?? null)) {
                    $attributes['custom_process_competence_id'] = $evidence['custom_process_competence_id'];
                } else {
                    continue;
                }
                CompetenceEvidence::create($attributes + ['scale' => isset($evidence['scale']) ? (string) $evidence['scale'] : null, 'custom_scale_level' => $evidence['custom_scale_level'] ?? null, 'custom_scale_status' => $evidence['custom_scale_status'] ?? null]);
            }
        });
    }

    private function resourceFilename($unit, $resource): string
    {
        $group = $unit->group()->with('gradeLevels')->first();
        $grade = $this->filenamePart($group?->gradeLevels->pluck('grade_level')->implode('-') ?: '');
        $aktenzeichen = $this->filenamePart($group?->aktenzeichen ?: '');
        $keyword = $this->filenamePart($unit->keyword ?: '');
        $original = pathinfo($resource->original_name ?: 'Datei', PATHINFO_FILENAME);
        $extension = pathinfo($resource->original_name ?: '', PATHINFO_EXTENSION);
        $original = preg_replace('/^\d+(?:\.\d+)*_[^\s]+\s+/u', '', $original) ?: $original;
        $prefix = $aktenzeichen !== '' ? $aktenzeichen.'_'.$grade : $grade;
        $lessonPart = $resource->lesson?->position ? str_pad((string) $resource->lesson->position, 2, '0', STR_PAD_LEFT) : null;

        return trim(collect([$prefix, $keyword, $lessonPart, $this->filenamePart($original).($extension ? '.'.$this->filenamePart($extension) : '')])->filter()->implode(' '));
    }

    private function filenamePart(string $value): string
    {
        return trim((string) preg_replace(['/[^\pL\pN._ -]+/u', '/\s+/u', '/\.{2,}/'], ['-', ' ', '.'], $value), ' .-');
    }

    public function updateExecution(UpdateLessonExecutionRequest $request, ScheduleSlot $scheduleSlot): RedirectResponse|JsonResponse
    {
        $group = $scheduleSlot->group;
        $this->authorize('update', $group);
        $scheduledLesson = $scheduleSlot->scheduledLesson;
        abort_unless($scheduledLesson, 404);
        $scheduledLesson->update($request->validated());

        $message = 'Durchführung wurde gespeichert.';

        return $request->expectsJson() ? response()->json(['message' => $message, 'status' => $scheduledLesson->status, 'actual_on' => $scheduledLesson->actual_on, 'execution_notes' => $scheduledLesson->execution_notes]) : back()->with('success', $message);
    }

    public function exportSongs(Request $request, ScheduleSlot $scheduleSlot, SongbookContentsResolver $contents, SongbookPdfExporter $exporter)
    {
        $group = $scheduleSlot->group;
        $this->authorize('view', $group);
        $data = $request->validate(['format' => ['required', 'in:a4,a5,chord-sheet'], 'instrument' => ['nullable', 'string', 'max:100']]);
        abort_if($data['format'] === 'chord-sheet' && blank($data['instrument'] ?? null), 422, 'Für ein Akkordblatt muss ein Instrument ausgewählt werden.');
        $format = $data['format'];
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson, 404, 'Für diesen Termin ist keine Unterrichtsstunde eingeplant.');
        $book = $group->songbook()->firstOrCreate([]);
        $versions = $contents->resolveLessonSongs($book, $lesson);
        abort_if($versions->isEmpty(), 422, 'Für diese Stunde sind keine neuen Lieder zugeordnet.');

        $path = $exporter->exportSongs($versions, $format, $book, $data['instrument'] ?? null);

        return Storage::disk('local')->download($path, 'Neue-Lieder-Stunde-'.$scheduleSlot->date->format('Y-m-d').'-'.$format.'.pdf');
    }
}
