<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\Curriculum;
use App\Models\EducationPlan;
use App\Models\Lesson;
use App\Models\LessonPhase;
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
        $userId = $request->user()->id;
        $like = "%{$query}%";
        $this->searchLike = $like;
        $results = [
            'schools' => $query ? School::where('user_id', $userId)->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'name'), 'short_name', 'or'), 'city', 'or'))->orderBy('name')->limit(10)->get(['id', 'slug', 'name', 'city']) : collect(),
            'groups' => $query ? TeachingGroup::where('user_id', $userId)->where(fn ($q) => $this->matches($q, 'name'))->with('school:id,name')->orderBy('name')->limit(10)->get(['id', 'school_id', 'name']) : collect(),
            'curricula' => $query ? Curriculum::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))->where(fn ($q) => $this->matches($this->matches($q, 'title'), 'external_identifier', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'external_identifier']) : collect(),
            'educationPlans' => $query ? EducationPlan::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))->where(fn ($q) => $this->matches($this->matches($q, 'title'), 'external_identifier', 'or'))->orderBy('title')->limit(10)->get(['id', 'title', 'external_identifier']) : collect(),
            'students' => $query ? Student::where('user_id', $userId)->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'first_name'), 'last_name', 'or'), 'class_name', 'or'))->with('school:id,name')->orderBy('last_name')->limit(10)->get(['id', 'school_id', 'first_name', 'last_name', 'class_name']) : collect(),
            'teachingUnits' => $query ? TeachingUnit::where('user_id', $userId)->where(fn ($q) => $this->matchesAny($q, ['title', 'keyword', 'notes', 'introduction_text']))->with('group:id,name')->orderBy('title')->limit(10)->get(['id', 'teaching_group_id', 'title', 'keyword']) : collect(),
            'lessons' => $query ? Lesson::whereHas('unit', fn ($q) => $q->where('user_id', $userId))->where(fn ($q) => $this->matchesAny($q, ['title', 'learning_goals', 'materials', 'homework', 'assessment_note', 'notes']))->with('unit:id,teaching_group_id,title')->orderBy('title')->limit(10)->get(['id', 'teaching_unit_id', 'title']) : collect(),
            'lessonPhases' => $query ? LessonPhase::whereHas('lesson.unit', fn ($q) => $q->where('user_id', $userId))->where(fn ($q) => $this->matchesAny($q, ['title', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'materials', 'media']))->with('lesson:id,teaching_unit_id,title', 'lesson.unit:id,teaching_group_id,title')->orderBy('title')->limit(10)->get(['id', 'lesson_id', 'title']) : collect(),
            'unitTemplates' => $query ? UnitTemplate::where('user_id', $userId)->where(fn ($q) => $this->matchesAny($q, ['title', 'description', 'notes']))->orderBy('title')->limit(10)->get(['id', 'title', 'description']) : collect(),
            'lessonTemplates' => $query ? LessonTemplate::where('user_id', $userId)->where(fn ($q) => $this->matchesAny($q, ['title', 'objective', 'notes']))->orderBy('title')->limit(10)->get(['id', 'title', 'objective']) : collect(),
            'phaseTemplates' => $query ? PhaseTemplate::where('user_id', $userId)->where(fn ($q) => $this->matchesAny($q, ['title', 'social_form', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'material', 'media']))->orderBy('title')->limit(10)->get(['id', 'title', 'material']) : collect(),
            'songs' => $query ? Song::where('user_id', $userId)->where(function ($q): void {
                $this->matchesAny($q, ['title', 'composer', 'author', 'copyright_notice', 'age_group', 'topics', 'notes']);
                $q->orWhereHas('versions', fn ($version) => $this->matchesAny($version, ['name', 'language', 'lyrics', 'notation', 'chords']));
                $q->orWhereHas('versions.parts', fn ($part) => $this->matchesAny($part, ['title', 'content']));
            })->orderBy('title')->limit(10)->get(['id', 'title', 'composer', 'author']) : collect(),
            'assessmentTasks' => $query ? AssessmentTask::where('user_id', $userId)->where(fn ($q) => $this->matchesAny($q, ['title', 'task_type', 'content', 'solution', 'level']))->orderBy('title')->limit(10)->get(['id', 'title', 'task_type', 'level']) : collect(),
            'schoolYears' => $query ? SchoolYear::where('user_id', $userId)->where(fn ($q) => $this->matches($this->matches($this->matches($q, 'name'), 'starts_on', 'or'), 'ends_on', 'or'))->with('school:id,slug,name')->orderByDesc('starts_on')->limit(10)->get(['id', 'school_id', 'name', 'slug', 'starts_on', 'ends_on']) : collect(),
            'assessments' => $query ? Assessment::where('user_id', $userId)->where(fn ($q) => $this->matchesAny($q, ['title', 'grade_component_label', 'status', 'notes']))->with('group:id,name')->orderByDesc('assessed_on')->limit(10)->get(['id', 'teaching_group_id', 'title', 'assessed_on', 'grade_component_label']) : collect(),
            'files' => $query ? ResourceReference::where('user_id', $userId)->where(fn ($q) => $this->matchesAny($q, ['original_name', 'description', 'copyrights', 'mime_type', 'security_status', 'source', 'publication_status']))->orderBy('original_name')->limit(10)->get(['id', 'original_name', 'description', 'mime_type']) : collect(),
            'links' => $query ? ResourceLink::where('user_id', $userId)->where(fn ($q) => $this->matchesAny($q, ['title', 'url', 'description', 'publication_status']))->orderBy('title')->limit(10)->get(['id', 'title', 'url', 'description']) : collect(),
            'materials' => $query ? MaterialItem::where('user_id', $userId)->where(fn ($q) => $this->matches($this->matches($this->matches($this->matches($q, 'name'), 'material_number', 'or'), 'storage_location', 'or'), 'description', 'or'))->orderBy('name')->limit(10)->get(['id', 'name', 'material_number', 'storage_location', 'description']) : collect(),
            'songVersions' => $query ? SongVersion::where(fn ($q) => $this->matchesAny($q, ['name', 'language', 'lyrics', 'notation', 'chords']))->orWhereHas('song', function ($song) use ($userId): void {
                $song->where(fn ($scope) => $scope->whereNull('user_id')->orWhere('user_id', $userId))
                    ->where(fn ($q) => $this->matchesAny($q, ['title', 'composer', 'author', 'copyright_notice', 'age_group', 'topics', 'notes']));
            })->orWhereHas('parts', fn ($parts) => $this->matchesAny($parts, ['title', 'content']))->whereHas('song', fn ($song) => $song->whereNull('user_id')->orWhere('user_id', $userId))->with('song:id,title,author,composer')->orderBy('name')->limit(10)->get(['id', 'song_id', 'name']) : collect(),
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

    private function matchesAny($builder, array $columns)
    {
        foreach ($columns as $index => $column) {
            $this->matches($builder, $column, $index === 0 ? 'and' : 'or');
        }

        return $builder;
    }
}
