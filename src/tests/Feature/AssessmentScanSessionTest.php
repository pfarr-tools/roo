<?php

use App\Models\Assessment;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function assessmentScanSessionFixture(): array
{
    $organization = Organization::create(['name' => 'Session Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Session Schule']);
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
        'title' => 'Lernstandserhebung Session',
    ]);

    return compact('user', 'group', 'assessment', 'organization', 'school', 'schoolYear');
}

it('creates a temporary session and accepts one fragment upload', function () {
    Storage::fake('temporary');
    $fixture = assessmentScanSessionFixture();

    $session = $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session")
        ->assertCreated()
        ->json();

    $response = $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session['session_id']}/fragments", [
            'fragment' => UploadedFile::fake()->image('fragment.png', 120, 80),
            'page' => 1,
            'booklet' => 1,
            'task_id' => '7',
            'start_y_cm' => 4.5,
            'end_y_cm' => 12.0,
        ])
        ->assertCreated()
        ->json();

    expect($response['fragment_id'])->toBeString()
        ->and($response['checksum'])->toMatch('/^[a-f0-9]{64}$/');
});

it('rejects foreign assessments and deletes temporary sessions', function () {
    Storage::fake('temporary');
    $fixture = assessmentScanSessionFixture();
    $otherGroup = TeachingGroup::create([
        'organization_id' => $fixture['organization']->id,
        'school_id' => $fixture['school']->id,
        'school_year_id' => $fixture['schoolYear']->id,
        'name' => '5a Religion',
    ]);
    $otherAssessment = Assessment::create([
        'organization_id' => $fixture['organization']->id,
        'teaching_group_id' => $otherGroup->id,
        'title' => 'Andere Session',
    ]);

    $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$otherAssessment->id}/auswertung/session")
        ->assertNotFound();

    $session = $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session")
        ->assertCreated()
        ->json('session_id');

    $this->actingAs($fixture['user'])
        ->deleteJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}")
        ->assertNoContent();
});
