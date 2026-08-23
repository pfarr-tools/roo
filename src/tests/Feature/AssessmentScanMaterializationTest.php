<?php

use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Services\AssessmentEvaluation\MaterializeAssessmentScan;
use App\Services\AssessmentScan\AssessmentScanSessionStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function assessmentScanMaterializationFixture(): array
{
    $organization = Organization::create(['name' => 'Materialisierungsorganisation']);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Materialisierungsschule']);
    $schoolYear = SchoolYear::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'name' => '2026/27',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
    ]);
    $group = TeachingGroup::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'school_year_id' => $schoolYear->id,
        'name' => '4a Religion',
    ]);
    $assessment = Assessment::create([
        'organization_id' => $organization->id,
        'teaching_group_id' => $group->id,
        'title' => 'Lernstandserhebung Materialisierung',
    ]);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $organization->id,
        'title' => 'Aufgabe eins',
    ]));
    $assessment->tasks()->attach($task, ['position' => 1]);

    return compact('assessment', 'task');
}

it('materializes PAGE groups with private name and task fragments then deletes the completed session', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentScanMaterializationFixture();
    $sessions = app(AssessmentScanSessionStore::class);
    $session = $sessions->create($fixture['assessment'])['session_id'];

    $sessions->storePage($session, 1, UploadedFile::fake()->image('first-page.png', 2480, 3508));
    $sessions->storePageMarkers($session, 1, [
        ['kind' => 'PAGE', 'page' => 1, 'y_cm' => 1.0, 'y_px' => 118, 'assessment_id' => $fixture['assessment']->id],
        ['kind' => 'START', 'page' => 1, 'y_cm' => 4.0, 'y_px' => 472, 'task_id' => (string) $fixture['task']->id],
        ['kind' => 'END', 'page' => 1, 'y_cm' => 12.0, 'y_px' => 1417, 'task_id' => (string) $fixture['task']->id],
    ]);
    $sessions->storePage($session, 2, UploadedFile::fake()->image('second-page.png', 2480, 3508));
    $sessions->storePageMarkers($session, 2, [
        ['kind' => 'PAGE', 'page' => 2, 'y_cm' => 1.0, 'y_px' => 118, 'assessment_id' => $fixture['assessment']->id],
        ['kind' => 'START', 'page' => 2, 'y_cm' => 5.0, 'y_px' => 591, 'task_id' => (string) $fixture['task']->id],
    ]);
    $sessions->complete($session, ['booklets' => [], 'warnings' => []], []);
    Storage::disk('temporary')->assertExists("assessment-scans/{$session}/pages/page-1.png");
    Storage::disk('temporary')->assertExists("assessment-scans/{$session}/pages/page-2.png");

    $booklets = app(MaterializeAssessmentScan::class)->handle($fixture['assessment'], $session);

    expect($booklets)->toHaveCount(2)
        ->and($booklets->pluck('number')->all())->toBe([1, 2])
        ->and($booklets[0]->fragments)->toHaveCount(1)
        ->and($booklets[0]->fragments->sole()->assessment_task_id)->toBe($fixture['task']->id)
        ->and($booklets[0]->fragments->sole()->page)->toBe(1)
        ->and($booklets[0]->fragments->sole()->start_y_cm)->toBe('4.000')
        ->and($booklets[0]->fragments->sole()->end_y_cm)->toBe('12.000');

    Storage::disk('documents')->assertExists($booklets[0]->name_fragment_path);
    Storage::disk('documents')->assertExists($booklets[1]->name_fragment_path);
    Storage::disk('documents')->assertExists($booklets[0]->fragments->sole()->image_path);
    expect($sessions->manifest($session))->toBeNull();
});
