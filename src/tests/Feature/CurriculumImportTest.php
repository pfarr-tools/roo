<?php

use App\Actions\Curricula\ImportCurriculum;
use App\Models\Curriculum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports every curriculum from the provided package', function () {
    $files = glob(base_path('../data/curricula/curricula/*.json'));
    expect($files)->toHaveCount(16);

    foreach ($files as $file) {
        $result = app(ImportCurriculum::class)->execute($file);
        expect($result['version']->topics()->count())->toBeGreaterThan(0);
    }

    expect(Curriculum::count())->toBe(16);
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
    $this->put("/curricula/{$own->id}/themen/{$topic->id}", ['title' => 'Neue UE', 'hours' => 3, 'notes' => 'Eigene Notiz', 'preparation_questions' => "Frage eins\nFrage zwei"])->assertRedirect();
    $this->post("/curricula/{$own->id}/themen", ['title' => 'Zusätzliche eigene UE', 'year' => 2, 'hours' => 2])->assertRedirect();

    expect($own->fresh()->title)->toBe('Mein bearbeitetes Curriculum')
        ->and($topic->fresh()->title)->toBe('Neue UE')
        ->and($topic->fresh()->preparation_questions)->toBe(['Frage eins', 'Frage zwei'])
        ->and($own->fresh()->topics()->where('title', 'Zusätzliche eigene UE')->value('year'))->toBe(2)
        ->and(Curriculum::where('external_identifier', 'GS_1-2_A')->first()->topics()->first()->title)->not->toBe('Neue UE');

    $this->delete("/curricula/{$own->id}")->assertRedirect('/curricula');
    expect(Curriculum::where('title', 'Mein bearbeitetes Curriculum')->exists())->toBeFalse();
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

it('imports explicit source grade assignments', function () {
    $result = app(ImportCurriculum::class)->execute(base_path('../data/curricula/curricula/GS_1-2_A.json'));

    expect($result['version']->topics()->where('external_identifier', 'ue-01')->value('year'))->toBe(2)
        ->and($result['version']->topics()->where('external_identifier', 'ue-02')->value('year'))->toBe(1)
        ->and($result['version']->topics()->where('external_identifier', 'ue-17')->value('year'))->toBe(2);
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
