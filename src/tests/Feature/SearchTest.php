<?php

use App\Models\Lesson;
use App\Models\LessonPhase;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Song;
use App\Models\SongVersion;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Meilisearch\Client;

uses(RefreshDatabase::class);

it('includes every song and lesson text field in searchable payloads', function () {
    $song = new Song([
        'title' => 'Liedtitel',
        'composer' => 'Komponist',
        'author' => 'Textdichter',
        'copyright_notice' => 'Rechtehinweis',
        'age_group' => 'Klasse 7',
        'topics' => 'Schöpfung',
        'notes' => 'Interne Notiz',
    ]);
    $version = new SongVersion([
        'name' => 'Gitarrenfassung',
        'language' => 'de',
        'lyrics' => 'Liedtext mit Suchbegriff',
        'notation' => 'Notation',
        'chords' => 'G D Em',
    ]);
    $lesson = new Lesson([
        'title' => 'Unterrichtsstunde',
        'learning_goals' => 'Lernzieltext',
        'materials' => 'Materialtext',
        'homework' => 'Hausaufgabentext',
        'assessment_note' => 'Bewertungsnotiz',
        'notes' => 'Stundennotiz',
    ]);
    $phase = new LessonPhase([
        'title' => 'Einstieg',
        'teacher_interaction' => 'Lehrerhandeln',
        'learner_activity' => 'Schüleraktivität',
        'differentiation' => 'Differenzierung',
        'didactic_comment' => 'Didaktischer Kommentar',
        'materials' => 'Phasenmaterial',
        'media' => 'Medium',
    ]);

    expect($song->toSearchableArray()['search_text'])->toContain('Interne Notiz')
        ->and($version->toSearchableArray()['search_text'])->toContain('Liedtext mit Suchbegriff')
        ->and($lesson->toSearchableArray()['search_text'])->toContain('Hausaufgabentext')
        ->and($lesson->toSearchableArray()['search_text'])->toContain('Bewertungsnotiz')
        ->and($phase->toSearchableArray()['search_text'])->toContain('Didaktischer Kommentar')
        ->and($phase->toSearchableArray()['search_text'])->toContain('Medium');
});

it('searches every lesson and song text column globally', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Suchschule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '7a']);
    $unit = TeachingUnit::create(['user_id' => $user->id, 'teaching_group_id' => $group->id, 'title' => 'Einheit', 'notes' => 'Einheitssuchtext']);
    $lesson = Lesson::create(['teaching_unit_id' => $unit->id, 'title' => 'Stunde', 'homework' => 'Hausaufgabensuchtext']);
    $phase = LessonPhase::create(['lesson_id' => $lesson->id, 'title' => 'Phase', 'didactic_comment' => 'Phasensuchtext']);
    $song = Song::create(['user_id' => $user->id, 'title' => 'Lied', 'notes' => 'Liedsuchtext']);
    $version = $song->versions()->create(['name' => 'Fassung', 'lyrics' => 'Liedtextsuchtext']);
    $version->parts()->create(['title' => 'Strophe', 'content' => 'Liedpartsuchtext', 'position' => 1]);

    $this->actingAs($user)->get('/suche?q=Suchtext')->assertInertia(fn ($page) => $page
        ->where('results.teachingUnits.0.id', $unit->id)
        ->where('results.lessons.0.id', $lesson->id)
        ->where('results.lessonPhases.0.id', $phase->id)
        ->where('results.songs.0.id', $song->id)
        ->where('results.songVersions.0.id', $version->id));

    $this->actingAs($user)->get('/suche?q=Liedpartsuchtext')->assertInertia(fn ($page) => $page
        ->where('results.songVersions.0.id', $version->id));
});

it('reaches Meilisearch and searches an index', function () {
    $client = app(Client::class);
    $indexName = 'roo-health-'.Str::lower(Str::random(12));
    $token = Str::random(24);

    expect(config('scout.driver'))->toBe('meilisearch')
        ->and($client->health()['status'])->toBe('available');

    try {
        $index = $client->index($indexName);
        $task = $index->addDocuments([
            ['id' => $token, 'content' => "Roo search health check {$token}"],
        ]);

        expect($client->waitForTask($task['taskUid'])['status'])->toBe('succeeded');

        $hits = $index->search($token)->getHits();

        expect($hits)->toHaveCount(1)
            ->and($hits[0]['id'])->toBe($token);
    } finally {
        $client->deleteIndex($indexName);
    }
});
