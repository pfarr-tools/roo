<?php

use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\AssessmentScan\AssessmentScanSessionStore;
use App\Services\AssessmentScan\DataMatrixDecoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function browserScanResultFixture(): array
{
    $organization = Organization::create(['name' => 'Result Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Result Schule']);
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
        'title' => 'Lernstandserhebung Ergebnis',
    ]);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $organization->id,
        'title' => 'Aufgabe eins',
    ]));
    $assessment->tasks()->attach($task, ['position' => 1]);

    return compact('user', 'group', 'assessment', 'task');
}

it('hands a completed browser session to the assessment result page', function () {
    Storage::fake('temporary');
    $fixture = browserScanResultFixture();

    $session = $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session")
        ->assertCreated()
        ->json('session_id');

    $fragment = $this->actingAs($fixture['user'])
        ->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/fragments", [
            'fragment' => UploadedFile::fake()->image('fragment.png', 120, 80),
            'page' => 3,
            'booklet' => 2,
            'task_id' => '7',
            'start_y_cm' => 4.5,
            'end_y_cm' => 12,
        ])
        ->assertCreated()
        ->json('fragment_id');

    $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/complete", [
            'scan' => [
                'booklets' => [['number' => 1, 'start_page' => 1, 'markers' => []]],
                'warnings' => ['Browserdecoder konnte nicht alle Marker prüfen.'],
            ],
            'fragment_ids' => [$fragment],
        ])
        ->assertOk()
        ->assertJsonPath('redirect_url', url("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/ergebnis"));

    $this->actingAs($fixture['user'])
        ->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/ergebnis")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Assess')
            ->where('scan.booklets.0.number', 1)
            ->where('scan.warnings.0', 'Browserdecoder konnte nicht alle Marker prüfen.')
            ->where('fragments.0.booklet', 2)
            ->where('fragments.0.task_id', '7')
            ->where('fragments.0.page', 3));

    $this->actingAs($fixture['user'])
        ->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/fragments/{$fragment}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('accepts a completed browser session without detected booklets', function () {
    Storage::fake('temporary');
    $fixture = browserScanResultFixture();

    $session = $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session")
        ->assertCreated()
        ->json('session_id');

    $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/complete", [
            'scan' => [
                'booklets' => [],
                'warnings' => ['Es wurden keine Booklets erkannt.'],
            ],
            'fragment_ids' => [],
        ])
        ->assertOk();
});

it('scans uploaded pages on the server and creates task fragments', function () {
    Storage::fake('temporary');
    $fixture = browserScanResultFixture();
    $this->app->bind(DataMatrixDecoder::class, fn () => new class($fixture['task']->id) implements DataMatrixDecoder
    {
        public function __construct(private readonly int $taskId) {}

        public function decode(string $imagePath): iterable
        {
            yield ['payload' => 'ROO1|A=3|L=M|K=PAGE', 'x_px' => 2, 'y_px' => 2, 'width_px' => 10, 'height_px' => 10];
            yield ['payload' => "ROO1|T={$this->taskId}|K=START", 'x_px' => 2, 'y_px' => 20, 'width_px' => 10, 'height_px' => 10];
            yield ['payload' => "ROO1|T={$this->taskId}|K=END", 'x_px' => 2, 'y_px' => 70, 'width_px' => 10, 'height_px' => 10];
        }
    });

    $session = $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session")
        ->assertCreated()
        ->json('session_id');

    $this->actingAs($fixture['user'])
        ->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/pages", [
            'image' => UploadedFile::fake()->image('page.png', 100, 100),
            'page' => 1,
        ])
        ->assertCreated()
        ->assertJsonCount(3, 'markers');

    expect(app(AssessmentScanSessionStore::class)->pages($session))->toHaveCount(1)
        ->and(app(AssessmentScanSessionStore::class)->pages($session)[0]['markers'])->toHaveCount(3);

    $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/complete", [])
        ->assertOk()
        ->assertJsonPath('redirect_url', url("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung"));

    $this->actingAs($fixture['user'])
        ->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertInertia(fn ($page) => $page
            ->where('scan.booklets.0.number', 1)
            ->where('fragments.0.booklet', 1)
            ->where('fragments.0.task_id', $fixture['task']->id));

    expect(app(AssessmentScanSessionStore::class)->manifest($session))->toBeNull();
});
