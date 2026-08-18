<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Student::class);
        $organizationId = $request->user()->organization_id;
        $search = trim((string) $request->query('q', ''));
        $schoolId = $request->integer('school_id') ?: null;
        $className = trim((string) $request->query('class_name', ''));
        $sort = in_array($request->query('sort'), ['last_name', 'first_name', 'class_name', 'school'], true) ? $request->query('sort') : 'last_name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $searchableStudentIds = $search === ''
            ? null
            : Student::search($search)
                ->where('organization_id', (string) $organizationId)
                ->keys();

        $students = Student::query()
            ->where('students.organization_id', $organizationId)
            ->when($searchableStudentIds !== null, fn ($query) => $query->whereIn('students.id', $searchableStudentIds))
            ->with(['school:id,name', 'teachingGroups:id,name,school_year_id'])
            ->with('teachingGroups.schoolYear:id,name')
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->when($className !== '', fn ($query) => $query->where('class_name', $className))
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
            'filters' => ['q' => $search, 'school_id' => $schoolId, 'class_name' => $className, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Student::class);
        $students = Student::query()
            ->where('organization_id', $request->user()->organization_id)
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
}
