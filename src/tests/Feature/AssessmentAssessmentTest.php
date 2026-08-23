<?php

use App\Models\Assessment;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\AssessmentScan\AssessmentPdfScanner;
use App\Services\AssessmentScan\AssessmentScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function assessmentScanFixture(): array
{
    $organization = Organization::create(['name' => 'Scan Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Scan Schule']);
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
        'title' => 'Lernstandserhebung Schöpfung',
    ]);

    return compact('user', 'group', 'assessment', 'organization', 'school', 'schoolYear');
}

it('uploads a pdf and renders the scan result page', function () {
    $fixture = assessmentScanFixture();
    $this->mock(AssessmentPdfScanner::class, function ($mock): void {
        $mock->shouldReceive('scan')->once()->andReturn(new AssessmentScanResult([
            ['number' => 1, 'start_page' => 1, 'markers' => []],
        ], []));
    });

    $this->actingAs($fixture['user'])
        ->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswerten", [
            'pdf' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ])
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Assess')
            ->where('assessment.id', $fixture['assessment']->id)
            ->has('scan.booklets', 1));
});

it('rejects non-pdf uploads and assessments from another group', function () {
    $fixture = assessmentScanFixture();
    $otherGroup = TeachingGroup::create([
        'organization_id' => $fixture['organization']->id,
        'school_id' => $fixture['school']->id,
        'school_year_id' => $fixture['schoolYear']->id,
        'name' => '5a Religion',
    ]);
    $otherAssessment = Assessment::create([
        'organization_id' => $fixture['organization']->id,
        'teaching_group_id' => $otherGroup->id,
        'title' => 'Andere LSE',
    ]);

    $this->actingAs($fixture['user'])
        ->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswerten", [
            'pdf' => UploadedFile::fake()->createWithContent('scan.txt', 'not a pdf'),
        ])
        ->assertSessionHasErrors('pdf');

    $this->actingAs($fixture['user'])
        ->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$otherAssessment->id}/auswerten", [
            'pdf' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ])
        ->assertNotFound();
});

it('offers the assessment scan action in the existing assessment editor', function () {
    $template = file_get_contents(resource_path('js/Pages/Assessments/Form.vue'));
    $modal = file_get_contents(resource_path('js/Features/AssessmentEvaluation/AssessmentScanUploadModal.vue'));

    expect($template)->toContain('assessmentScanTitle')
        ->and($template)->toContain('lernstandserhebungen/${assessment.id}/auswertung')
        ->and($template)->not->toContain('createScanClient')
        ->and($modal)->toContain('assessmentScanProcessing')
        ->and($modal)->toContain('assessmentScanProcessingHint')
        ->and($modal)->toContain('role="status"');
});

it('lays out the scan upload modal beside a contained page preview', function () {
    $template = file_get_contents(resource_path('js/Features/AssessmentEvaluation/AssessmentScanUploadModal.vue'));
    $styles = file_get_contents(resource_path('scss/app.scss'));

    expect($template)->toContain('assessment-scan-modal')
        ->and($template)->toContain('assessment-scan-preview-pane')
        ->and($styles)->toContain('.assessment-scan-modal')
        ->and($styles)->toContain('max-height: 80vh')
        ->and($styles)->toContain('.assessment-scan-form { height: 100%')
        ->and($styles)->toContain('.assessment-scan-preview-image')
        ->and($styles)->toContain('object-fit: contain');
});
