<?php

namespace App\Http\Controllers;

use App\Models\LessonTemplate;
use App\Models\MaterialItem;
use App\Models\PhaseTemplate;
use App\Models\ResourceLink;
use App\Models\ScheduleSlot;
use App\Models\SocialForm;
use App\Http\Requests\UpdateLessonExecutionRequest;
use App\Models\ScheduledLesson;
use App\Models\ObservationType;
use App\Models\AttendanceRecord;
use App\Models\Observation;
use App\Models\CompetenceEvidence;
use App\Services\CompetencyResolver;
use App\Services\WscDocInspector;
use App\Services\SongbookContentsResolver;
use App\Services\SongbookPdfExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LessonWorkspaceController extends Controller
{
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
            'scheduledLesson.lesson.materialItems',
            'scheduledLesson.lesson.songs.song:id,title,author,composer,copyright_notice',
            'scheduledLesson.lesson.unit.competencies.educationPlanCompetency.area',
            'scheduledLesson.lesson.unit.competencies.educationPlanCompetency.variants',
            'scheduledLesson.lesson.unit.competencies.curriculumCompetency',
            'scheduledLesson.lesson.phases.socialForm',
            'scheduledLesson.lesson.phases.resources',
            'scheduledLesson.lesson.phases.resourceLinks',
            'scheduledLesson.lesson.phases.materialItems',
            'scheduledLesson.lesson.phases.songs.song:id,title,author,composer,copyright_notice',
            'scheduledLesson.lesson.phases.songs.parts',
            'scheduledLesson.lesson.competencies.educationPlanCompetency.area',
            'scheduledLesson.lesson.competencies.curriculumCompetency',
        ]);
        $lesson = $scheduleSlot->scheduledLesson?->lesson;
        abort_unless($lesson, 404, 'Für diesen Termin ist keine Unterrichtsstunde eingeplant.');
        $gradeLevels = $group->gradeLevels()->pluck('grade_level')->map(fn ($grade) => (int) preg_replace('/\D+/', '', (string) $grade))->filter()->values();
        $curricula = $group->curricula()->with(['versions.topics' => fn ($query) => $query->whereIn('year', $gradeLevels), 'versions.topics.competencies' => fn ($query) => $query->forGroup($group), 'versions.topics.competencies.educationPlanCompetency.area'])->get();
        $competencyOptions = $curricula->flatMap(fn ($curriculum) => $curriculum->versions)->flatMap(fn ($version) => $version->topics)->flatMap(fn ($topic) => $topic->competencies)->unique('id')->values()->each(function ($competency) use ($competencyResolver): void {
            $competency->setAttribute('competency_presentation', $competencyResolver->present($competency));
            $competency->setAttribute('competency_area', ['identifier' => $competency->educationPlanCompetency?->area?->external_identifier, 'title' => $competency->educationPlanCompetency?->area?->title]);
        });
        $lesson->unit->competencies->each(fn ($competency) => $competency->setAttribute('competency_presentation', $competencyResolver->present($competency)));
        $lesson->resources->each(function ($resource) use ($lesson, $inspector): void {
            if ($resource->page_count === null && strtolower(pathinfo($resource->original_name, PATHINFO_EXTENSION)) === 'wscdoc') {
                $resource->page_count = $inspector->pageCount(Storage::disk('local')->path($resource->storage_path));
            }
            $resource->setAttribute('display_name', $this->resourceFilename($lesson->unit, $resource));
        });
        $lesson->phases->each(function ($phase): void {
            $phase->setAttribute('resource_ids', $phase->resources->pluck('id')->values());
            $phase->setAttribute('resource_link_ids', $phase->resourceLinks->pluck('id')->values());
            $phase->setAttribute('material_item_ids', $phase->materialItems->pluck('id')->values());
            $phase->setAttribute('song_ids', $phase->songs->pluck('id')->values());
        });
        $scheduledLesson = $scheduleSlot->scheduledLesson;
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
            ->map(fn ($competency) => $competencyResolver->present($competency))
            ->groupBy('kind')
            ->map(fn ($competencies) => $competencies->values())
            ->all();

        return Inertia::render('Lessons/Show', [
            'slot' => $scheduleSlot,
            'group' => $group,
            'lesson' => $lesson,
            'unit' => $lesson->unit,
            'phaseTemplates' => PhaseTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->with('socialForm:id,name')->orderBy('position')->orderBy('title')->get(['id', 'title', 'duration_minutes', 'social_form_id', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'material', 'media']),
            'socialForms' => SocialForm::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(['id', 'name']),
            'materialItems' => $lesson->unit->materialItems->merge($lesson->materialItems)->unique('id')->values(),
            'songs' => \App\Models\SongVersion::whereHas('song', fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->with('song:id,title,author,composer,copyright_notice')->orderBy('name')->get(),
            'resourceLinks' => ResourceLink::where('organization_id', $request->user()->organization_id)->where(function ($query) use ($lesson): void {
                $query->where('teaching_unit_id', $lesson->teaching_unit_id)->orWhere('lesson_id', $lesson->id);
            })->orderBy('title')->get(['id', 'teaching_unit_id', 'lesson_id', 'title', 'url', 'description']),
            'lessonTemplates' => LessonTemplate::where('organization_id', $request->user()->organization_id)->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'competencyOptions' => $competencyOptions,
            'targetCompetencies' => ['process' => $targetCompetencies['process'] ?? [], 'content' => $targetCompetencies['content'] ?? []],
            'observationStudents' => $groupStudents,
            'observationTypes' => $observationTypes,
            'attendanceRecords' => $scheduledLesson->attendanceRecords()->get(['student_id', 'status', 'note']),
            'observations' => $scheduledLesson->observations()->get(['student_id', 'observation_type_id', 'note']),
            'competenceEvidences' => $scheduledLesson->competenceEvidences()->get(['student_id', 'teaching_unit_competency_id', 'scale', 'note']),
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
            'students.*.evidences.*.competency_id' => ['required', 'integer'],
            'students.*.evidences.*.scale' => ['nullable', 'string', 'max:32'],
            'students.*.evidences.*.note' => ['nullable', 'string', 'max:2000'],
        ]);
        $studentIds = collect($data['students'])->pluck('student_id');
        abort_unless($studentIds->unique()->count() === $studentIds->count() && $studentIds->diff($students)->isEmpty(), 422);
        $typeIds = ObservationType::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->pluck('id');
        $competencyIds = $scheduledLesson->lesson->competencies()->pluck('teaching_unit_competencies.id');

        DB::transaction(function () use ($data, $scheduledLesson, $typeIds, $competencyIds): void {
            foreach ($data['students'] as $student) {
                AttendanceRecord::updateOrCreate(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student['student_id']], ['status' => $student['attendance'] ?? 'present', 'note' => $student['note'] ?? null]);
                Observation::where('scheduled_lesson_id', $scheduledLesson->id)->where('student_id', $student['student_id'])->delete();
                foreach (collect($student['observation_type_ids'] ?? [])->intersect($typeIds) as $typeId) {
                    Observation::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student['student_id'], 'observation_type_id' => $typeId, 'note' => $student['note'] ?? null]);
                }
                CompetenceEvidence::where('scheduled_lesson_id', $scheduledLesson->id)->where('student_id', $student['student_id'])->delete();
                foreach ($student['evidences'] ?? [] as $evidence) {
                    if ($competencyIds->contains($evidence['competency_id'])) {
                        CompetenceEvidence::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student['student_id'], 'teaching_unit_competency_id' => $evidence['competency_id'], 'scale' => $evidence['scale'] ?? null, 'note' => $evidence['note'] ?? null]);
                    }
                }
            }
        });
        return back()->with('success', 'Beobachtungen wurden gespeichert.');
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
        return trim((string) preg_replace(['/[^\pL\pN._ -]+/u', '/\s+/u', '/\.{2,}/'], ['-', ' ', '.'], $value), " .-");
    }

    public function updateExecution(UpdateLessonExecutionRequest $request, ScheduleSlot $scheduleSlot): \Illuminate\Http\RedirectResponse
    {
        $group = $scheduleSlot->group;
        $this->authorize('update', $group);
        $scheduledLesson = $scheduleSlot->scheduledLesson;
        abort_unless($scheduledLesson, 404);
        $scheduledLesson->update($request->validated());

        return back()->with('success', 'Durchführung wurde gespeichert.');
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
