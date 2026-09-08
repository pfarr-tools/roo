<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\EducationPlan;
use App\Models\AssessmentTask;
use App\Models\Assessment;
use App\Models\LessonTemplate;
use App\Models\MaterialItem;
use App\Models\PhaseTemplate;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Song;
use App\Models\SongVersion;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\UnitTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private string $searchLike = '';

    public function __invoke(Request $request): Response|JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $organizationId = $request->user()->organization_id;
        $like = "%{$query}%";
        $this->searchLike = $like;
        $results = [
            'schools' => $query ? School::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'name'), 'short_name', 'or'), 'city', 'or'))->orderBy('name')->limit(10)->get(['id', 'slug', 'name', 'city']) : collect(),
            'groups' => $query ? TeachingGroup::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($q, 'name'))->with('school:id,name')->orderBy('name')->limit(10)->get(['id', 'school_id', 'name']) : collect(),
            'curricula' => $query ? Curriculum::where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId))->where(fn ($q) => $this->matches($this->matches($q, 'title'), 'external_identifier', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'external_identifier']) : collect(),
            'educationPlans' => $query ? EducationPlan::where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId))->where(fn ($q) => $this->matches($this->matches($q, 'title'), 'external_identifier', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'external_identifier']) : collect(),
            'students' => $query ? Student::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'first_name'), 'last_name', 'or'), 'class_name', 'or'))->with('school:id,name')->orderBy('last_name')->limit(10)->get(['id', 'school_id', 'first_name', 'last_name', 'class_name']) : collect(),
            'teachingUnits' => $query ? TeachingUnit::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($q, 'title'), 'keyword', 'or'))->with('group:id,name')->orderBy('title')->limit(10)->get(['id', 'teaching_group_id', 'title', 'keyword']) : collect(),
            'unitTemplates' => $query ? UnitTemplate::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($q, 'title'), 'description', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'description']) : collect(),
            'lessonTemplates' => $query ? LessonTemplate::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($q, 'title'), 'objective', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'objective']) : collect(),
            'phaseTemplates' => $query ? PhaseTemplate::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($this->matches($this->matches($q, 'title'), 'teacher_interaction', 'or'), 'learner_activity', 'or'), 'material', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'material']) : collect(),
            'songs' => $query ? Song::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'title'), 'composer', 'or'), 'author', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'composer', 'author']) : collect(),
            'assessmentTasks' => $query ? AssessmentTask::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'title'), 'task_type', 'or'), 'level', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'task_type', 'level']) : collect(),
            'schoolYears' => $query ? SchoolYear::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'name'), 'starts_on', 'or'), 'ends_on', 'or'))->with('school:id,slug,name')->orderByDesc('starts_on')->limit(10)->get(['id', 'school_id', 'name', 'slug', 'starts_on', 'ends_on']) : collect(),
            'assessments' => $query ? Assessment::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($q, 'title'), 'grade_component_label', 'or'))->with('group:id,name')->orderByDesc('assessed_on')->limit(10)->get(['id', 'teaching_group_id', 'title', 'assessed_on', 'grade_component_label']) : collect(),
            'files' => $query ? ResourceReference::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($q, 'original_name'), 'description', 'or'))->orderBy('original_name')->limit(10)->get(['id', 'original_name', 'description', 'mime_type']) : collect(),
            'links' => $query ? ResourceLink::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'title'), 'url', 'or'), 'description', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'url', 'description']) : collect(),
            'materials' => $query ? MaterialItem::where('organization_id', $organizationId)->where(fn ($q) => $this->matches($this->matches($this->matches($this->matches($q, 'name'), 'material_number', 'or'), 'storage_location', 'or'), 'description', 'or'))->orderBy('name')->limit(10)->get(['id', 'name', 'material_number', 'storage_location', 'description']) : collect(),
            'songVersions' => $query ? SongVersion::whereHas('song', function ($song) use ($organizationId): void {
                $song->where(fn ($scope) => $scope->whereNull('organization_id')->orWhere('organization_id', $organizationId))
                    ->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'title'), 'composer', 'or'), 'author', 'or'));
            })->with('song:id,title,author,composer')->orderBy('name')->limit(10)->get(['id', 'song_id', 'name']) : collect(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['results' => $results]);
        }

        return Inertia::render('Search/Index', ['query' => $query, 'results' => $results]);
    }

    private function matches($builder, string $column, string $boolean = 'and')
    {
        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';

        return $builder->{$method}('LOWER(CAST("'.$column.'" AS TEXT)) LIKE LOWER(?)', [$this->searchLike]);
    }
}
