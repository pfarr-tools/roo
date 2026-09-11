<?php

use App\Models\Assessment;
use App\Models\AssessmentBooklet;
use App\Models\AssessmentBookletFragment;
use App\Models\AssessmentTask;
use App\Models\AssessmentTaskImage;
use App\Models\ResourceReference;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('cleans assessment booklet crops when a teaching group is deleted', function () {
    Storage::fake('documents');
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Löschprüfung Schule']);
    $schoolYear = SchoolYear::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'name' => '2026/27',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
    ]);
    $group = TeachingGroup::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'school_year_id' => $schoolYear->id,
        'name' => 'Löschprüfung Gruppe',
    ]);
    $assessment = Assessment::create([
        'user_id' => $user->id,
        'teaching_group_id' => $group->id,
        'title' => 'Löschprüfung Assessment',
    ]);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'user_id' => $user->id,
        'title' => 'Löschprüfung Aufgabe',
    ]));
    $assessment->tasks()->attach($task, ['position' => 1]);
    $booklet = AssessmentBooklet::create([
        'assessment_id' => $assessment->id,
        'number' => 1,
        'status' => 'open',
        'name_fragment_path' => "assessment-booklets/{$assessment->id}/{$assessment->id}/name.png",
    ]);
    $fragment = AssessmentBookletFragment::create([
        'assessment_booklet_id' => $booklet->id,
        'assessment_task_id' => $task->id,
        'image_path' => "assessment-booklets/{$assessment->id}/{$booklet->id}/task.png",
        'page' => 1,
        'start_y_cm' => 4,
        'end_y_cm' => 10,
    ]);
    Storage::disk('documents')->put($booklet->name_fragment_path, 'name crop');
    Storage::disk('documents')->put($fragment->image_path, 'task crop');

    $this->actingAs($user)
        ->delete("/unterrichtsgruppen/{$group->id}")
        ->assertRedirect('/unterrichtsgruppen');

    Storage::disk('documents')->assertMissing($booklet->name_fragment_path);
    Storage::disk('documents')->assertMissing($fragment->image_path);
});

it('preserves teaching-unit resources still used by reusable assessment tasks', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Ressourcenprüfung Schule']);
    $schoolYear = SchoolYear::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'name' => '2026/27',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
    ]);
    $group = TeachingGroup::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'school_year_id' => $schoolYear->id,
        'name' => 'Ressourcenprüfung Gruppe',
    ]);
    $unit = $group->teachingUnits()->create([
        'user_id' => $user->id,
        'title' => 'Einheit mit Bild',
        'position' => 1,
    ]);
    $resource = ResourceReference::create([
        'user_id' => $user->id,
        'teaching_unit_id' => $unit->id,
        'original_name' => 'Bild.png',
        'storage_path' => 'teaching-units/bild.png',
        'mime_type' => 'image/png',
    ]);
    $assessment = Assessment::create([
        'user_id' => $user->id,
        'teaching_group_id' => $group->id,
        'title' => 'Bildprüfung',
    ]);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'user_id' => $user->id,
        'title' => 'Bildaufgabe',
    ]));
    $assessment->tasks()->attach($task, ['position' => 1]);
    $image = AssessmentTaskImage::create([
        'assessment_task_id' => $task->id,
        'resource_reference_id' => $resource->id,
        'position' => 0,
    ]);

    $this->actingAs($user)
        ->delete("/unterrichtsgruppen/{$group->id}")
        ->assertRedirect('/unterrichtsgruppen');

    expect($group->fresh())->toBeNull()
        ->and($resource->fresh()->teaching_unit_id)->toBeNull()
        ->and($image->fresh()->resource_reference_id)->toBe($resource->id);
});
