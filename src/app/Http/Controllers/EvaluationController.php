<?php

namespace App\Http\Controllers;

use App\Models\CompetenceEvidence;
use App\Models\EducationPlanCompetency;
use App\Models\ReportPeriod;
use App\Models\ReportPeriodEvaluationTemplate;
use App\Models\StudentEvaluation;
use App\Models\TeachingGroup;
use App\Services\CompetencyResolver;
use App\Services\EvaluationTemplateGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EvaluationController extends Controller
{
    public function index(Request $request, ?TeachingGroup $teachingGroup = null)
    {
        $this->authorize('viewAny', TeachingGroup::class);
        $groups = TeachingGroup::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'grading_model']);
        $preference = $request->user()->preferences()->where('key', 'evaluations.last_group')->first()?->value ?? [];
        $selectedGroup = ($teachingGroup && $teachingGroup->user_id === $request->user()->id ? $groups->firstWhere('id', $teachingGroup->id) : null)
            ?? $groups->firstWhere('id', $request->integer('group'))
            ?? $groups->firstWhere('id', (int) ($preference['group_id'] ?? 0))
            ?? $groups->first();
        if ($selectedGroup && (int) ($preference['group_id'] ?? 0) !== $selectedGroup->id) {
            $request->user()->preferences()->updateOrCreate(
                ['key' => 'evaluations.last_group'],
                ['value' => ['group_id' => $selectedGroup->id]],
            );
        }
        $selectedGroup?->load('reportPeriods.evaluations.student', 'reportPeriods.evaluations.competenceRatings', 'reportPeriods.evaluations.observationScales');
        $selectedGroup?->loadMissing('gradeComponents', 'schoolYear');
        if (in_array($selectedGroup?->grading_model, ['competency_texts_and_grades', 'grades_only'], true)) {
            $selectedGroup->reportPeriods->each(function ($period) use ($selectedGroup): void {
                $period->evaluations->each(function ($evaluation) use ($selectedGroup): void {
                    if ($evaluation->status !== 'confirmed') {
                        return;
                    }
                    $components = $this->gradeComponents($selectedGroup, $evaluation, $evaluation->competenceRatings);
                    $active = collect($components)->filter(fn (array $component): bool => $component['percentage'] !== null);
                    $weight = $active->sum('weight');
                    $percentage = $weight > 0 ? round_percentage($active->sum(fn (array $component): float => $component['percentage'] * $component['weight']) / $weight) : null;
                    $evaluation->setAttribute('result_percentage', $percentage);
                    $evaluation->setAttribute('result_grade', $percentage === null ? null : ($evaluation->period->whole_grades ? percentage_to_whole_grade($percentage) : percentage_to_grade($percentage)));
                });
            });
        }

        return Inertia::render('Evaluations/Index', [
            'groups' => $groups,
            'group' => $selectedGroup,
            'reportPeriods' => $selectedGroup?->reportPeriods ?? collect(),
        ]);
    }

    public function createPeriod(TeachingGroup $teachingGroup)
    {
        $this->authorize('update', $teachingGroup);

        return Inertia::render('Evaluations/PeriodForm', ['group' => $teachingGroup, 'period' => null]);
    }

    public function editPeriod(TeachingGroup $teachingGroup, ReportPeriod $period)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($period->teaching_group_id === $teachingGroup->id, 404);

        return Inertia::render('Evaluations/PeriodForm', ['group' => $teachingGroup, 'period' => $period]);
    }

    public function storePeriod(Request $request, TeachingGroup $teachingGroup, EvaluationTemplateGenerator $templateGenerator)
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['label' => ['required', 'string', 'max:100'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'whole_grades' => ['sometimes', 'boolean'], 'include_full_school_year' => ['sometimes', 'boolean']]);
        $data['whole_grades'] = (bool) ($data['whole_grades'] ?? false);
        $data['include_full_school_year'] = (bool) ($data['include_full_school_year'] ?? false);
        if (! in_array($teachingGroup->grading_model, ['grades_only', 'competency_texts_and_grades'], true)) {
            $data['whole_grades'] = false;
            $data['include_full_school_year'] = false;
        }
        DB::transaction(function () use ($data, $teachingGroup, $templateGenerator): void {
            $period = $teachingGroup->reportPeriods()->create([...$data, 'user_id' => $teachingGroup->user_id]);
            $teachingGroup->students()->get()->each(fn ($student) => $period->evaluations()->create(['student_id' => $student->id]));
            if ($teachingGroup->grading_model === 'competency_texts_and_grades') {
                $period->evaluationTemplates()->createMany($templateGenerator->generate($period)->all());
            }
        });

        return to_route('teaching-groups.show', [$teachingGroup, 'tab' => 'evaluations'])->with('success', 'Bewertungszeitraum wurde angelegt.');
    }

    public function updatePeriod(Request $request, TeachingGroup $teachingGroup, ReportPeriod $period)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($period->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['label' => ['required', 'string', 'max:100'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'whole_grades' => ['sometimes', 'boolean'], 'include_full_school_year' => ['sometimes', 'boolean']]);
        $period->update([
            ...$data,
            'whole_grades' => in_array($teachingGroup->grading_model, ['grades_only', 'competency_texts_and_grades'], true) && (bool) ($data['whole_grades'] ?? false),
            'include_full_school_year' => in_array($teachingGroup->grading_model, ['grades_only', 'competency_texts_and_grades'], true) && (bool) ($data['include_full_school_year'] ?? false),
        ]);

        return to_route('teaching-groups.show', [$teachingGroup, 'tab' => 'evaluations'])->with('success', 'Bewertungszeitraum wurde gespeichert.');
    }

    public function deletePeriod(TeachingGroup $teachingGroup, ReportPeriod $period)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($period->teaching_group_id === $teachingGroup->id, 404);
        $period->delete();

        return to_route('teaching-groups.show', [$teachingGroup, 'tab' => 'evaluations'])->with('success', 'Bewertungszeitraum wurde gelöscht.');
    }

    public function editTemplate(TeachingGroup $teachingGroup, ReportPeriod $period, EvaluationTemplateGenerator $templateGenerator)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($period->teaching_group_id === $teachingGroup->id, 404);
        if ($teachingGroup->grading_model === 'competency_texts_and_grades' && ! $period->evaluationTemplates()->exists()) {
            $period->evaluationTemplates()->createMany($templateGenerator->generate($period)->all());
        }

        return Inertia::render('Evaluations/TemplateEdit', [
            'group' => $teachingGroup,
            'period' => $period->load('evaluationTemplates'),
        ]);
    }

    public function updateTemplate(Request $request, TeachingGroup $teachingGroup, ReportPeriod $period)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($period->teaching_group_id === $teachingGroup->id, 404);
        abort_unless($teachingGroup->grading_model === 'competency_texts_and_grades', 404);
        $data = $request->validate(['templates' => ['required', 'array'], 'templates.*.id' => ['required', 'integer'], 'templates.*.text' => ['required', 'string', 'max:10000']]);
        $templates = $period->evaluationTemplates()->whereKey(collect($data['templates'])->pluck('id'))->get()->keyBy('id');
        abort_unless($templates->count() === count($data['templates']), 422);
        foreach ($data['templates'] as $template) {
            $templates->get($template['id'])->update(['text' => $template['text']]);
        }

        return to_route('teaching-groups.show', [$teachingGroup, 'tab' => 'evaluations'])->with('success', 'Bewertungsvorlagen wurden gespeichert.');
    }

    public function resetTemplate(TeachingGroup $teachingGroup, ReportPeriod $period, ReportPeriodEvaluationTemplate $template, EvaluationTemplateGenerator $templateGenerator)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($period->teaching_group_id === $teachingGroup->id, 404);
        abort_unless($teachingGroup->grading_model === 'competency_texts_and_grades', 404);
        abort_unless($template->report_period_id === $period->id, 404);

        $proposal = $templateGenerator->generate($period)->first(fn (array $generated): bool => $generated['level'] === $template->level);
        abort_unless($proposal, 422);
        $template->update(['text' => $proposal['text']]);

        return to_route('evaluations.templates.edit', [$teachingGroup, $period]);
    }

    public function edit(TeachingGroup $teachingGroup, StudentEvaluation $evaluation, CompetencyResolver $competencyResolver)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($evaluation->period->teaching_group_id === $teachingGroup->id, 404);

        $evaluation->load('student', 'period', 'observationScales', 'competenceRatings');
        $teachingGroup->loadMissing('school', 'schoolYear', 'gradeComponents');
        $navigation = $this->evaluationNavigation($evaluation);
        $lses = collect();
        $periodLevel = $evaluation->level;
        if (in_array($teachingGroup->grading_model, ['competency_texts_and_grades', 'grades_only'], true)) {
            $lses = $teachingGroup->assessments()
                ->where(function ($query) use ($evaluation): void {
                    $query->where('report_period_id', $evaluation->period_id)
                        ->orWhereBetween('assessed_on', [$evaluation->period->starts_on, $evaluation->period->ends_on])
                        ->orWhereHas('scheduleSlots', fn ($slotQuery) => $slotQuery->whereBetween('date', [$evaluation->period->starts_on, $evaluation->period->ends_on]));
                })
                ->with(['scheduleSlots' => fn ($query) => $query->whereBetween('date', [$evaluation->period->starts_on, $evaluation->period->ends_on])->orderBy('date')->orderBy('period_number'), 'tasks.levels', 'tasks.educationPlanCompetency', 'tasks.expectations'])
                ->orderByRaw('COALESCE(assessed_on, created_at)')
                ->get();
            $studentLevels = DB::table('student_assessment_results')
                ->where('student_id', $evaluation->student_id)
                ->whereIn('assessment_id', $lses->pluck('id'))
                ->get(['assessment_id', 'assessment_task_id', 'level', 'points'])
                ->groupBy('assessment_id');
            $levelOrder = ['G' => 1, 'M' => 2, 'E' => 3];
            $lses = $lses->map(function ($assessment) use ($studentLevels, $levelOrder, $evaluation): array {
                $studentResultRows = collect($studentLevels->get($assessment->id, []));
                $maxTotal = 0;
                $pointsTotal = 0;
                foreach ($studentResultRows as $resultRow) {
                    $task = $assessment->tasks->firstWhere('id', $resultRow->assessment_task_id);
                    $maxPoints = $task?->max_points !== null ? (int) $task->max_points : $task?->maximumPoints();
                    if ($resultRow->points === null || ! $maxPoints) {
                        continue;
                    }
                    $maxTotal += $maxPoints;
                    $pointsTotal += min($maxPoints, max(0, (float) $resultRow->points));
                }
                $percentage = $maxTotal > 0 ? round_percentage($pointsTotal / $maxTotal * 100) : null;
                $levels = $studentResultRows->pluck('level')->filter()->unique();
                if ($levels->isEmpty()) {
                    $levels = $assessment->tasks
                        ->filter(fn ($task) => $studentResultRows->pluck('assessment_task_id')->contains($task->id))
                        ->flatMap(fn ($task) => $task->levels->pluck('level')->whenEmpty(fn ($levels) => collect([$task->level])))
                        ->filter()->unique();
                }
                $levels = $levels->sortBy(fn ($level) => $levelOrder[$level] ?? 99)->values();
                $configuredLevels = $assessment->tasks->flatMap(fn ($task) => $task->levels->pluck('level')->whenEmpty(fn ($levels) => collect([$task->level])))->filter()->unique();
                $competenceResults = $studentResultRows
                    ->mapToGroups(function ($resultRow) use ($assessment): array {
                        $task = $assessment->tasks->firstWhere('id', $resultRow->assessment_task_id);
                        $maxPoints = $task?->max_points !== null ? (int) $task->max_points : $task?->maximumPoints();
                        $competenceKey = $task?->education_plan_competency_id ? 'education_plan:'.$task->education_plan_competency_id : null;

                        return $competenceKey ? [$competenceKey => ['points' => $resultRow->points, 'max_points' => $maxPoints, 'weight' => (int) ($task->pivot->weight ?? 50)]] : [];
                    })
                    ->map(function ($results): ?int {
                        $maxPoints = $results->sum('max_points');
                        if ($maxPoints <= 0 || $results->every(fn (array $result): bool => $result['points'] === null)) {
                            return null;
                        }

                        $weightTotal = $results->sum('weight');
                        $percentage = $weightTotal > 0
                            ? $results->sum(fn (array $result): float => ($result['max_points'] > 0 ? min(100, max(0, (float) $result['points'] / $result['max_points'] * 100)) : 0) * $result['weight']) / $weightTotal
                            : $results->sum('points') / $maxPoints * 100;

                        return round_percentage($percentage);
                    })
                    ->filter(fn (?int $percentage): bool => $percentage !== null);

                return ['id' => $assessment->id, 'title' => $assessment->title, 'date' => $assessment->scheduleSlots->first()?->date?->toDateString() ?? $assessment->assessed_on?->toDateString(), 'student_levels' => $levels->all(), 'configured_levels' => $configuredLevels->sortBy(fn ($level) => $levelOrder[$level] ?? 99)->values()->all(), 'percentage' => $percentage, 'grade' => $percentage === null ? null : percentage_to_grade($percentage), 'receives_grades' => (bool) $evaluation->student->receives_grades, 'competence_results' => $competenceResults->all()];
            })->values();
            $periodLevel ??= $lses->flatMap->configured_levels->sortBy(fn ($level) => $levelOrder[$level] ?? 99)->first();
        }
        $customProcessCompetences = $teachingGroup->grading_model === 'observation_scales'
            ? $teachingGroup->school->customProcessCompetences()->where('is_active', true)->get(['id', 'text', 'position'])
            : collect();
        $competencies = collect();
        $competenceAverages = collect();
        if ($teachingGroup->grading_model === 'observation_scales') {
            $averages = CompetenceEvidence::query()
                ->selectRaw('custom_process_competence_id, AVG(custom_scale_level) AS average')
                ->where('student_id', $evaluation->student_id)
                ->whereNotNull('custom_process_competence_id')
                ->whereNotNull('custom_scale_level')
                ->whereHas('scheduledLesson.slot', function ($query) use ($teachingGroup, $evaluation): void {
                    $query->where('teaching_group_id', $teachingGroup->id)
                        ->whereBetween('date', [$evaluation->period->starts_on, $evaluation->period->ends_on]);
                })
                ->groupBy('custom_process_competence_id')
                ->get()
                ->keyBy('custom_process_competence_id');
            $competenceAverages = $customProcessCompetences->map(function ($competence) use ($averages, $teachingGroup): array {
                $average = $averages->get($competence->id)?->average;

                return [
                    'custom_process_competence_id' => $competence->id,
                    'average' => $average !== null ? round((float) $average, 2) : null,
                    'rounded_level' => $average !== null ? (int) round((float) $average) : null,
                    'interval_count' => $teachingGroup->school->observation_scale_interval_count,
                ];
            })->values();
        } elseif ($teachingGroup->grading_model === 'competency_texts_and_grades') {
            $observationEvidences = CompetenceEvidence::query()
                ->with(['scheduledLesson.lesson', 'scheduledLesson.slot'])
                ->where('student_id', $evaluation->student_id)
                ->whereNotNull('education_plan_competency_id')
                ->whereNotNull('scale')
                ->where('scale', '!=', '')
                ->whereHas('scheduledLesson.slot', function ($query) use ($teachingGroup, $evaluation): void {
                    $query->where('teaching_group_id', $teachingGroup->id)
                        ->whereBetween('date', [$evaluation->period->starts_on, $evaluation->period->ends_on]);
                })
                ->get();
            $competencies = EducationPlanCompetency::query()
                ->whereHas('lessons.unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))
                ->whereHas('lessons.scheduledLessons.slot', function ($query) use ($teachingGroup, $evaluation): void {
                    $query->where('teaching_group_id', $teachingGroup->id)
                        ->whereBetween('date', [$evaluation->period->starts_on, $evaluation->period->ends_on]);
                })
                ->with(['area', 'variants'])
                ->orderBy('id')
                ->get();
            $observationSources = $observationEvidences->groupBy('education_plan_competency_id')->map(fn ($evidences) => $evidences->map(function ($evidence): array {
                $rating = (float) $evidence->scale <= 0 ? '0' : str_repeat('★', (int) round((float) $evidence->scale));

                return ['type' => 'observations', 'title' => $evidence->scheduledLesson?->slot?->date?->format('d.m.Y') ?: 'Beobachtung', 'date' => $evidence->scheduledLesson?->slot?->date?->toDateString(), 'percentage' => round_percentage((float) $evidence->scale / 5 * 100), 'text' => ($evidence->scheduledLesson?->slot?->date?->format('d.m.Y') ?: 'Beobachtung').': '.$rating];
            })->values());
            $writtenWeight = (int) ($teachingGroup->gradeComponents->firstWhere('type', 'written_assessments')?->percentage ?? 0);
            $observationWeight = (int) ($teachingGroup->gradeComponents->firstWhere('type', 'observations')?->percentage ?? 0);
            $competenceAverages = $competencies->map(function ($competence) use ($lses, $observationSources, $writtenWeight, $observationWeight): array {
                $lseSources = $lses->flatMap(fn (array $lse): array => collect($lse['competence_results']['education_plan:'.$competence->id] ?? [])->map(fn (int $percentage): array => ['type' => 'written_assessments', 'title' => $lse['title'], 'date' => $lse['date'], 'percentage' => $percentage, 'grade' => $lse['grade'], 'text' => ($lse['date'] ? date('d.m.Y', strtotime($lse['date'])) : '–').': '.$lse['title'].' ('.(implode(', ', $lse['student_levels']) ?: '–').'): '.$percentage.'% / '.($lse['grade'] ?? '–')])->all())->values();
                $sources = $lseSources->concat($observationSources->get($competence->id, collect()))->sortBy('date')->values();
                $sourceAverages = collect([
                    'written_assessments' => [$lseSources, $writtenWeight],
                    'observations' => [$observationSources->get($competence->id, collect()), $observationWeight],
                ])->map(function (array $source): ?array {
                    if ($source[0]->isEmpty() || $source[1] <= 0) {
                        return null;
                    }

                    return ['average' => $source[0]->avg('percentage'), 'weight' => $source[1]];
                })->filter();
                $weightTotal = $sourceAverages->sum('weight');
                $percentage = $weightTotal > 0 ? $sourceAverages->sum(fn (array $source): float => $source['average'] * $source['weight']) / $weightTotal : null;
                $average = $percentage !== null ? round($percentage / 20, 2) : null;

                return ['education_plan_competency_id' => $competence->id, 'average' => $average, 'rounded_level' => $average !== null ? (int) round($average) : null, 'percentage' => $percentage !== null ? round_percentage($percentage) : null, 'sources' => $sources->all()];
            })->values();
            $competencies = $competencies->map(function ($competence) use ($competencyResolver, $periodLevel): array {
                $presented = $competencyResolver->present($competence);
                $presented['education_plan_competency_id'] = $competence->id;
                $presented['level_texts'] = collect(['G', 'M', 'E'])->mapWithKeys(fn (string $level): array => [$level => $competencyResolver->textForLevel($competence, $level)])->all();
                $presented['text'] = $presented['level_texts'][$periodLevel] ?? $presented['text'];
                $presented['label'] = $presented['text'];

                return $presented;
            })->values();
        }
        $gradeComponents = $this->gradeComponents($teachingGroup, $evaluation, $evaluation->competenceRatings);

        return Inertia::render('Evaluations/Edit', [
            'group' => $teachingGroup,
            'evaluation' => $evaluation,
            'customProcessCompetences' => $customProcessCompetences,
            'customProcessCompetenceScaleIntervalCount' => $teachingGroup->school->observation_scale_interval_count,
            'competencies' => $competencies,
            'competenceAverages' => $competenceAverages,
            'previousEvaluation' => $navigation['previous'],
            'nextEvaluation' => $navigation['next'],
            'lses' => $lses,
            'periodLevel' => $periodLevel,
            'gradeComponents' => $gradeComponents,
        ]);
    }

    public function draftText(Request $request, TeachingGroup $teachingGroup, StudentEvaluation $evaluation, EvaluationTemplateGenerator $templateGenerator)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($evaluation->period->teaching_group_id === $teachingGroup->id, 404);
        abort_unless($teachingGroup->grading_model === 'competency_texts_and_grades', 404);
        abort_if($evaluation->status === 'confirmed', 422, 'Eine bestätigte Bewertung kann nicht mehr geändert werden.');
        $data = $request->validate(['level' => ['nullable', 'in:G,M,E'], 'competence_ratings' => ['sometimes', 'array'], 'competence_ratings.*.education_plan_competency_id' => ['required', 'integer'], 'competence_ratings.*.rating' => ['nullable', 'integer', 'between:0,5'], 'competence_ratings.*.include_in_text' => ['sometimes', 'boolean'], 'competence_ratings.*.include_in_grade' => ['sometimes', 'boolean']]);
        $ratings = collect($data['competence_ratings'] ?? []);
        $allowedCompetenceIds = EducationPlanCompetency::query()->whereHas('lessons.unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->pluck('education_plan_competencies.id');
        abort_unless($ratings->pluck('education_plan_competency_id')->diff($allowedCompetenceIds)->isEmpty(), 422);

        return response()->json(['draft_text' => $templateGenerator->draft($evaluation->period, $evaluation->student, $ratings, $data['level'] ?? null), 'grade_components' => $this->gradeComponents($teachingGroup, $evaluation, $ratings)]);
    }

    private function gradeComponents(TeachingGroup $teachingGroup, StudentEvaluation $evaluation, $ratings): array
    {
        $ratings = collect($ratings)->map(fn ($rating): array => is_array($rating) ? $rating : $rating->toArray());
        $components = $teachingGroup->gradeComponents->map(fn ($component): array => ['type' => $component->type, 'label' => $component->label, 'weight' => $component->percentage, 'percentage' => null, 'grade' => null, 'sources' => []]);
        if (! in_array($teachingGroup->grading_model, ['competency_texts_and_grades', 'grades_only'], true)) {
            return $components->values()->all();
        }

        $period = $evaluation->period;
        $numericGrades = $teachingGroup->grading_model === 'grades_only' || ($teachingGroup->grading_model === 'competency_texts_and_grades' && $teachingGroup->numeric_grades_enabled);
        $start = $period->starts_on;
        $end = $period->ends_on;
        if ($numericGrades && $period->include_full_school_year && $teachingGroup->schoolYear) {
            $start = $teachingGroup->schoolYear->starts_on;
            $end = $teachingGroup->schoolYear->ends_on;
        }
        $assessments = $teachingGroup->assessments()
            ->where(function ($query) use ($evaluation, $start, $end): void {
                $query->where('report_period_id', $evaluation->period_id)
                    ->orWhereBetween('assessed_on', [$start, $end])
                    ->orWhereHas('scheduleSlots', fn ($slotQuery) => $slotQuery->whereBetween('date', [$start, $end]));
            })
            ->with(['scheduleSlots' => fn ($query) => $query->whereBetween('date', [$start, $end]), 'tasks.levels'])
            ->get();
        $results = DB::table('student_assessment_results')->where('student_id', $evaluation->student_id)->whereIn('assessment_id', $assessments->pluck('id'))->get()->groupBy('assessment_id');
        $writtenSources = $assessments->map(function ($assessment) use ($results): ?array {
            $rows = $results->get($assessment->id, collect());
            $max = 0;
            $points = 0;
            foreach ($rows as $row) {
                $task = $assessment->tasks->firstWhere('id', $row->assessment_task_id);
                $taskMax = $task?->max_points !== null ? (int) $task->max_points : $task?->maximumPoints();
                if ($row->points !== null && $taskMax) {
                    $max += $taskMax;
                    $points += min($taskMax, max(0, (float) $row->points));
                }
            }

            if ($max <= 0) {
                return null;
            }
            $percentage = round_percentage($points / $max * 100);
            $levels = $rows->pluck('level')->filter()->unique()->implode(', ');
            if ($levels === '') {
                $levels = $assessment->tasks->flatMap->levels->pluck('level')->filter()->unique()->implode(', ');
            }

            $date = $assessment->scheduleSlots->first()?->date?->format('d.m.Y') ?? $assessment->assessed_on?->format('d.m.Y') ?? '–';

            return ['percentage' => $percentage, 'date' => $assessment->scheduleSlots->first()?->date?->toDateString() ?? $assessment->assessed_on?->toDateString(), 'text' => $date.': '.$assessment->title.' ('.($levels ?: '–').'): '.$percentage.'% / '.percentage_to_grade($percentage)];
        })->filter();
        $writtenPercentages = $writtenSources->pluck('percentage');
        $available = EducationPlanCompetency::query()
            ->whereHas('lessons.unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))
            ->whereHas('lessons.scheduledLessons.slot', fn ($query) => $query->whereBetween('date', [$start, $end]))
            ->pluck('education_plan_competencies.id');
        $excluded = collect($ratings)->filter(fn (array $rating): bool => ($rating['include_in_grade'] ?? true) === false)->pluck('education_plan_competency_id');
        $included = $available->diff($excluded);
        $observationPercentages = CompetenceEvidence::query()->with('educationPlanCompetency')->where('student_id', $evaluation->student_id)->whereNotNull('education_plan_competency_id')->whereNotNull('scale')->where('scale', '!=', '')->whereHas('scheduledLesson.slot', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id)->whereBetween('date', [$start, $end]))->get()->filter(fn ($evidence): bool => $included->contains($evidence->education_plan_competency_id))->map(fn ($evidence): int => round_percentage((float) $evidence->scale / 5 * 100));

        $observationSources = CompetenceEvidence::query()->with(['educationPlanCompetency', 'scheduledLesson.slot'])->where('student_id', $evaluation->student_id)->whereNotNull('education_plan_competency_id')->whereHas('scheduledLesson.slot', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id)->whereBetween('date', [$start, $end]))->get()->filter(fn ($evidence): bool => $included->contains($evidence->education_plan_competency_id))->map(function ($evidence): array {
            $rating = $evidence->scale === null || $evidence->scale === '' ? '-' : ((float) $evidence->scale <= 0 ? '0' : str_repeat('★', (int) round((float) $evidence->scale)));

            return ['percentage' => round_percentage((float) $evidence->scale / 5 * 100), 'text' => ($evidence->scheduledLesson?->slot?->date?->format('d.m.Y') ?? 'Beobachtung').': '.$rating];
        })->values();

        return $components->map(function (array $component) use ($writtenPercentages, $writtenSources, $observationPercentages, $observationSources): array {
            $values = match ($component['type']) {
                'written_assessments' => $writtenPercentages,
                'observations' => $observationPercentages,
                default => collect(),
            };
            $component['percentage'] = $values->isNotEmpty() ? round_percentage($values->avg()) : null;
            $component['grade'] = $component['percentage'] === null ? null : percentage_to_grade($component['percentage']);
            $component['sources'] = match ($component['type']) {
                'written_assessments' => $writtenSources->sortBy('date')->pluck('text')->values()->all(),
                'observations' => $observationSources->sortBy('date')->pluck('text')->values()->all(),
                default => [],
            };

            return $component;
        })->values()->all();
    }

    public function update(Request $request, TeachingGroup $teachingGroup, StudentEvaluation $evaluation)
    {
        $this->authorize('update', $teachingGroup);
        abort_unless($evaluation->period->teaching_group_id === $teachingGroup->id, 404);
        $data = $request->validate(['draft_text' => ['nullable', 'string', 'max:10000'], 'teacher_note' => ['nullable', 'string', 'max:5000'], 'level' => ['nullable', 'in:G,M,E'], 'status' => ['required', 'in:draft,confirmed'], 'observation_scales' => ['sometimes', 'array'], 'observation_scales.*.custom_process_competence_id' => ['required', 'integer'], 'observation_scales.*.custom_scale_level' => ['nullable', 'integer'], 'observation_scales.*.custom_scale_status' => ['nullable', 'in:ne'], 'competence_ratings' => ['sometimes', 'array'], 'competence_ratings.*.education_plan_competency_id' => ['required', 'integer'], 'competence_ratings.*.rating' => ['nullable', 'integer', 'between:0,5'], 'competence_ratings.*.include_in_text' => ['sometimes', 'boolean'], 'competence_ratings.*.include_in_grade' => ['sometimes', 'boolean']]);
        abort_if($evaluation->status === 'confirmed' && $data['status'] !== 'draft', 422, 'Eine bestätigte Bewertung kann nur zurückgesetzt werden.');
        $customCompetences = $teachingGroup->school->customProcessCompetences()->where('is_active', true)->get(['id', 'text', 'position']);
        $submittedScales = collect($data['observation_scales'] ?? []);
        if ($teachingGroup->grading_model === 'observation_scales') {
            foreach ($submittedScales as $scale) {
                $competence = $customCompetences->firstWhere('id', $scale['custom_process_competence_id']);
                abort_unless($competence, 422);
                $hasLevel = filled($scale['custom_scale_level'] ?? null);
                $hasStatus = ($scale['custom_scale_status'] ?? null) === 'ne';
                abort_unless($hasLevel xor $hasStatus, 422);
                abort_unless(! $hasLevel || ((int) $scale['custom_scale_level'] >= 1 && (int) $scale['custom_scale_level'] <= $teachingGroup->school->observation_scale_interval_count), 422);
            }
        } else {
            $submittedScales = collect();
        }
        $submittedRatings = collect($data['competence_ratings'] ?? []);
        if ($teachingGroup->grading_model === 'competency_texts_and_grades') {
            $allowedCompetenceIds = EducationPlanCompetency::query()->whereHas('lessons.unit', fn ($query) => $query->where('teaching_group_id', $teachingGroup->id))->pluck('education_plan_competencies.id');
            abort_unless($submittedRatings->pluck('education_plan_competency_id')->diff($allowedCompetenceIds)->isEmpty(), 422);
        } else {
            $submittedRatings = collect();
        }
        DB::transaction(function () use ($data, $evaluation, $teachingGroup, $customCompetences, $submittedScales, $submittedRatings): void {
            $evaluation->update([...collect($data)->only(['draft_text', 'teacher_note', 'level', 'status'])->all(), 'confirmed_at' => $data['status'] === 'confirmed' ? now() : null]);
            $evaluation->observationScales()->delete();
            foreach ($submittedScales as $scale) {
                $competence = $customCompetences->firstWhere('id', $scale['custom_process_competence_id']);
                $evaluation->observationScales()->create(['custom_process_competence_id' => $competence->id, 'competence_text_snapshot' => $competence->text, 'position_snapshot' => $competence->position, 'interval_count_snapshot' => $teachingGroup->school->observation_scale_interval_count, 'custom_scale_level' => $scale['custom_scale_level'] ?? null, 'custom_scale_status' => $scale['custom_scale_status'] ?? null]);
            }
            $evaluation->competenceRatings()->delete();
            foreach ($submittedRatings as $rating) {
                $evaluation->competenceRatings()->create($rating);
            }
        });

        $nextEvaluation = $this->evaluationNavigation($evaluation)['next'];

        if ($nextEvaluation) {
            return to_route('evaluations.edit', [$teachingGroup, $nextEvaluation['id']])->with('success', 'Bewertungsentwurf wurde gespeichert.');
        }

        return to_route('evaluations.group-index', [$teachingGroup])->with('success', 'Bewertungsentwurf wurde gespeichert.');
    }

    private function evaluationNavigation(StudentEvaluation $evaluation): array
    {
        $evaluations = $evaluation->period->evaluations()->with('student')->get()->sortBy(fn ($item): string => mb_strtolower($item->student->last_name.' '.$item->student->first_name))->values();
        $index = $evaluations->search(fn ($item): bool => $item->id === $evaluation->id);

        $present = fn ($item): ?array => $item ? ['id' => $item->id, 'student_name' => $item->student->last_name.', '.$item->student->first_name] : null;

        return [
            'previous' => $present($evaluations->get($index - 1)),
            'next' => $present($evaluations->get($index + 1)),
        ];
    }
}
