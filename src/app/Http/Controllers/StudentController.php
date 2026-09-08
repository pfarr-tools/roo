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
        $organizationId = $request->user()->organization_id;
        $search = trim((string) $request->query('q', ''));
        $schoolId = $request->integer('school_id') ?: null;
        $gradeLevel = trim((string) $request->query('grade_level', ''));
        $className = trim((string) $request->query('class_name', ''));
        $groupId = $request->integer('teaching_group_id') ?: null;
        $schoolYearId = $request->integer('school_year_id') ?: null;
        $sort = in_array($request->query('sort'), ['last_name', 'first_name', 'class_name', 'school'], true) ? $request->query('sort') : 'last_name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $searchableStudentIds = $this->searchableStudentIds($search, $organizationId);

        $students = Student::query()
            ->where('students.organization_id', $organizationId)
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
            'schools' => School::where('organization_id', $organizationId)->orderBy('name')->get(['id', 'name']),
            'classes' => Student::where('organization_id', $organizationId)->distinct()->orderBy('class_name')->pluck('class_name')->values(),
            'gradeLevels' => Student::where('organization_id', $organizationId)->pluck('class_name')->map(fn (string $className): ?string => preg_match('/^\d+/', $className, $matches) ? $matches[0] : null)->filter()->unique()->sort()->values(),
            'groups' => TeachingGroup::where('organization_id', $organizationId)->with('schoolYear:id,name')->orderBy('name')->get(['id', 'name', 'school_year_id']),
            'schoolYears' => TeachingGroup::where('organization_id', $organizationId)->with('schoolYear:id,name')->get()->pluck('schoolYear')->filter()->unique('id')->sortBy('name')->map(fn ($schoolYear) => ['id' => $schoolYear->id, 'name' => $schoolYear->name])->values(),
            'filters' => ['q' => $search, 'school_id' => $schoolId, 'grade_level' => $gradeLevel, 'class_name' => $className, 'teaching_group_id' => $groupId, 'school_year_id' => $schoolYearId, 'sort' => $sort, 'direction' => $direction],
            'pronounSets' => PronounSets::toArray(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Student::class);
        $organizationId = $request->user()->organization_id;
        $search = trim((string) $request->query('q', ''));
        $schoolId = $request->integer('school_id') ?: null;
        $className = trim((string) $request->query('class_name', ''));
        $groupId = $request->integer('teaching_group_id') ?: null;
        $schoolYearId = $request->integer('school_year_id') ?: null;
        $searchableStudentIds = $this->searchableStudentIds($search, $organizationId);

        $students = Student::query()
            ->where('organization_id', $organizationId)
            ->when($searchableStudentIds !== null, fn ($query) => $query->whereIn('students.id', $searchableStudentIds))
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->when($className !== '', fn ($query) => $query->where('class_name', $className))
            ->when($groupId, fn ($query) => $query->whereHas('teachingGroups', fn ($groupQuery) => $groupQuery->whereKey($groupId)))
            ->when($schoolYearId, fn ($query) => $query->whereHas('teachingGroups', fn ($groupQuery) => $groupQuery->where('school_year_id', $schoolYearId)))
            ->with(['school:id,name', 'teachingGroups:id,name,school_year_id', 'teachingGroups.schoolYear:id,name'])
            ->orderByRaw('LOWER(last_name)')
            ->orderByRaw('LOWER(first_name)')
            ->get();

        return response()->streamDownload(function () use ($students): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nachname', 'Vorname', 'Klasse', 'Schule', 'Schuljahre'], ';');
            foreach ($students as $student) {
                fputcsv($handle, [
                    $student->last_name,
                    $student->first_name,
                    $student->class_name,
                    $student->school->name,
                    $student->teachingGroups->pluck('schoolYear.name')->filter()->unique()->implode(', '),
                ], ';');
            }
            fclose($handle);
        }, 'schuelerinnen.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function searchableStudentIds(string $search, int $organizationId): ?array
    {
        if ($search === '') {
            return null;
        }

        $indexedIds = Student::search($search)
            ->where('organization_id', $organizationId)
            ->keys();
        $needle = mb_strtolower($search);
        $databaseIds = Student::query()
            ->where('organization_id', $organizationId)
            ->where(function ($query) use ($needle): void {
                foreach (['first_name', 'last_name', 'class_name'] as $column) {
                    $query->orWhereRaw('LOWER('.$column.') LIKE ?', ['%'.$needle.'%']);
                }
                $query->orWhereHas('teachingGroups', fn ($groupQuery) => $groupQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%']));
            })
            ->pluck('id');

        return collect($indexedIds)->merge($databaseIds)->unique()->values()->all();
    }
}
