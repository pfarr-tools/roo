<?php

use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\TeachingGroupGradeComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function assessmentCategoryFixture(): array
{
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Kategorieschule']);
    $year = SchoolYear::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'name' => '2026/27',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
    ]);
    $group = TeachingGroup::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'school_year_id' => $year->id,
        'name' => 'Kategoriegruppe',
    ]);
    $component = TeachingGroupGradeComponent::create([
        'teaching_group_id' => $group->id,
        'type' => 'custom',
        'label' => 'Ordner',
        'percentage' => 10,
        'position' => 3,
    ]);
    $assessment = Assessment::create([
        'user_id' => $user->id,
        'teaching_group_id' => $group->id,
        'title' => 'Ordnereinsicht',
    ]);

    return compact('user', 'group', 'component', 'assessment');
}

it('keeps an assessment category readable after the category is deactivated', function () {
    $fixture = assessmentCategoryFixture();
    $fixture['assessment']->update([
        'grade_component_id' => $fixture['component']->id,
        'grade_component_label' => $fixture['component']->label,
    ]);
    $fixture['component']->update(['is_active' => false]);

    expect($fixture['assessment']->fresh()->gradeComponentLabel)->toBe('Ordner')
        ->and($fixture['assessment']->fresh()->gradeComponent)->toBeNull();
});

it('distinguishes manually created booklets from scanned booklets', function () {
    $fixture = assessmentCategoryFixture();
    $booklet = $fixture['assessment']->booklets()->create([
        'student_id' => null,
        'number' => 1,
        'status' => 'open',
        'source' => 'manual',
    ]);

    expect($booklet->source)->toBe('manual')->and($booklet->fragments)->toBeEmpty();
});

it('stores a group assessment category and offers active categories in the form', function () {
    $fixture = assessmentCategoryFixture();

    $this->actingAs($fixture['user'])->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/neu")
        ->assertInertia(fn ($page) => $page->where('gradeComponents.0.id', $fixture['component']->id));

    $this->actingAs($fixture['user'])->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen", [
        'title' => 'Ordnereinsicht neu',
        'grade_component_id' => $fixture['component']->id,
    ])->assertRedirect();

    expect(Assessment::where('title', 'Ordnereinsicht neu')->firstOrFail()->only(['grade_component_id', 'grade_component_label']))
        ->toBe(['grade_component_id' => $fixture['component']->id, 'grade_component_label' => 'Ordner']);
});

it('rejects a category belonging to another group or an inactive category', function () {
    $fixture = assessmentCategoryFixture();
    $otherComponent = TeachingGroupGradeComponent::create([
        'teaching_group_id' => TeachingGroup::create([
            'user_id' => $fixture['group']->user_id,
            'school_id' => $fixture['group']->school_id,
            'school_year_id' => $fixture['group']->school_year_id,
            'name' => 'Andere Kategoriegruppe',
        ])->id,
        'type' => 'custom',
        'label' => 'Andere Kategorie',
        'percentage' => 10,
        'position' => 1,
    ]);

    $this->actingAs($fixture['user'])->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen", ['title' => 'Fremde Kategorie', 'grade_component_id' => $otherComponent->id])
        ->assertSessionHasErrors('grade_component_id');

    $fixture['component']->update(['is_active' => false]);
    $this->actingAs($fixture['user'])->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen", ['title' => 'Inaktive Kategorie', 'grade_component_id' => $fixture['component']->id])
        ->assertSessionHasErrors('grade_component_id');
});

it('legt ein manuelles Exemplar direkt für ein Gruppenmitglied an', function () {
    $fixture = assessmentCategoryFixture();
    $student = Student::create(['user_id' => $fixture['group']->user_id, 'school_id' => $fixture['group']->school_id, 'first_name' => 'Mara', 'last_name' => 'Muster', 'class_name' => '4a']);
    $fixture['group']->students()->attach($student);

    $this->actingAs($fixture['user'])->post(route('assessments.booklets.manual.store', [$fixture['group'], $fixture['assessment']]), ['student_id' => $student->id])->assertRedirect();

    $booklet = $fixture['assessment']->booklets()->firstOrFail();
    expect($booklet->source)->toBe('manual')
        ->and($booklet->student_id)->toBe($student->id)
        ->and($booklet->fragments)->toBeEmpty();
});

it('stellt manuelle Exemplare als bewertbare Ziele ohne Scanfragment bereit', function () {
    $fixture = assessmentCategoryFixture();
    $student = Student::create(['user_id' => $fixture['group']->user_id, 'school_id' => $fixture['group']->school_id, 'first_name' => 'Mara', 'last_name' => 'Muster', 'class_name' => '4a']);
    $fixture['group']->students()->attach($student);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create(['user_id' => $fixture['group']->user_id, 'title' => 'Erwartung']));
    $fixture['assessment']->tasks()->attach($task);
    $fixture['assessment']->booklets()->create(['student_id' => $student->id, 'number' => 1, 'status' => 'open', 'source' => 'manual']);

    $this->actingAs($fixture['user'])->get(route('assessments.evaluation', [$fixture['group'], $fixture['assessment']]))
        ->assertInertia(fn ($page) => $page->where('booklets.0.source', 'manual')->where('taskFragments.0.image_url', null)->where('taskFragments.0.assessment_task_id', $task->id));

    $booklet = $fixture['assessment']->booklets()->firstOrFail();
    $this->actingAs($fixture['user'])->put(route('assessments.task-reviews.update', [$fixture['group'], $fixture['assessment'], $booklet, $task]), ['items' => [], 'extra_points' => 0, 'extra_note' => null])->assertRedirect();
    expect($booklet->reviews()->where('assessment_task_id', $task->id)->exists())->toBeTrue();
});
