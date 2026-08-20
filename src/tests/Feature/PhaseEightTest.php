<?php

use App\Models\LessonPhase;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Song;
use App\Models\SongVersion;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('legt ein Lied mit Fassung und A5-Liedblatt an', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Lieder Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->post('/lieder', [
        'title' => 'Geh aus, mein Herz', 'version_name' => 'A5-Fassung', 'rights_status' => 'cleared',
        'text_export_allowed' => true, 'sheet' => UploadedFile::fake()->create('liedblatt.pdf', 20, 'application/pdf'),
    ])->assertRedirect();

    $version = SongVersion::firstOrFail();
    expect($version->song->title)->toBe('Geh aus, mein Herz')->and($version->sheet)->not->toBeNull();
    Storage::disk('local')->assertExists($version->sheet->storage_path);
});

it('ordnet ein Lied über die gemeinsame Ressourcenroute einer Phase zu und führt es ins Gruppenliederbuch', function () {
    $organization = Organization::create(['name' => 'Zuordnung Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Liederschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $lesson = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Lied UE', 'position' => 1])->lessons()->create(['title' => 'Liedstunde', 'position' => 1, 'duration' => 1]);
    $phase = $lesson->phases()->create(['title' => 'Singen', 'position' => 1]);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Dona nobis pacem'])->versions()->create(['name' => 'Standardfassung']);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/ressourcen/song/{$version->id}/zuordnen", ['target_type' => 'phase', 'target_id' => $phase->id])->assertRedirect();

    expect($phase->fresh()->songs->pluck('id')->all())->toBe([$version->id])
        ->and($group->fresh()->songbook->entries->first()->song_number)->toBe(1);
});

it('schützt und speichert die Titelseite des Gruppenliederbuchs', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Titelseiten Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Titel Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5b']);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/liederbuch/titelseite", ['title_page' => UploadedFile::fake()->create('titelseite.pdf', 10, 'application/pdf')])->assertRedirect();
    $book = $group->fresh()->songbook;
    expect($book->title_page_original_name)->toBe('titelseite.pdf');
    Storage::disk('local')->assertExists($book->title_page_path);
});

it('speichert Liedteile mit Kehrvers und stellt das Gruppenliederbuch als Stundenressource bereit', function () {
    $organization = Organization::create(['name' => 'Editor Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Editor Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '6a']);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Lied mit Teilen'])->versions()->create(['name' => 'Schulfassung']);
    $this->actingAs($user)->put("/lieder/fassungen/{$version->id}", ['name' => 'Schulfassung', 'language' => 'de', 'parts' => [['title' => 'Strophe 1', 'content' => 'Text', 'is_refrain' => false], ['title' => 'Kehrvers', 'content' => 'Wiederholung', 'is_refrain' => true]]])->assertRedirect();
    expect($version->fresh()->parts)->toHaveCount(2)->and($version->fresh()->parts->last()->is_refrain)->toBeTrue();
    $group->songbook()->create();
    $this->actingAs($user)->get("/jahresplanung/{$group->id}/ressourcen", ['Accept' => 'application/json'])->assertOk()->assertJsonFragment(['kind' => 'songbook']);
});

it('bearbeitet Liedmetadaten und löscht eigene Lieder, aber keine globalen Lieder', function () {
    $organization = Organization::create(['name' => 'Liedpflege Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $song = Song::create(['organization_id' => $organization->id, 'title' => 'Alter Titel']);
    $version = $song->versions()->create(['name' => 'Fassung']);

    $this->actingAs($user)->put("/lieder/fassungen/{$version->id}", [
        'name' => 'Fassung', 'language' => 'de', 'song' => ['title' => 'Neuer Titel', 'composer' => 'Komponist'],
        'parts' => [['content' => "Zeile eins\nZeile zwei", 'is_refrain' => true]],
    ])->assertRedirect();

    $this->actingAs($user)->put("/lieder/fassungen/{$version->id}", [
        'name' => 'Fassung', 'language' => 'de',
        'parts' => [['content' => 'Nur ein aktualisierter Teil', 'is_refrain' => false]],
    ])->assertRedirect();

    expect($song->fresh()->title)->toBe('Neuer Titel')->and($version->fresh()->parts)->toHaveCount(1)->and($version->fresh()->parts->first()->content)->toBe('Nur ein aktualisierter Teil');
    $this->actingAs($user)->delete("/lieder/{$song->id}")->assertRedirect();
    expect(Song::find($song->id))->toBeNull();

    $global = Song::create(['title' => 'Globales Lied']);
    $this->actingAs($user)->delete("/lieder/{$global->id}")->assertNotFound();
    expect(Song::find($global->id))->not->toBeNull();
});

it('erzeugt einen datierten A5-Gruppenliederbuch-Export und einen Druckstand', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Export Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Export Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '7a']);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Exportlied'])->versions()->create(['name' => 'Fassung']);
    $book = $group->songbook()->create();
    $book->entries()->create(['song_version_id' => $version->id, 'song_number' => 1, 'added_at' => '2026-09-01']);

    $response = $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/liederbuch/export?format=a5&through_date=2026-09-30");
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($book->fresh()->exports)->toHaveCount(1)->and($book->fresh()->checkpoints)->toHaveCount(1);
});
