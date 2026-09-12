<?php

namespace App\Http\Controllers;

use App\Models\Observation;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAssessmentResult;
use App\Models\StudentEvaluation;
use App\Models\StudentEvaluationCompetenceRating;
use App\Models\TeachingGroup;
use App\Students\PronounSets\PronounSets;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function show(Request $request, Student $student): Response
    {
        $this->authorize('view', $student);
        $student->load(['school:id,name', 'teachingGroups:id,name,school_id,school_year_id', 'teachingGroups.schoolYear:id,name,starts_on,ends_on', 'teachingGroups.school:id,name']);

        $availableSchoolYears = $student->teachingGroups->pluck('schoolYear')->filter()->unique('id')->sortByDesc('starts_on')->values();
        $selectedSchoolYearId = $request->integer('school_year') ?: $availableSchoolYears->first()?->id;
        if (! $availableSchoolYears->contains('id', $selectedSchoolYearId)) {
            $selectedSchoolYearId = $availableSchoolYears->first()?->id;
        }
        $selectedSchoolYear = $availableSchoolYears->firstWhere('id', $selectedSchoolYearId);
        $groupIds = $student->teachingGroups->where('school_year_id', $selectedSchoolYearId)->pluck('id');

        $observations = Observation::query()->where('student_id', $student->id)->whereHas('scheduledLesson.slot', fn ($query) => $query->whereIn('teaching_group_id', $groupIds))->with(['type:id,label,symbol,color', 'scheduledLesson.lesson:id,title,teaching_unit_id', 'scheduledLesson.slot:id,teaching_group_id,date,period_number', 'scheduledLesson.slot.group:id,name'])->get();
        $assessmentResults = StudentAssessmentResult::query()->where('student_id', $student->id)->whereHas('assessment.group', fn ($query) => $query->whereIn('id', $groupIds))->with(['assessment:id,teaching_group_id,title,assessed_on,status', 'assessment.group:id,name', 'task:id,title,max_points'])->get();
        $evaluations = StudentEvaluation::query()->where('student_id', $student->id)->whereHas('period', fn ($query) => $query->whereIn('teaching_group_id', $groupIds))->with(['period:id,teaching_group_id,label,starts_on,ends_on,whole_grades', 'period.group:id,name', 'blocks:id,student_evaluation_id,area,text,position', 'observationScales', 'competenceRatings.competence:id,text'])->get();
        $ratings = StudentEvaluationCompetenceRating::query()->whereNotNull('rating')->whereHas('evaluation', fn ($query) => $query->where('student_id', $student->id)->whereHas('period', fn ($periodQuery) => $periodQuery->whereIn('teaching_group_id', $groupIds)))->with(['evaluation.period:id,teaching_group_id,label', 'evaluation.period.group:id,name', 'competence:id,text'])->get();

        return Inertia::render('Students/Show', compact('student', 'availableSchoolYears', 'selectedSchoolYear', 'observations', 'assessmentResults', 'evaluations', 'ratings') + ['groups' => $student->teachingGroups->whereIn('id', $groupIds)->values()]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Student::class);
        $userId = $request->user()->id;
        $search = trim((string) $request->query('q', ''));
        $schoolId = $request->integer('school_id') ?: null;
        $gradeLevel = trim((string) $request->query('grade_level', ''));
        $className = trim((string) $request->query('class_name', ''));
        $groupId = $request->integer('teaching_group_id') ?: null;
        $schoolYearId = $request->integer('school_year_id') ?: null;
        $sort = in_array($request->query('sort'), ['last_name', 'first_name', 'class_name', 'school'], true) ? $request->query('sort') : 'last_name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $searchableStudentIds = $this->searchableStudentIds($search, $userId);

        $students = Student::query()
            ->where('students.user_id', $userId)
            ->when($searchableStudentIds !== null, fn ($query) => $query->whereIn('students.id', $searchableStudentIds))
            ->with(['school:id,name', 'teachingGroups:id,name,school_year_id'])
            ->with('teachingGroups.schoolYear:id,name')
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->when($gradeLevel !== '', fn ($query) => $query->where('class_name', 'like', $gradeLevel.'%'))
            ->when($className !== '', fn ($query) => $query->where('class_name', $className))
            ->when($groupId, fn ($query) => $query->whereHas('teachingGroups', fn ($groupQuery) => $groupQuery->whereKey($groupId)))
            ->when($schoolYearId, fn ($query) => $query->whereHas('teachingGroups', fn ($groupQuery) => $groupQuery->where('school_year_id', $schoolYearId)))
            ->when($sort === 'school', fn ($query) => $query->orderBy(School::select('name')->whereColumn('schools.id', 'students.school_id'), $direction))
            ->when($sort !== 'school', fn ($query) => $query->orderByRaw('LOWER(students.'.$sort.') '.$direction))
            ->orderByRaw('LOWER(students.last_name) asc')
            ->orderByRaw('LOWER(students.first_name) asc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Students/Index', [
            'students' => $students,
            'schools' => School::where('user_id', $userId)->orderBy('name')->get(['id', 'name']),
            'classes' => Student::where('user_id', $userId)->distinct()->orderBy('class_name')->pluck('class_name')->values(),
            'gradeLevels' => Student::where('user_id', $userId)->pluck('class_name')->map(fn (string $className): ?string => preg_match('/^\d+/', $className, $matches) ? $matches[0] : null)->filter()->unique()->sort()->values(),
            'groups' => TeachingGroup::where('user_id', $userId)->with('schoolYear:id,name')->orderBy('name')->get(['id', 'name', 'school_year_id']),
            'schoolYears' => TeachingGroup::where('user_id', $userId)->with('schoolYear:id,name')->get()->pluck('schoolYear')->filter()->unique('id')->sortBy('name')->map(fn ($schoolYear) => ['id' => $schoolYear->id, 'name' => $schoolYear->name])->values(),
            'filters' => ['q' => $search, 'school_id' => $schoolId, 'grade_level' => $gradeLevel, 'class_name' => $className, 'teaching_group_id' => $groupId, 'school_year_id' => $schoolYearId, 'sort' => $sort, 'direction' => $direction],
            'pronounSets' => PronounSets::toArray(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Student::class);
        $userId = $request->user()->id;
        $search = trim((string) $request->query('q', ''));
        $schoolId = $request->integer('school_id') ?: null;
        $className = trim((string) $request->query('class_name', ''));
        $groupId = $request->integer('teaching_group_id') ?: null;
        $schoolYearId = $request->integer('school_year_id') ?: null;
        $fields = collect($request->input('fields', []))->intersect($this->exportFields())->values();
        if ($fields->isEmpty()) {
            $fields = collect(['last_name', 'first_name_plus', 'pronoun_set', 'class_name', 'denomination']);
        }
        $sort = in_array($request->query('sort'), ['last_name', 'first_name', 'class_name', 'school', 'school_year', 'denomination', 'pronoun_set'], true) ? $request->query('sort') : 'last_name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $groupByClass = $request->boolean('group_by_class');
        $searchableStudentIds = $this->searchableStudentIds($search, $userId);

        $students = Student::query()
            ->where('user_id', $userId)
            ->when($searchableStudentIds !== null, fn ($query) => $query->whereIn('students.id', $searchableStudentIds))
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->when($className !== '', fn ($query) => $query->where('class_name', $className))
            ->when($groupId, fn ($query) => $query->whereHas('teachingGroups', fn ($groupQuery) => $groupQuery->whereKey($groupId)))
            ->when($schoolYearId, fn ($query) => $query->whereHas('teachingGroups', fn ($groupQuery) => $groupQuery->where('school_year_id', $schoolYearId)))
            ->with(['school:id,name', 'teachingGroups:id,name,aktenzeichen,school_year_id', 'teachingGroups.schoolYear:id,name'])
            ->get();

        $students = $this->sortExportStudents($students, $sort, $direction, $groupByClass);
        $firstNamePlus = $this->firstNamePlus($students);
        $filename = $this->exportFilename($students);

        return response()->streamDownload(function () use ($students, $fields, $firstNamePlus): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $fields->map(fn (string $field): string => $this->exportFieldLabels()[$field])->all(), ';');
            foreach ($students as $student) {
                fputcsv($handle, $fields->map(fn (string $field) => $this->exportFieldValue($student, $field, $firstNamePlus[$student->id] ?? $student->first_name))->all(), ';');
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportFields(): array
    {
        return ['last_name', 'first_name', 'first_name_plus', 'pronoun_set', 'class_name', 'denomination', 'school', 'school_year'];
    }

    private function exportFieldLabels(): array
    {
        return [
            'last_name' => 'Nachname',
            'first_name' => 'Vorname',
            'first_name_plus' => 'Vorname_Plus',
            'pronoun_set' => 'Pronomen',
            'class_name' => 'Klasse',
            'denomination' => 'Konfession',
            'school' => 'Schule',
            'school_year' => 'Schuljahre',
        ];
    }

    private function exportFieldValue(Student $student, string $field, string $firstNamePlus): string
    {
        return match ($field) {
            'first_name' => (string) $student->first_name,
            'first_name_plus' => $firstNamePlus,
            'pronoun_set' => collect(PronounSets::toArray())->firstWhere('key', $student->pronoun_set)['label'] ?? (string) $student->pronoun_set,
            'class_name' => (string) $student->class_name,
            'denomination' => [
                'evangelical' => 'evangelisch',
                'catholic' => 'katholisch',
                'old_catholic' => 'alt-katholisch',
                'syriac_orthodox' => 'syrisch-orthodox',
            ][$student->denomination] ?? (string) $student->denomination,
            'school' => (string) ($student->school?->name ?? ''),
            'school_year' => $student->teachingGroups->pluck('schoolYear.name')->filter()->unique()->implode(', '),
            default => (string) $student->last_name,
        };
    }

    private function sortExportStudents(Collection $students, string $sort, string $direction, bool $groupByClass): Collection
    {
        $value = function (Student $student) use ($sort): string {
            return mb_strtolower(match ($sort) {
                'first_name' => (string) $student->first_name,
                'class_name' => (string) $student->class_name,
                'school' => (string) ($student->school?->name ?? ''),
                'school_year' => $student->teachingGroups->pluck('schoolYear.name')->filter()->unique()->implode(', '),
                'denomination' => (string) $student->denomination,
                'pronoun_set' => (string) $student->pronoun_set,
                default => (string) $student->last_name,
            });
        };
        $lastName = fn (Student $student): string => mb_strtolower((string) $student->last_name);
        $firstName = fn (Student $student): string => mb_strtolower((string) $student->first_name);

        return $students->sort(function (Student $a, Student $b) use ($value, $lastName, $firstName, $direction, $groupByClass, $sort): int {
            $comparison = $groupByClass ? strnatcasecmp((string) $a->class_name, (string) $b->class_name) : 0;
            if ($comparison === 0 && $sort !== 'class_name') {
                $comparison = strnatcasecmp($value($a), $value($b));
            }
            if ($comparison === 0) {
                $comparison = strnatcasecmp($lastName($a), $lastName($b));
            }
            if ($comparison === 0) {
                $comparison = strnatcasecmp($firstName($a), $firstName($b));
            }
            return $direction === 'desc' && (! $groupByClass || $comparison !== strnatcasecmp((string) $a->class_name, (string) $b->class_name)) ? -$comparison : $comparison;
        })->values();
    }

    private function firstNamePlus(Collection $students): array
    {
        $counts = $students->countBy(fn (Student $student): string => mb_strtolower(trim((string) $student->first_name)));

        return $students->mapWithKeys(function (Student $student) use ($counts): array {
            $firstName = (string) $student->first_name;
            $lastNameInitial = mb_substr(trim((string) $student->last_name), 0, 1);
            $value = ($counts->get(mb_strtolower(trim($firstName)), 0) > 1 && $lastNameInitial !== '') ? $firstName.' '.$lastNameInitial.'.' : $firstName;

            return [$student->id => $value];
        })->all();
    }

    private function exportFilename(Collection $students): string
    {
        $groups = $students->flatMap->teachingGroups;
        $components = collect([
            $this->uniqueExportComponent($groups->pluck('aktenzeichen')),
            $this->uniqueExportComponent($groups->pluck('schoolYear.name')->map(fn ($name) => $this->canonicalSchoolYear((string) $name))),
            $this->uniqueExportComponent($groups->pluck('name')),
        ])->filter()->values();

        return ($components->isNotEmpty() ? $components->implode('_').' ' : '').'Liste.csv';
    }

    private function uniqueExportComponent(Collection $values): ?string
    {
        $values = $values->map(fn ($value) => $this->filenamePart((string) $value))->filter()->unique()->values();

        return $values->count() === 1 ? $values->first() : null;
    }

    private function canonicalSchoolYear(string $name): string
    {
        return preg_replace('/^(\d{4})\/(\d{2})$/', '$1-$2', trim($name)) ?: trim($name);
    }

    private function filenamePart(string $value): string
    {
        return trim((string) preg_replace(['%[\\/]+%', '/[^\pL\pN._ -]+/u', '/\s+/u', '/\.{2,}/'], ['-', '-', ' ', '.'], $value), ' .-');
    }

    private function searchableStudentIds(string $search, int $userId): ?array
    {
        if ($search === '') {
            return null;
        }

        $needle = mb_strtolower($search);
        $databaseIds = Student::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($needle): void {
                foreach (['first_name', 'last_name', 'class_name'] as $column) {
                    $query->orWhereRaw('LOWER('.$column.') LIKE ?', ['%'.$needle.'%']);
                }
                $query->orWhereHas('teachingGroups', fn ($groupQuery) => $groupQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%']));
            })
            ->pluck('id');

        return $databaseIds->values()->all();
    }
}
