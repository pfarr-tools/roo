<?php

use App\Actions\Curricula\ImportCurriculum;
use App\Actions\EducationPlans\ImportEducationPlan;
use App\Models\Curriculum;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'BP2016BW_ALLG_GS_REV.json',
        'BP2016BW_ALLG_GS_RRK.json',
        'BP2016BW_ALLG_GYM_REV.json',
        'BP2016BW_ALLG_GYM_RRK.json',
        'BP2016BW_ALLG_SEK1_REV.json',
        'BP2016BW_ALLG_SEK1_RRK.json',
    ] as $file) {
        app(ImportEducationPlan::class)->execute(base_path('../data/bildungsplaene/plans/'.$file));
    }
});

it('imports every curriculum from the provided package', function () {
    $files = array_values(array_filter(glob(base_path('../data/curricula/curricula/*.json')), fn (string $file): bool => ! str_ends_with($file, '.validation.json')));
    expect($files)->toHaveCount(16);

    foreach ($files as $file) {
        $result = app(ImportCurriculum::class)->execute($file);
        expect($result['version']->topics()->count())->toBeGreaterThan(0);
        expect($result['version']->topics()->whereHas('competencies', fn ($query) => $query->where('competency_kind', 'process')->whereNull('denomination'))->count())->toBe(0);
    }

    expect(Curriculum::count())->toBe(16);
});

it('imports unit perspectives by denomination including common content', function () {
    $result = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_3-4_B.json'));
    $topic = $result['version']->topics()->where('external_identifier', 'ue-01')->firstOrFail();

    expect($topic->perspectives()->pluck('text', 'denomination')->all())
        ->toHaveKeys(['evangelical', 'catholic', 'common'])
        ->and($topic->perspectives()->where('denomination', 'common')->value('text'))->not->toBe('');
});

it('compares two visible curricula', function () {
    $first = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'))['curriculum'];
    $second = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_3-4_A.json'))['curriculum'];
    $user = User::factory()->create();

    $this->actingAs($user)->get('/curricula/vergleichen?left='.$first->id.'&right='.$second->id)->assertSuccessful()->assertInertia(fn ($page) => $page->where('left.id', $first->id)->where('right.id', $second->id)->has('left.topics')->has('right.topics'));
});

it('creates a new editable curriculum version by copying the current version', function () {
    $result = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'));
    $user = User::factory()->create();
    $this->actingAs($user)->post('/curricula', ['title' => 'Versionscurriculum', 'grades' => [1, 2], 'source_version_ids' => [$result['version']->id]])->assertRedirect();
    $curriculum = Curriculum::where('title', 'Versionscurriculum')->firstOrFail();
    $originalCount = $curriculum->versions()->firstOrFail()->topics()->count();

    $this->actingAs($user)->post('/curricula/'.$curriculum->id.'/fassungen')->assertRedirect();

    expect($curriculum->versions()->count())->toBe(2)
        ->and($curriculum->versions()->latest('id')->first()->topics()->count())->toBe($originalCount)
        ->and($curriculum->versions()->latest('id')->first()->is_editable)->toBeTrue();
});

it('creates and edits an own curriculum without changing the source', function () {
    $imported = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'));
    $user = User::factory()->create();

    $this->actingAs($user)->delete("/curricula/{$imported['curriculum']->id}")->assertForbidden();
    $importedTopic = $imported['version']->topics()->firstOrFail();
    $this->actingAs($user)->post("/curricula/{$imported['curriculum']->id}/themen/{$importedTopic->id}/jahr", ['year' => 1])->assertRedirect();
    expect($importedTopic->fresh()->year)->toBe(1);

    $this->actingAs($user)->post('/curricula', [
        'title' => 'Mein Religionscurriculum',
        'school_type' => 'GS',
        'grades' => [1, 2],
        'source_version_ids' => [$imported['version']->id],
    ])->assertRedirect();

    $own = Curriculum::where('title', 'Mein Religionscurriculum')->firstOrFail();
    $topic = $own->versions()->firstOrFail()->topics()->firstOrFail();
    $this->put("/curricula/{$own->id}", ['title' => 'Mein bearbeitetes Curriculum', 'school_type' => 'GS', 'grades' => [1, 2]])->assertRedirect();
    $this->put("/curricula/{$own->id}/themen/{$topic->id}", ['title' => 'Neue UE', 'hours' => 3, 'notes' => 'Eigene Notiz', 'preparation_questions' => "Frage eins\nFrage zwei", 'perspectives' => ['common' => 'Gemeinsame Perspektive', 'evangelical' => 'Evangelische Perspektive', 'catholic' => 'Katholische Perspektive']])->assertRedirect();
    $this->post("/curricula/{$own->id}/themen", ['title' => 'Zusätzliche eigene UE', 'year' => 2, 'hours' => 2])->assertRedirect();

    expect($own->fresh()->title)->toBe('Mein bearbeitetes Curriculum')
        ->and($topic->fresh()->title)->toBe('Neue UE')
        ->and($topic->fresh()->preparation_questions)->toBe(['Frage eins', 'Frage zwei'])
        ->and($topic->fresh()->perspectives()->where('denomination', 'common')->value('text'))->toBe('Gemeinsame Perspektive')
        ->and($own->fresh()->topics()->where('title', 'Zusätzliche eigene UE')->value('year'))->toBe(2)
        ->and(Curriculum::where('external_identifier', 'GS_1-2_A')->first()->topics()->first()->title)->not->toBe('Neue UE');

    $this->delete("/curricula/{$own->id}")->assertRedirect('/curricula');
    expect(Curriculum::where('title', 'Mein bearbeitetes Curriculum')->exists())->toBeFalse();
});

it('toggles editing for imported curricula outside production', function () {
    $imported = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'));
    $user = User::factory()->create();

    $this->actingAs($user)->post("/curricula/{$imported['curriculum']->id}/bearbeitung")->assertRedirect();
    expect($imported['version']->fresh()->is_editable)->toBeTrue();

    $this->actingAs($user)->post("/curricula/{$imported['curriculum']->id}/bearbeitung")->assertRedirect();
    expect($imported['version']->fresh()->is_editable)->toBeFalse();
});

it('assigns all copied units when the source covers exactly one grade', function () {
    $imported = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/SEK1_10_A.json'));
    $user = User::factory()->create();

    $this->actingAs($user)->post('/curricula', [
        'title' => 'Mein Klasse-10-Curriculum',
        'grades' => [10],
        'source_version_ids' => [$imported['version']->id],
    ])->assertRedirect();

    $own = Curriculum::where('title', 'Mein Klasse-10-Curriculum')->firstOrFail();
    expect($own->topics()->whereNull('year')->count())->toBe(0)
        ->and($own->topics()->where('year', 10)->count())->toBe(5);
});

it('imports all units with their assigned years', function () {
    $result = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'));

    expect($result['version']->topics()->count())->toBe(17)
        ->and($result['version']->topics()->where('year', 1)->count())->toBe(9)
        ->and($result['version']->topics()->where('year', 2)->count())->toBe(8)
        ->and($result['version']->topics()->whereNull('year')->count())->toBe(0);
});

it('derives the visible grade metadata from selected sources when none is entered', function () {
    $first = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'));
    $second = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_3-4_A.json'));
    $user = User::factory()->create();

    $this->actingAs($user)->post('/curricula', [
        'title' => 'Grundschule komplett',
        'source_version_ids' => [$first['version']->id, $second['version']->id],
    ])->assertRedirect();

    expect(Curriculum::where('title', 'Grundschule komplett')->value('grades'))->toBe([1, 2, 3, 4]);
});

it('creates a blank curriculum and supports denominational process competencies', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/curricula', [
        'title' => 'Leeres Curriculum',
        'denominations' => ['evangelical', 'catholic'],
    ])->assertRedirect();

    $curriculum = Curriculum::where('title', 'Leeres Curriculum')->firstOrFail();
    expect($curriculum->topics()->count())->toBe(0)
        ->and($curriculum->denominations)->toBe(['evangelical', 'catholic']);

    $source = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'));
    $this->actingAs($user)->post('/curricula', [
        'title' => 'Kompetenztest',
        'source_version_ids' => [$source['version']->id],
    ])->assertRedirect();
    $own = Curriculum::where('title', 'Kompetenztest')->firstOrFail();
    $topic = $own->topics()->firstOrFail();
    $competencyId = $topic->educationPlanReferences()->value('education_plan_competency_id');

    $this->put("/curricula/{$own->id}/themen/{$topic->id}/kompetenzen", [
        'competencies' => [['denomination' => 'catholic', 'competency_kind' => 'process', 'education_plan_competency_id' => $competencyId]],
    ])->assertRedirect();

    expect($topic->fresh()->competencies()->where('competency_kind', 'process')->value('denomination'))->toBe('catholic');
});

it('allows selecting a Bildungsplan binding and resolves matching competencies', function () {
    $plan = app(ImportEducationPlan::class)->execute(base_path('../data/bildungsplaene/plans/BP2016BW_ALLG_GS_REV.json'));
    $imported = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'));
    $user = User::factory()->create();

    $this->actingAs($user)->put("/curricula/{$imported['curriculum']->id}", [
        'title' => $imported['curriculum']->title,
        'school_type' => $imported['curriculum']->school_type,
        'grades' => $imported['curriculum']->grades,
        'denominations' => $imported['curriculum']->denominations,
        'education_plan_bindings' => [['denomination' => 'evangelical', 'role' => 'denominational_basis', 'subject' => 'Evangelische Religionslehre', 'plan_code' => 'BP2016BW_ALLG_GS_REV']],
    ])->assertRedirect();

    expect($imported['version']->fresh()->bindings()->where('education_plan_id', $plan['plan']->id)->exists())->toBeTrue()
        ->and($imported['version']->topics()->firstOrFail()->educationPlanReferences()->whereNotNull('education_plan_competency_id')->exists())->toBeTrue()
        ->and($imported['version']->topics()->firstOrFail()->educationPlanReferences()->whereNotNull('education_plan_competency_id')->first()->educationPlanCompetency->text)->not->toBeNull();

    $topic = $imported['version']->topics()->firstOrFail();
    $unboundCompetencyId = EducationPlanCompetency::query()
        ->whereHas('area', fn ($query) => $query->whereIn('education_plan_version_id', EducationPlanVersion::query()->whereHas('plan', fn ($query) => $query->where('external_identifier', 'BP2016BW_ALLG_GS_RRK'))->pluck('id')))
        ->value('id');
    $this->actingAs($user)->put("/curricula/{$imported['curriculum']->id}/themen/{$topic->id}/kompetenzen", [
        'competencies' => [['denomination' => 'catholic', 'competency_kind' => 'process', 'education_plan_competency_id' => $unboundCompetencyId]],
    ])->assertForbidden();
    $this->actingAs($user)->put("/curricula/{$imported['curriculum']->id}/themen/{$topic->id}/kompetenzen", [
        'competencies' => [['denomination' => 'evangelical', 'competency_kind' => 'content', 'education_plan_competency_id' => $unboundCompetencyId]],
    ])->assertForbidden();

    app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'));
    expect($imported['version']->fresh()->bindings()->where('plan_code', 'BP2016BW_ALLG_GS_REV')->exists())->toBeTrue()
        ->and($imported['version']->topics()->firstOrFail()->competencies()->whereNotNull('education_plan_competency_id')->exists())->toBeTrue();
});
