<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportStudentsRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\StoreTeachingGroupMembershipRequest;
use App\Http\Requests\StoreTeachingGroupRequest;
use App\Http\Requests\StoreTimetableSlotRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Requests\UpdateTeachingGroupCurriculaRequest;
use App\Http\Requests\UpdateTeachingGroupGradingSettingsRequest;
use App\Http\Requests\UpdateTeachingGroupPeriodsRequest;
use App\Http\Requests\UpdateTeachingGroupRitualsRequest;
use App\Models\Curriculum;
use App\Models\CurriculumEducationPlanBinding;
use App\Models\CurriculumTopicEducationPlanReference;
use App\Models\EducationPlanCompetency;
use App\Models\PhaseTemplate;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\SongVersion;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Services\CompetencyResolver;
use App\Services\SongbookContentsResolver;
use App\Services\SongbookPdfExporter;
use App\Students\PronounSets\PronounSets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeachingGroupController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', TeachingGroup::class);
        $userId = auth()->user()->id;

        return Inertia::render('TeachingGroups/Index', [
            'groups' => TeachingGroup::where('user_id', $userId)->with(['school:id,name', 'schoolYear:id,name', 'gradeLevels:id,teaching_group_id,grade_level'])->withCount('students')->orderBy('name')->get(),
            'schools' => School::where('user_id', $userId)->with('schoolYears:id,school_id,name')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(TeachingGroup $teachingGroup, SongbookContentsResolver $contentsResolver, CompetencyResolver $competencyResolver): Response
    {
        $this->authorize('view', $teachingGroup);
        $teachingGroup->load(['school:id,name', 'schoolYear:id,name,starts_on,ends_on', 'gradeLevels', 'gradeComponents', 'students:id,school_id,first_name,last_name,class_name,notes,receives_grades,pronoun_set,denomination', 'timetableSlots', 'curricula:id,title,denominations', 'schoolPeriods:id,school_id,period_number,starts_at,ends_at', 'rituals.phaseTemplate:id,title,duration_minutes', 'songbook.entries.songVersion.song', 'songbook.entries.songVersion.sheet', 'songbook.entries.songVersion.chordSets', 'assessments.tasks', 'reportPeriods.evaluations.student']);
        $userId = auth()->user()->id;
        $gradeLevels = $teachingGroup->gradeLevels->pluck('grade_level')->map(fn ($grade) => (int) preg_replace('/\D+/', '', (string) $grade))->filter();
        $planCompetencies = CurriculumTopicEducationPlanReference::query()
            ->whereHas('topic.version', fn ($query) => $query->whereIn('curriculum_id', $teachingGroup->curricula->pluck('id')))
            ->whereHas('topic', fn ($query) => $query->whereIn('year', $gradeLevels))
            ->forGroup($teachingGroup)->with(['topic:id,title,year', 'educationPlanCompetency.area:id,kind'])->orderBy('competency_kind')->orderBy('position')->get();
        $plannedCompetencies = $teachingGroup->teachingUnits()->with(['lessons:id,teaching_unit_id,duration', 'lessons.educationPlanCompetencies:id'])->get()
            ->flatMap(fn ($unit) => $unit->lessons->flatMap(fn ($lesson) => $lesson->educationPlanCompetencies->map(fn ($competency) => ['curriculum_id' => $competency->pivot->curriculum_topic_education_plan_reference_id, 'education_id' => $competency->id, 'hours' => $lesson->duration])))
            ->groupBy('curriculum_id');
        $coveredEducationHours = $plannedCompetencies->filter(fn ($items, $id) => $id === '' || $id === null)->flatten(1)->groupBy('education_id')->map(fn ($items) => $items->sum('hours'));
        $coveredHours = $plannedCompetencies->reject(fn ($items, $id) => $id === '' || $id === null)->map(fn ($items) => $items->sum('hours'));
        $competencies = $planCompetencies->map(function ($competency) use ($competencyResolver, $coveredHours, $coveredEducationHours): array {
            $presentation = $competencyResolver->present($competency);
            $text = $presentation['text'] ?: $competency->educationPlanCompetency?->text;
            $presentation['text'] = $text;
            $presentation['label'] = $presentation['identifier'] && $text ? $presentation['identifier'].' – '.$text : ($text ?: $presentation['identifier']);

            return [
                'id' => $competency->id, 'topic_id' => $competency->topic->id, 'topic_title' => $competency->topic->title,
                'grade' => $competency->topic->year, 'kind' => $competency->competency_kind, 'denomination' => $competency->denomination,
                'education_plan_competency_id' => $competency->education_plan_competency_id,
                'presentation' => $presentation, 'missing_from_curriculum' => false,
                'covered_hours' => $coveredHours->get($competency->id, 0) ?: $coveredEducationHours->get($competency->education_plan_competency_id, 0),
            ];
        })->values();
        $curriculumEducationIds = CurriculumTopicEducationPlanReference::query()
            ->whereHas('topic.version', fn ($query) => $query->whereIn('curriculum_id', $teachingGroup->curricula->pluck('id')))
            ->forGroup($teachingGroup)->pluck('education_plan_competency_id')->filter()->unique();
        $curriculumEducationPlanReferenceIdentifiers = CurriculumTopicEducationPlanReference::query()
            ->whereHas('topic.version', fn ($query) => $query->whereIn('curriculum_id', $teachingGroup->curricula->pluck('id')))
            ->forGroup($teachingGroup)->with('educationPlanCompetency:id,external_identifier')->get()->pluck('educationPlanCompetency.external_identifier')->filter()->unique();
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
            ->orderBy('external_identifier')->get();
        $missingCompetencies = $missingCompetencies
            ->reject(fn ($competency) => $curriculumEducationPlanReferenceIdentifiers->contains($competency->external_identifier))
            ->map(function ($competency) use ($competencyResolver, $coveredEducationHours): array {
                $presentation = $competencyResolver->present($competency);

                return [
                    'id' => 'education-'.$competency->id, 'education_plan_competency_id' => $competency->id,
                    'topic_id' => null, 'topic_title' => null, 'grade' => null, 'kind' => $competency->area->kind,
                    'denomination' => null, 'presentation' => $presentation, 'missing_from_curriculum' => true,
                    'covered_hours' => $coveredEducationHours->get($competency->id, 0),
                ];
            })->values();
        $competencies = $competencies->concat($missingCompetencies)->map(function (array $competency) use ($educationPlanAreas): array {
            $area = $competency['education_plan_competency_id']
                ? $educationPlanAreas->get($competency['presentation']['identifier'])
                : null;
            $area ??= $educationPlanAreas->get($competency['presentation']['identifier']);
            $competency['area'] = $area ? ['identifier' => $area->external_identifier, 'title' => $area->title] : null;

            return $competency;
        })->groupBy(fn (array $competency) => $competency['presentation']['identifier'] ?: $competency['id'])
            ->map(function ($items): array {
                $competency = $items->first();
                $competency['covered_hours'] = $items->sum('covered_hours');
                $competency['missing_from_curriculum'] = $items->every(fn (array $item) => $item['missing_from_curriculum']);

                return $competency;
            })->values();
        $songbookVersions = $teachingGroup->songbook
            ? $contentsResolver->resolve($teachingGroup->songbook)
                ->map(fn ($entry) => $entry->songVersion)
                ->filter()
                ->unique('id')
                ->values()
            : collect();
        $teachingUnits = $teachingGroup->teachingUnits()
            ->with(['lessons:id,teaching_unit_id,title,position', 'lessons.scheduledLessons:id,lesson_id,schedule_slot_id,status', 'lessons.scheduledLessons.slot:id,date,period_number,starts_at,ends_at'])
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'teaching_group_id', 'title', 'position', 'introduction_text'])
            ->map(fn ($unit): array => [
                'id' => $unit->id,
                'title' => $unit->title,
                'position' => $unit->position,
                'introduction_text' => $unit->introduction_text,
                'public_url' => URL::signedRoute('public.teaching-units.show', ['teachingUnit' => $unit]),
                'lessons' => $unit->lessons->map(fn ($lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'position' => $lesson->position,
                    'scheduled_dates' => $lesson->scheduledLessons->map(fn ($scheduledLesson) => $scheduledLesson->slot?->date?->format('Y-m-d'))->filter()->values()->all(),
                ])->values()->all(),
            ])
            ->values();
        $parentLetterPreference = auth()->user()->preferences()->where('key', 'documents.parent-letter.format')->value('value');
        $parentLetterTemplatePreference = auth()->user()->preferences()->where('key', 'documents.parent-letter.template')->value('value');

        return Inertia::render('TeachingGroups/Show', [
            'group' => $teachingGroup,
            'pronounSets' => PronounSets::toArray(),
            'songbookVersions' => $songbookVersions,
            'students' => Student::where('user_id', $userId)->where('school_id', $teachingGroup->school_id)->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'class_name', 'notes', 'pronoun_set', 'denomination']),
            'curricula' => Curriculum::where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId))->orderBy('title')->get(['id', 'title']),
            'schoolPeriods' => $teachingGroup->school->periods()->orderBy('period_number')->get(['id', 'school_id', 'period_number', 'starts_at', 'ends_at']),
            'ritualPhaseTemplates' => PhaseTemplate::where('user_id', $userId)->where('is_active', true)->orderBy('position')->orderBy('title')->get(['id', 'title', 'duration_minutes']),
            'songVersions' => SongVersion::whereHas('song', fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId))->with('song:id,title')->orderBy('name')->get(),
            'assessments' => $teachingGroup->assessments->sortByDesc('assessed_on')->values(),
            'reportPeriods' => $teachingGroup->reportPeriods->sortByDesc('ends_on')->values(),
            'competencies' => $competencies,
            'teachingUnits' => $teachingUnits,
            'parentLetterFormat' => is_array($parentLetterPreference) ? ($parentLetterPreference['format'] ?? 'docx') : 'docx',
            'parentLetterTemplate' => is_array($parentLetterTemplatePreference) ? ($parentLetterTemplatePreference['template'] ?? 'primary-school-lower-secondary') : 'primary-school-lower-secondary',
            'denominationOptions' => $teachingGroup->curricula->flatMap(fn ($curriculum) => $curriculum->denominations ?? [])->filter()->unique()->values(),
        ]);
    }

    public function uploadSongbookTitlePage(Request $request, TeachingGroup $teachingGroup, SongbookPdfExporter $exporter): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['title_page' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:51200']]);
        $book = $teachingGroup->songbook()->firstOrCreate([]);
        if ($book->title_page_path) {
            Storage::disk('local')->delete($book->title_page_path);
        }
        if ($book->title_page_a4_path) {
            Storage::disk('local')->delete($book->title_page_a4_path);
        }
        $file = $data['title_page'];
        $titlePagePath = $file->storeAs('songbooks', Str::uuid().'.'.$file->getClientOriginalExtension(), 'local');
        $book->update(['title_page_path' => $titlePagePath, 'title_page_a4_path' => $exporter->generateTitlePageA4($titlePagePath), 'title_page_original_name' => $file->getClientOriginalName(), 'title_page_mime_type' => $file->getMimeType(), 'title_page_size' => $file->getSize()]);

        return back()->with('success', 'Titelseite des Liederbuchs wurde gespeichert.');
    }

    public function songbookTitlePage(Request $request, TeachingGroup $teachingGroup)
    {
        $this->authorize('view', $teachingGroup);
        $book = $teachingGroup->songbook;
        abort_unless($book?->title_page_path, 404);

        return response()->file(Storage::disk('local')->path($book->title_page_path), ['Content-Type' => $book->title_page_mime_type ?: 'application/octet-stream']);
    }

    public function searchSongbookSongs(Request $request, TeachingGroup $teachingGroup)
    {
        $this->authorize('view', $teachingGroup);
        $query = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($query) < 2, 422, 'Die Suche benötigt mindestens zwei Zeichen.');

        return SongVersion::query()
            ->join('songs', 'songs.id', '=', 'song_versions.song_id')
            ->whereHas('song', fn ($song) => $song->where(fn ($scope) => $scope->whereNull('user_id')->orWhere('user_id', $teachingGroup->user_id))->where('title', 'like', "%{$query}%"))
            ->with('song:id,title')
            ->orderBy('songs.title')
            ->limit(20)
            ->get(['song_versions.id', 'song_versions.song_id', 'song_versions.name']);
    }

    public function updateRituals(UpdateTeachingGroupRitualsRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $ids = collect($request->validated()['phase_template_ids'] ?? [])->unique()->values();
        abort_unless(PhaseTemplate::where('user_id', $teachingGroup->user_id)->whereIn('id', $ids)->count() === $ids->count(), 422, 'Eine Phasen-Vorlage gehört nicht zu diesem Benutzerkonto.');
        $teachingGroup->rituals()->delete();
        $teachingGroup->rituals()->createMany($ids->values()->map(fn (int $id, int $position): array => ['user_id' => $teachingGroup->user_id, 'phase_template_id' => $id, 'position' => $position + 1])->all());

        return back()->with('success', 'Gruppenrituale wurden gespeichert.');
    }

    public function updatePeriods(UpdateTeachingGroupPeriodsRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $periods = collect($request->validated()['periods']);
        abort_if($periods->map(fn (array $period) => $period['school_period_id'].'-'.$period['weekday'])->duplicates()->isNotEmpty(), 422, 'Eine Stunde darf pro Wochentag nur einmal vorkommen.');
        $periodIds = $periods->pluck('school_period_id')->unique();
        abort_unless($teachingGroup->school->periods()->whereIn('id', $periodIds)->count() === $periodIds->count(), 422);
        DB::table('teaching_group_periods')->where('teaching_group_id', $teachingGroup->id)->delete();
        if ($periods->isNotEmpty()) {
            DB::table('teaching_group_periods')->insert($periods->map(fn (array $period) => ['teaching_group_id' => $teachingGroup->id, 'school_period_id' => $period['school_period_id'], 'weekday' => $period['weekday']])->all());
        }

        return back()->with('success', 'Regelmäßige Unterrichtsstunden wurden gespeichert.');
    }

    public function updateGradingSettings(UpdateTeachingGroupGradingSettingsRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validated();
        $components = collect($data['components'] ?? []);
        $labels = ['observations' => 'Beobachtungen im Unterricht', 'written_assessments' => 'Schriftliche Leistungen'];

        DB::transaction(function () use ($data, $components, $labels, $teachingGroup): void {
            $teachingGroup->update([
                'grading_model' => $data['grading_model'],
                'numeric_grades_enabled' => $data['grading_model'] === 'grades_only' || (bool) ($data['numeric_grades_enabled'] ?? false),
            ]);
            if ($data['grading_model'] === 'observation_scales' || ($data['grading_model'] === 'competency_texts_and_grades' && ! $teachingGroup->numeric_grades_enabled)) {
                return;
            }
            $teachingGroup->gradeComponents()->delete();
            $teachingGroup->gradeComponents()->createMany($components->values()->map(fn (array $component, int $position): array => [
                'type' => $component['type'],
                'label' => $labels[$component['type']] ?? trim($component['label']),
                'percentage' => $component['percentage'],
                'position' => $position + 1,
            ])->all());
        });

        return to_route('teaching-groups.show', ['teachingGroup' => $teachingGroup, 'tab' => 'evaluations'])->with('success', 'Bewertungseinstellungen wurden gespeichert.');
    }

    public function store(StoreTeachingGroupRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $school = School::whereKey($data['school_id'])->where('user_id', $request->user()->id)->firstOrFail();
        $schoolYear = SchoolYear::whereKey($data['school_year_id'])->where('user_id', $request->user()->id)->where('school_id', $school->id)->firstOrFail();

        $group = DB::transaction(function () use ($data, $request, $schoolYear): TeachingGroup {
            $group = TeachingGroup::create(collect($data)->only(['school_id', 'school_year_id', 'name', 'aktenzeichen', 'denomination', 'notes'])->merge(['user_id' => $request->user()->id])->all());
            $group->gradeLevels()->createMany(collect($data['grade_levels'])->map(fn (string $grade) => ['grade_level' => trim($grade)])->all());
            $group->gradeComponents()->createMany([
                ['type' => 'observations', 'label' => 'Beobachtungen im Unterricht', 'percentage' => 50, 'position' => 1],
                ['type' => 'written_assessments', 'label' => 'Schriftliche Leistungen', 'percentage' => 50, 'position' => 2],
            ]);
            $this->createDefaultReportPeriods($group, $schoolYear);

            return $group;
        });

        return to_route('teaching-groups.show', $group)->with('success', 'Unterrichtsgruppe wurde angelegt.');
    }

    private function createDefaultReportPeriods(TeachingGroup $teachingGroup, SchoolYear $schoolYear): void
    {
        $secondHalfStart = $schoolYear->second_half_start_on ?? $schoolYear->starts_on->copy()->addYear()->setMonth(2)->setDay(1);

        $teachingGroup->reportPeriods()->createMany([
            ['user_id' => $teachingGroup->user_id, 'label' => '1. Halbjahr', 'starts_on' => $schoolYear->starts_on, 'ends_on' => $secondHalfStart->copy()->subDay(), 'whole_grades' => false, 'include_full_school_year' => false],
            ['user_id' => $teachingGroup->user_id, 'label' => '2. Halbjahr', 'starts_on' => $secondHalfStart, 'ends_on' => $schoolYear->ends_on, 'whole_grades' => true, 'include_full_school_year' => true],
        ]);
    }

    public function update(StoreTeachingGroupRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validated();
        abort_unless($data['school_id'] === $teachingGroup->school_id && $data['school_year_id'] === $teachingGroup->school_year_id, 422);
        $nameChanged = $teachingGroup->name !== $data['name'];
        DB::transaction(function () use ($data, $teachingGroup): void {
            $teachingGroup->update(collect($data)->only(['name', 'aktenzeichen', 'denomination', 'notes'])->all());
            $teachingGroup->gradeLevels()->delete();
            $teachingGroup->gradeLevels()->createMany(collect($data['grade_levels'])->map(fn (string $grade) => ['grade_level' => trim($grade)])->all());
            if (array_key_exists('periods', $data)) {
                $periods = collect($data['periods']);
                abort_if($periods->map(fn (array $period) => $period['school_period_id'].'-'.$period['weekday'])->duplicates()->isNotEmpty(), 422, 'Eine Stunde darf pro Wochentag nur einmal vorkommen.');
                $periodIds = $periods->pluck('school_period_id')->unique();
                abort_unless($teachingGroup->school->periods()->whereIn('id', $periodIds)->count() === $periodIds->count(), 422);
                DB::table('teaching_group_periods')->where('teaching_group_id', $teachingGroup->id)->delete();
                if ($periods->isNotEmpty()) {
                    DB::table('teaching_group_periods')->insert($periods->map(fn (array $period) => ['teaching_group_id' => $teachingGroup->id, 'school_period_id' => $period['school_period_id'], 'weekday' => $period['weekday']])->all());
                }
            }
            if (array_key_exists('phase_template_ids', $data)) {
                $phaseTemplateIds = collect($data['phase_template_ids'])->unique()->values();
                abort_unless(PhaseTemplate::where('user_id', $teachingGroup->user_id)->whereIn('id', $phaseTemplateIds)->count() === $phaseTemplateIds->count(), 422, 'Eine Phasen-Vorlage gehört nicht zu diesem Benutzerkonto.');
                $teachingGroup->rituals()->delete();
                $teachingGroup->rituals()->createMany($phaseTemplateIds->map(fn (int $id, int $position): array => ['user_id' => $teachingGroup->user_id, 'phase_template_id' => $id, 'position' => $position + 1])->all());
            }
        });
        if ($nameChanged) {
        }

        return back()->with('success', 'Unterrichtsgruppe wurde gespeichert.');
    }

    public function storeStudent(StoreStudentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $school = School::whereKey($data['school_id'])->where('user_id', $request->user()->id)->firstOrFail();
        $student = Student::create($data + ['user_id' => $school->user_id]);

        return back()->with('success', 'Schüler:in wurde angelegt.');
    }

    public function storeStudentForGroup(StoreStudentRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validated();
        abort_unless((int) $data['school_id'] === $teachingGroup->school_id, 422);

        DB::transaction(function () use ($data, $teachingGroup): void {
            $student = Student::create($data + ['user_id' => $teachingGroup->user_id]);
            $teachingGroup->students()->attach($student->id);
            $this->ensurePeriodEvaluations($teachingGroup, collect([$student->id]));
        });

        return back()->with('success', 'Schüler:in wurde angelegt und der Gruppe zugeordnet.');
    }

    public function importStudents(ImportStudentsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $school = School::whereKey($data['school_id'])->where('user_id', $request->user()->id)->firstOrFail();
        $lines = file($request->file('students')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        abort_if(count($lines) < 2, 422, 'Die CSV-Datei enthält keine Schüler:innen.');
        $delimiter = str_contains($lines[0], ';') ? ';' : ',';
        $headers = array_map(fn (string $header): string => Str::lower(trim(ltrim($header, "\xEF\xBB\xBF"))), str_getcsv($lines[0], $delimiter));
        $aliases = ['vorname' => 'first_name', 'nachname' => 'last_name', 'klasse' => 'class_name', 'notizen' => 'notes', 'konfession' => 'denomination'];
        $headers = array_map(fn (string $header): string => $aliases[$header] ?? $header, $headers);
        abort_unless(collect(['first_name', 'last_name', 'class_name'])->diff($headers)->isEmpty(), 422, 'Die CSV-Datei benötigt die Spalten Vorname, Nachname und Klasse.');
        $createdStudents = collect();
        $created = 0;
        DB::transaction(function () use ($lines, $delimiter, $headers, $school, &$created, &$createdStudents): void {
            foreach (array_slice($lines, 1) as $line) {
                $values = str_getcsv($line, $delimiter);
                $row = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
                if (! trim((string) ($row['first_name'] ?? '')) || ! trim((string) ($row['last_name'] ?? '')) || ! trim((string) ($row['class_name'] ?? ''))) {
                    continue;
                }
                $createdStudents->push(Student::create([
                    'user_id' => $school->user_id,
                    'school_id' => $school->id,
                    'first_name' => trim($row['first_name']),
                    'last_name' => trim($row['last_name']),
                    'class_name' => trim($row['class_name']),
                    'notes' => filled($row['notes'] ?? null) ? trim($row['notes']) : null,
                    'denomination' => filled($row['denomination'] ?? null) ? trim($row['denomination']) : null,
                ]));
                $created++;
            }
        });

        return back()->with('success', $created.' Schüler:innen wurden importiert.');
    }

    public function updateStudent(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->validated());

        return back()->with('success', 'Schüler:in wurde gespeichert.');
    }

    public function destroyStudent(Student $student): RedirectResponse
    {
        $this->authorize('delete', $student);
        $student->delete();

        return back()->with('success', 'Schüler:in wurde gelöscht.');
    }

    public function storeMembership(StoreTeachingGroupMembershipRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validated();
        $studentIds = collect($data['student_ids'] ?? [$data['student_id']])->filter()->unique()->values();
        abort_unless(Student::whereIn('id', $studentIds)->where('user_id', $request->user()->id)->where('school_id', $teachingGroup->school_id)->count() === $studentIds->count(), 422);
        $pivot = collect($data)->only(['starts_on', 'ends_on'])->all();
        DB::transaction(function () use ($teachingGroup, $studentIds, $pivot): void {
            $teachingGroup->students()->syncWithoutDetaching($studentIds->mapWithKeys(fn (int $studentId): array => [$studentId => $pivot])->all());
            $this->ensurePeriodEvaluations($teachingGroup, $studentIds);
        });

        return back()->with('success', 'Schüler:in wurde der Gruppe zugeordnet.');
    }

    private function ensurePeriodEvaluations(TeachingGroup $teachingGroup, $studentIds): void
    {
        foreach ($teachingGroup->reportPeriods as $period) {
            foreach ($studentIds as $studentId) {
                $period->evaluations()->firstOrCreate(['student_id' => $studentId]);
            }
        }
    }

    public function destroyMembership(TeachingGroup $teachingGroup, Student $student): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $teachingGroup->students()->detach($student->id);

        return back()->with('success', 'Zuordnung wurde entfernt.');
    }

    public function storeTimetableSlot(StoreTimetableSlotRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $teachingGroup->timetableSlots()->create($request->validated());

        return back()->with('success', 'Stundenplantermin wurde hinzugefügt.');
    }

    public function updateCurricula(UpdateTeachingGroupCurriculaRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $assignments = collect($request->validated()['curriculum_assignments'] ?? []);
        abort_if($assignments->where('role', 'primary')->count() > 1, 422, 'Es kann nur ein primäres Curriculum geben.');
        $allowed = Curriculum::where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))->whereIn('id', $assignments->pluck('curriculum_id'))->count();
        abort_unless($allowed === $assignments->pluck('curriculum_id')->unique()->count(), 403);
        $teachingGroup->curricula()->sync($assignments->mapWithKeys(fn (array $assignment) => [$assignment['curriculum_id'] => ['role' => $assignment['role']]])->all());

        return back()->with('success', 'Curricula wurden zugeordnet.');
    }

    public function destroy(TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('delete', $teachingGroup);
        $teachingGroup->assessments()->get()->each->delete();
        $teachingGroup->delete();

        return to_route('teaching-groups.index')->with('success', 'Unterrichtsgruppe wurde gelöscht.');
    }
}
