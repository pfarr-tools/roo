<?php

use App\Models\Assessment;
use App\Models\AssessmentScanMaterialization;
use App\Models\AssessmentTask;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\AssessmentEvaluation\MaterializeAssessmentScan;
use App\Services\AssessmentScan\AssessmentScanSessionStore;
use App\Services\AssessmentScan\DataMatrixDecoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function assessmentScanMaterializationFixture(): array
{
    $organization = Organization::create(['name' => 'Materialisierungsorganisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
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

    return compact('assessment', 'task', 'user', 'group');
}

it('materializes uploaded pages and redirects their completion to the durable evaluation', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentScanMaterializationFixture();
    $this->app->bind(DataMatrixDecoder::class, fn () => new class($fixture['assessment']->id, $fixture['task']->id) implements DataMatrixDecoder
    {
        public function __construct(private readonly int $assessmentId, private readonly int $taskId) {}

        public function decode(string $imagePath): iterable
        {
            yield ['payload' => "ROO1|A={$this->assessmentId}|K=PAGE", 'x_px' => 20, 'y_px' => 100, 'width_px' => 10, 'height_px' => 10];
            yield ['payload' => "ROO1|T={$this->taskId}|K=START", 'x_px' => 20, 'y_px' => 500, 'width_px' => 10, 'height_px' => 10];
            yield ['payload' => "ROO1|T={$this->taskId}|K=END", 'x_px' => 20, 'y_px' => 1200, 'width_px' => 10, 'height_px' => 10];
        }
    });

    $session = $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session")
        ->assertCreated()
        ->json('session_id');

    $this->actingAs($fixture['user'])
        ->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/pages", [
            'image' => UploadedFile::fake()->image('page.png', 2480, 3508),
            'page' => 1,
        ])
        ->assertCreated();

    $this->actingAs($fixture['user'])
        ->postJson("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session/{$session}/complete")
        ->assertOk()
        ->assertJsonPath('redirect_url', url("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung"));

    expect($fixture['assessment']->booklets()->count())->toBe(1)
        ->and($fixture['assessment']->booklets()->first()->fragments)->toHaveCount(1)
        ->and(app(AssessmentScanSessionStore::class)->manifest($session))->toBeNull();
});

it('returns the durable evaluation redirect when completion is retried after its session was deleted', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentScanMaterializationFixture();
    $this->app->bind(DataMatrixDecoder::class, fn () => new class($fixture['assessment']->id) implements DataMatrixDecoder
    {
        public function __construct(private readonly int $assessmentId) {}

        public function decode(string $imagePath): iterable
        {
            yield ['payload' => "ROO1|A={$this->assessmentId}|K=PAGE", 'x_px' => 20, 'y_px' => 100, 'width_px' => 10, 'height_px' => 10];
        }
    });
    $sessionUrl = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session";
    $session = $this->actingAs($fixture['user'])->postJson($sessionUrl)->assertCreated()->json('session_id');
    $this->actingAs($fixture['user'])->post("{$sessionUrl}/{$session}/pages", [
        'image' => UploadedFile::fake()->image('page.png', 2480, 3508),
        'page' => 1,
    ])->assertCreated();

    $completeUrl = "{$sessionUrl}/{$session}/complete";
    $this->actingAs($fixture['user'])->postJson($completeUrl)->assertOk();
    $this->actingAs($fixture['user'])->postJson($completeUrl)
        ->assertOk()
        ->assertJsonPath('redirect_url', url("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung"));

    expect($fixture['assessment']->booklets()->count())->toBe(1)
        ->and(AssessmentScanMaterialization::query()->where('assessment_id', $fixture['assessment']->id)->where('session_id', $session)->count())->toBe(1);
});

it('materializes PAGE groups with a cross-page task fragment then deletes the completed session', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentScanMaterializationFixture();
    $sessions = app(AssessmentScanSessionStore::class);
    $session = $sessions->create($fixture['assessment'])['session_id'];

    $sessions->storePage($session, 1, UploadedFile::fake()->image('first-page.png', 2480, 3508));
    $sessions->storePageMarkers($session, 1, [
        ['kind' => 'PAGE', 'page' => 1, 'y_cm' => 1.0, 'y_px' => 118, 'assessment_id' => $fixture['assessment']->id],
        ['kind' => 'START', 'page' => 1, 'y_cm' => 4.0, 'y_px' => 472, 'task_id' => (string) $fixture['task']->id],
    ]);
    $sessions->storePage($session, 2, UploadedFile::fake()->image('second-page.png', 2480, 3508));
    $sessions->storePageMarkers($session, 2, [
        ['kind' => 'END', 'page' => 2, 'y_cm' => 12.0, 'y_px' => 1417, 'task_id' => (string) $fixture['task']->id],
    ]);
    $sessions->storePage($session, 3, UploadedFile::fake()->image('third-page.png', 2480, 3508));
    $sessions->storePageMarkers($session, 3, [
        ['kind' => 'PAGE', 'page' => 3, 'y_cm' => 1.0, 'y_px' => 118, 'assessment_id' => $fixture['assessment']->id],
        ['kind' => 'START', 'page' => 3, 'y_cm' => 5.0, 'y_px' => 591, 'task_id' => (string) $fixture['task']->id],
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
        ->and($booklets[0]->fragments->sole()->end_page)->toBe(2)
        ->and($booklets[0]->fragments->sole()->start_y_cm)->toBe('4.000')
        ->and($booklets[0]->fragments->sole()->end_y_cm)->toBe('12.000');

    Storage::disk('documents')->assertExists($booklets[0]->name_fragment_path);
    Storage::disk('documents')->assertExists($booklets[1]->name_fragment_path);
    Storage::disk('documents')->assertExists($booklets[0]->fragments->sole()->image_path);
    expect($sessions->manifest($session))->toBeNull();
});

it('returns the previously materialized booklets when a completed session is retried after deletion fails', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentScanMaterializationFixture();
    $sessions = app(AssessmentScanSessionStore::class);
    $session = $sessions->create($fixture['assessment'])['session_id'];
    $sessions->storePage($session, 1, UploadedFile::fake()->image('page.png', 2480, 3508));
    $sessions->storePageMarkers($session, 1, [
        ['kind' => 'PAGE', 'page' => 1, 'y_cm' => 1.0, 'y_px' => 118, 'assessment_id' => $fixture['assessment']->id],
    ]);
    $sessions->complete($session, ['booklets' => [], 'warnings' => []], []);
    $temporary = Storage::disk('temporary');
    $sessionFiles = collect($temporary->allFiles("assessment-scans/{$session}"))
        ->mapWithKeys(fn (string $path): array => [$path => $temporary->get($path)]);

    $first = app(MaterializeAssessmentScan::class)->handle($fixture['assessment'], $session);
    $sessionFiles->each(fn (string $contents, string $path) => $temporary->put($path, $contents));
    $retried = app(MaterializeAssessmentScan::class)->handle($fixture['assessment'], $session);

    expect($retried->pluck('id')->all())->toBe($first->pluck('id')->all())
        ->and($fixture['assessment']->booklets()->count())->toBe(1)
        ->and($sessions->manifest($session))->toBeNull();
});

it('retains the completed session and rolls back booklets when a crop cannot be created', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentScanMaterializationFixture();
    $sessions = app(AssessmentScanSessionStore::class);
    $session = $sessions->create($fixture['assessment'])['session_id'];
    $stored = $sessions->storePage($session, 1, UploadedFile::fake()->image('page.png', 2480, 3508));
    Storage::disk('temporary')->put($stored['path'], 'not a PNG');
    $sessions->storePageMarkers($session, 1, [
        ['kind' => 'PAGE', 'page' => 1, 'y_cm' => 1.0, 'y_px' => 118, 'assessment_id' => $fixture['assessment']->id],
    ]);
    $sessions->complete($session, ['booklets' => [], 'warnings' => []], []);

    expect(fn () => app(MaterializeAssessmentScan::class)->handle($fixture['assessment'], $session))
        ->toThrow(RuntimeException::class);

    expect($fixture['assessment']->booklets()->count())->toBe(0)
        ->and($sessions->manifest($session))->not->toBeNull();
});

it('quarantines foreign PAGE markers and retains a zero-booklet warning for the evaluation', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentScanMaterializationFixture();
    $sessions = app(AssessmentScanSessionStore::class);
    $session = $sessions->create($fixture['assessment'])['session_id'];
    $sessions->storePage($session, 1, UploadedFile::fake()->image('foreign-page.png', 2480, 3508));
    $sessions->storePageMarkers($session, 1, [
        ['kind' => 'PAGE', 'page' => 1, 'y_cm' => 1.0, 'y_px' => 118, 'assessment_id' => $fixture['assessment']->id + 1],
    ]);
    $sessions->complete($session, ['booklets' => [], 'warnings' => []], []);

    $booklets = app(MaterializeAssessmentScan::class)->handle($fixture['assessment'], $session);
    $warnings = AssessmentScanMaterialization::query()->sole()->warnings;

    expect($booklets)->toHaveCount(0)
        ->and($warnings)->toContain('Seite 1: Der PAGE-Marker gehört zu einer anderen Lernstandserhebung und wurde ignoriert.')
        ->and($warnings)->toContain('Es wurden keine passenden Booklet-Marker für diese Lernstandserhebung erkannt.');

    $this->actingAs($fixture['user'])
        ->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertInertia(fn ($page) => $page->where('scan.warnings', $warnings));
});

it('retains unmatched task marker warnings for the durable evaluation', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentScanMaterializationFixture();
    $sessions = app(AssessmentScanSessionStore::class);
    $session = $sessions->create($fixture['assessment'])['session_id'];
    $sessions->storePage($session, 1, UploadedFile::fake()->image('unmatched-page.png', 2480, 3508));
    $sessions->storePageMarkers($session, 1, [
        ['kind' => 'PAGE', 'page' => 1, 'y_cm' => 1.0, 'y_px' => 118, 'assessment_id' => $fixture['assessment']->id],
        ['kind' => 'START', 'page' => 1, 'y_cm' => 4.0, 'y_px' => 472, 'task_id' => (string) $fixture['task']->id],
    ]);
    $sessions->complete($session, ['booklets' => [], 'warnings' => []], []);

    app(MaterializeAssessmentScan::class)->handle($fixture['assessment'], $session);

    expect(AssessmentScanMaterialization::query()->sole()->warnings)
        ->toContain("Seite 1: START-Marker für Aufgabe {$fixture['task']->id} ohne END-Marker.");
});
