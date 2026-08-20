<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportStudentsRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\StoreTeachingGroupMembershipRequest;
use App\Http\Requests\StoreTeachingGroupRequest;
use App\Http\Requests\StoreTimetableSlotRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Requests\UpdateTeachingGroupCurriculaRequest;
use App\Http\Requests\UpdateTeachingGroupPeriodsRequest;
use App\Http\Requests\UpdateTeachingGroupRitualsRequest;
use App\Models\Curriculum;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\PhaseTemplate;
use App\Models\SongVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TeachingGroupController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', TeachingGroup::class);
        $organizationId = auth()->user()->organization_id;

        return Inertia::render('TeachingGroups/Index', [
            'groups' => TeachingGroup::where('organization_id', $organizationId)->with(['school:id,name', 'schoolYear:id,name', 'gradeLevels:id,teaching_group_id,grade_level'])->withCount('students')->orderBy('name')->get(),
            'schools' => School::where('organization_id', $organizationId)->with('schoolYears:id,school_id,name')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(TeachingGroup $teachingGroup): Response
    {
        $this->authorize('view', $teachingGroup);
        $teachingGroup->load(['school:id,name', 'schoolYear:id,name,starts_on,ends_on', 'gradeLevels', 'students:id,school_id,first_name,last_name,class_name,notes', 'timetableSlots', 'curricula:id,title', 'schoolPeriods:id,school_id,period_number,starts_at,ends_at', 'rituals.phaseTemplate:id,title,duration_minutes', 'songbook.entries.songVersion.song', 'songbook.entries.songVersion.sheet']);
        $organizationId = auth()->user()->organization_id;

        return Inertia::render('TeachingGroups/Show', [
            'group' => $teachingGroup,
            'students' => Student::where('organization_id', $organizationId)->where('school_id', $teachingGroup->school_id)->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'class_name', 'notes']),
            'curricula' => Curriculum::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))->orderBy('title')->get(['id', 'title']),
            'schoolPeriods' => $teachingGroup->school->periods()->orderBy('period_number')->get(['id', 'school_id', 'period_number', 'starts_at', 'ends_at']),
            'ritualPhaseTemplates' => PhaseTemplate::where('organization_id', $organizationId)->where('is_active', true)->orderBy('position')->orderBy('title')->get(['id', 'title', 'duration_minutes']),
            'songVersions' => SongVersion::whereHas('song', fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))->with('song:id,title')->orderBy('name')->get(),
        ]);
    }

    public function uploadSongbookTitlePage(Request $request, TeachingGroup $teachingGroup): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['title_page' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:51200']]);
        $book = $teachingGroup->songbook()->firstOrCreate([]);
        if ($book->title_page_path) Storage::disk('local')->delete($book->title_page_path);
        $file = $data['title_page'];
        $book->update(['title_page_path' => $file->storeAs('songbooks', Str::uuid().'.'.$file->getClientOriginalExtension(), 'local'), 'title_page_original_name' => $file->getClientOriginalName(), 'title_page_mime_type' => $file->getMimeType(), 'title_page_size' => $file->getSize()]);
        return back()->with('success', 'Titelseite des Liederbuchs wurde gespeichert.');
    }

    public function songbookTitlePage(Request $request, TeachingGroup $teachingGroup)
    {
        $this->authorize('view', $teachingGroup);
        $book = $teachingGroup->songbook;
        abort_unless($book?->title_page_path, 404);
        return response()->file(Storage::disk('local')->path($book->title_page_path), ['Content-Type' => $book->title_page_mime_type ?: 'application/octet-stream']);
    }

    public function updateRituals(UpdateTeachingGroupRitualsRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $ids = collect($request->validated()['phase_template_ids'] ?? [])->unique()->values();
        abort_unless(PhaseTemplate::where('organization_id', $teachingGroup->organization_id)->whereIn('id', $ids)->count() === $ids->count(), 422, 'Eine Phasen-Vorlage gehört nicht zu dieser Organisation.');
        $teachingGroup->rituals()->delete();
        $teachingGroup->rituals()->createMany($ids->values()->map(fn (int $id, int $position): array => ['organization_id' => $teachingGroup->organization_id, 'phase_template_id' => $id, 'position' => $position + 1])->all());

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

    public function store(StoreTeachingGroupRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $school = School::whereKey($data['school_id'])->where('organization_id', $request->user()->organization_id)->firstOrFail();
        $schoolYear = SchoolYear::whereKey($data['school_year_id'])->where('organization_id', $request->user()->organization_id)->where('school_id', $school->id)->firstOrFail();

        $group = DB::transaction(function () use ($data, $request): TeachingGroup {
            $group = TeachingGroup::create(collect($data)->only(['school_id', 'school_year_id', 'name', 'aktenzeichen', 'notes'])->merge(['organization_id' => $request->user()->organization_id])->all());
            $group->gradeLevels()->createMany(collect($data['grade_levels'])->map(fn (string $grade) => ['grade_level' => trim($grade)])->all());

            return $group;
        });

        return to_route('teaching-groups.show', $group)->with('success', 'Unterrichtsgruppe wurde angelegt.');
    }

    public function update(StoreTeachingGroupRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validated();
        abort_unless($data['school_id'] === $teachingGroup->school_id && $data['school_year_id'] === $teachingGroup->school_year_id, 422);
        $nameChanged = $teachingGroup->name !== $data['name'];
        DB::transaction(function () use ($data, $teachingGroup): void {
            $teachingGroup->update(collect($data)->only(['name', 'aktenzeichen', 'notes'])->all());
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
                abort_unless(PhaseTemplate::where('organization_id', $teachingGroup->organization_id)->whereIn('id', $phaseTemplateIds)->count() === $phaseTemplateIds->count(), 422, 'Eine Phasen-Vorlage gehört nicht zu dieser Organisation.');
                $teachingGroup->rituals()->delete();
                $teachingGroup->rituals()->createMany($phaseTemplateIds->map(fn (int $id, int $position): array => ['organization_id' => $teachingGroup->organization_id, 'phase_template_id' => $id, 'position' => $position + 1])->all());
            }
        });
        if ($nameChanged) {
            $teachingGroup->students()->get()->each->searchable();
        }

        return back()->with('success', 'Unterrichtsgruppe wurde gespeichert.');
    }

    public function storeStudent(StoreStudentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $school = School::whereKey($data['school_id'])->where('organization_id', $request->user()->organization_id)->firstOrFail();
        $student = Student::create($data + ['organization_id' => $school->organization_id]);

        return back()->with('success', 'Schüler:in wurde angelegt.');
    }

    public function storeStudentForGroup(StoreStudentRequest $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validated();
        abort_unless((int) $data['school_id'] === $teachingGroup->school_id, 422);

        DB::transaction(function () use ($data, $teachingGroup): void {
            $student = Student::create($data + ['organization_id' => $teachingGroup->organization_id]);
            $teachingGroup->students()->attach($student->id);
            $student->searchable();
        });

        return back()->with('success', 'Schüler:in wurde angelegt und der Gruppe zugeordnet.');
    }

    public function importStudents(ImportStudentsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $school = School::whereKey($data['school_id'])->where('organization_id', $request->user()->organization_id)->firstOrFail();
        $lines = file($request->file('students')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        abort_if(count($lines) < 2, 422, 'Die CSV-Datei enthält keine Schüler:innen.');
        $delimiter = str_contains($lines[0], ';') ? ';' : ',';
        $headers = array_map(fn (string $header): string => Str::lower(trim(ltrim($header, "\xEF\xBB\xBF"))), str_getcsv($lines[0], $delimiter));
        $aliases = ['vorname' => 'first_name', 'nachname' => 'last_name', 'klasse' => 'class_name', 'notizen' => 'notes'];
        $headers = array_map(fn (string $header): string => $aliases[$header] ?? $header, $headers);
        abort_unless(collect(['first_name', 'last_name', 'class_name'])->diff($headers)->isEmpty(), 422, 'Die CSV-Datei benötigt die Spalten Vorname, Nachname und Klasse.');
        $created = 0;
        DB::transaction(function () use ($lines, $delimiter, $headers, $school, &$created): void {
            foreach (array_slice($lines, 1) as $line) {
                $values = str_getcsv($line, $delimiter);
                $row = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
                if (! trim((string) ($row['first_name'] ?? '')) || ! trim((string) ($row['last_name'] ?? '')) || ! trim((string) ($row['class_name'] ?? ''))) {
                    continue;
                }
                Student::create([
                    'organization_id' => $school->organization_id,
                    'school_id' => $school->id,
                    'first_name' => trim($row['first_name']),
                    'last_name' => trim($row['last_name']),
                    'class_name' => trim($row['class_name']),
                    'notes' => filled($row['notes'] ?? null) ? trim($row['notes']) : null,
                ]);
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
        abort_unless(Student::whereIn('id', $studentIds)->where('organization_id', $request->user()->organization_id)->where('school_id', $teachingGroup->school_id)->count() === $studentIds->count(), 422);
        $pivot = collect($data)->only(['starts_on', 'ends_on'])->all();
        $teachingGroup->students()->syncWithoutDetaching($studentIds->mapWithKeys(fn (int $studentId): array => [$studentId => $pivot])->all());
        Student::whereIn('id', $studentIds)->get()->each->searchable();

        return back()->with('success', 'Schüler:in wurde der Gruppe zugeordnet.');
    }

    public function destroyMembership(TeachingGroup $teachingGroup, Student $student): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $teachingGroup->students()->detach($student->id);
        $student->searchable();

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
        $allowed = Curriculum::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->whereIn('id', $assignments->pluck('curriculum_id'))->count();
        abort_unless($allowed === $assignments->pluck('curriculum_id')->unique()->count(), 403);
        $teachingGroup->curricula()->sync($assignments->mapWithKeys(fn (array $assignment) => [$assignment['curriculum_id'] => ['role' => $assignment['role']]])->all());

        return back()->with('success', 'Curricula wurden zugeordnet.');
    }

    public function destroy(TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('delete', $teachingGroup);
        $studentIds = $teachingGroup->students()->pluck('students.id');
        $teachingGroup->delete();
        Student::whereIn('id', $studentIds)->get()->each->searchable();

        return to_route('teaching-groups.index')->with('success', 'Unterrichtsgruppe wurde gelöscht.');
    }
}
