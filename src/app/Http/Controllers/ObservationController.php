<?php

namespace App\Http\Controllers;

use App\Models\Observation;
use App\Models\ObservationType;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObservationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TeachingGroup::class);
        $organizationId = $request->user()->organization_id;
        $search = trim((string) $request->query('q', ''));
        $groupId = $request->integer('group') ?: null;
        $schoolYearId = $request->integer('school_year') ?: null;
        $typeId = $request->integer('type') ?: null;
        $sort = in_array($request->query('sort'), ['date', 'student', 'group', 'type'], true) ? $request->query('sort') : 'date';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $observations = Observation::query()
            ->join('scheduled_lessons', 'scheduled_lessons.id', '=', 'observations.scheduled_lesson_id')
            ->join('schedule_slots', 'schedule_slots.id', '=', 'scheduled_lessons.schedule_slot_id')
            ->join('lessons', 'lessons.id', '=', 'scheduled_lessons.lesson_id')
            ->join('teaching_groups', 'teaching_groups.id', '=', 'schedule_slots.teaching_group_id')
            ->join('students', 'students.id', '=', 'observations.student_id')
            ->join('observation_types', 'observation_types.id', '=', 'observations.observation_type_id')
            ->where('teaching_groups.organization_id', $organizationId)
            ->when($groupId, fn ($query) => $query->where('teaching_groups.id', $groupId))
            ->when($schoolYearId, fn ($query) => $query->where('teaching_groups.school_year_id', $schoolYearId))
            ->when($typeId, fn ($query) => $query->where('observation_types.id', $typeId))
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search): void {
                $term = '%'.$search.'%';
                $searchQuery->whereRaw('LOWER(students.first_name) LIKE LOWER(?)', [$term])
                    ->orWhereRaw('LOWER(students.last_name) LIKE LOWER(?)', [$term])
                    ->orWhereRaw('LOWER(teaching_groups.name) LIKE LOWER(?)', [$term])
                    ->orWhereRaw('LOWER(lessons.title) LIKE LOWER(?)', [$term])
                    ->orWhereRaw('LOWER(observation_types.label) LIKE LOWER(?)', [$term])
                    ->orWhereRaw('LOWER(observations.note) LIKE LOWER(?)', [$term]);
            }))
            ->select('observations.*')
            ->with([
                'student:id,first_name,last_name,class_name',
                'type:id,label,symbol,color',
                'scheduledLesson.lesson:id,teaching_unit_id,title',
                'scheduledLesson.slot:id,teaching_group_id,date,period_number',
                'scheduledLesson.slot.group:id,name,school_year_id',
                'scheduledLesson.slot.group.schoolYear:id,name',
            ])
            ->when($sort === 'date', fn ($query) => $query->orderBy('schedule_slots.date', $direction)->orderBy('schedule_slots.period_number', $direction))
            ->when($sort === 'student', fn ($query) => $query->orderByRaw('LOWER(students.last_name) '.$direction)->orderByRaw('LOWER(students.first_name) '.$direction))
            ->when($sort === 'group', fn ($query) => $query->orderByRaw('LOWER(teaching_groups.name) '.$direction))
            ->when($sort === 'type', fn ($query) => $query->orderByRaw('LOWER(observation_types.label) '.$direction))
            ->orderBy('observations.id', 'desc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Observations/Index', [
            'observations' => $observations,
            'groups' => TeachingGroup::where('organization_id', $organizationId)->with('schoolYear:id,name')->orderBy('name')->get(['id', 'name', 'school_year_id']),
            'schoolYears' => SchoolYear::where('organization_id', $organizationId)->orderByDesc('starts_on')->get(['id', 'name']),
            'observationTypes' => ObservationType::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))->where('is_active', true)->orderBy('position')->orderBy('label')->get(['id', 'label']),
            'filters' => ['q' => $search, 'group' => $groupId, 'school_year' => $schoolYearId, 'type' => $typeId, 'sort' => $sort, 'direction' => $direction],
        ]);
    }
}
