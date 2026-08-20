<?php

use App\Models\Organization;
use App\Models\ResourceReference;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Song;
use App\Models\SongVersion;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\SongbookContentsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('legt ein Lied mit Fassung und A5-Liedblatt an', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Lieder Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->post('/lieder', [
        'title' => 'Geh aus, mein Herz', 'version_name' => 'A5-Fassung',
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
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Dona nobis pacem'])->versions()->create(['name' => 'Fassung']);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/ressourcen/song/{$version->id}/zuordnen", ['target_type' => 'phase', 'target_id' => $phase->id])->assertRedirect();

    expect($phase->fresh()->songs->pluck('id')->all())->toBe([$version->id])
        ->and($group->fresh()->songbook->entries->first()->song_number)->toBe(1);
});

it('stellt ein über die Ressourcenbibliothek zugeordnetes Lied im Unterrichtsarbeitsraum und im Phasenpicker bereit', function () {
    $organization = Organization::create(['name' => 'Unterrichtslied Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Unterrichtslied Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $lesson = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Lied UE', 'position' => 1])->lessons()->create(['title' => 'Liedstunde', 'position' => 1, 'duration' => 1]);
    $phase = $lesson->phases()->create(['title' => 'Singen', 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Komm, wir singen', 'author' => 'Ada Text', 'composer' => 'Ben Musik'])->versions()->create(['name' => 'Fassung']);
    $version->parts()->create(['title' => 'Strophe 1', 'content' => 'Großer Liedtext', 'position' => 1]);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/ressourcen/song/{$version->id}/zuordnen", ['target_type' => 'lesson', 'target_id' => $lesson->id])->assertRedirect();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/ressourcen/song/{$version->id}/zuordnen", ['target_type' => 'phase', 'target_id' => $phase->id])->assertRedirect();

    expect($lesson->fresh()->songs->pluck('id')->all())->toBe([$version->id]);
    $this->actingAs($user)->get("/unterricht/{$slot->id}")->assertInertia(fn ($page) => $page
        ->where('lesson.songs.0.id', $version->id)
        ->where('lesson.songs.0.song.title', 'Komm, wir singen')
        ->where('lesson.songs.0.song.author', 'Ada Text')
        ->where('lesson.phases.0.song_ids.0', $version->id)
        ->where('lesson.phases.0.songs.0.parts.0.content', 'Großer Liedtext')
        ->where('songs.0.id', $version->id));
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

it('zeigt gespeicherte Ausgangslieder wieder in der Gruppenansicht an', function () {
    $organization = Organization::create(['name' => 'Ausgangslieder Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Ausgangslieder Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4c']);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Ausgangslied'])->versions()->create(['name' => 'Standard']);

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/liederbuch/lieder", ['song_version_ids' => [$version->id]])->assertRedirect();
    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}")->assertInertia(fn ($page) => $page
        ->where('group.songbook.entries.0.song_version_id', $version->id)
        ->where('group.songbook.entries.0.song_version.song.title', 'Ausgangslied'));
});

it('speichert Liedteile mit Kehrvers und Nummerierung und stellt das Gruppenliederbuch als Stundenressource bereit', function () {
    $organization = Organization::create(['name' => 'Editor Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Editor Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '6a']);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Lied mit Teilen'])->versions()->create(['name' => 'Schulfassung']);
    $this->actingAs($user)->put("/lieder/fassungen/{$version->id}", ['name' => 'Schulfassung', 'language' => 'de', 'parts' => [['title' => 'Strophe 1', 'content' => 'Text', 'is_refrain' => false, 'is_numbered' => true, 'is_repeated' => true, 'repeat_count' => 3], ['title' => 'Kehrvers', 'content' => 'Wiederholung', 'is_refrain' => true, 'is_numbered' => true, 'number' => 4], ['title' => 'Strophe 2', 'content' => 'Weiter', 'is_refrain' => false, 'is_numbered' => true]]])->assertRedirect();
    expect($version->fresh()->parts)->toHaveCount(3)
        ->and($version->fresh()->parts->first()->is_numbered)->toBeTrue()
        ->and($version->fresh()->parts->first()->is_repeated)->toBeTrue()
        ->and($version->fresh()->parts->first()->repeat_count)->toBe(3)
        ->and($version->fresh()->parts->get(1)->number)->toBe(4)
        ->and($version->fresh()->parts->last()->is_refrain)->toBeFalse();
    $group->songbook()->create();
    $this->actingAs($user)->get("/jahresplanung/{$group->id}/ressourcen", ['Accept' => 'application/json'])->assertOk()->assertJsonFragment(['kind' => 'songbook']);
});

it('bearbeitet Liedmetadaten und löscht eigene Lieder, aber keine globalen Lieder', function () {
    Storage::fake('local');
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
    expect($version->fresh()->generated_sheet_path)->not->toBeNull()->and($version->fresh()->generated_sheet_a4_path)->not->toBeNull();
    $generated = Storage::disk('local')->get($version->fresh()->generated_sheet_path);
    expect($generated)->toContain('/Title <FEFF')->toContain('/Author <FEFF')->toContain('/Subject <FEFF')->toContain('/Creator <FEFF');
    $a4Response = $this->actingAs($user)->get("/lieder/fassungen/{$version->id}/liedblatt/erzeugt/a4");
    $a4Response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($a4Response->headers->get('content-disposition'))->toContain('Neuer Titel.pdf');
    $this->actingAs($user)->delete("/lieder/{$song->id}")->assertRedirect();
    expect(Song::find($song->id))->toBeNull();

    $global = Song::create(['title' => 'Globales Lied']);
    $this->actingAs($user)->delete("/lieder/{$global->id}")->assertNotFound();
    expect(Song::find($global->id))->not->toBeNull();
});

it('speichert Akkordsätze pro Instrument an konkreten Textzeichen', function () {
    $organization = Organization::create(['name' => 'Akkord Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Akkordlied'])->versions()->create(['name' => 'Gitarrenfassung']);
    $part = $version->parts()->create(['content' => "Geh mit mir\nins Licht", 'position' => 1]);

    $this->actingAs($user)->get("/bibliothek/lied/{$version->id}")->assertInertia(fn ($page) => $page->where('songVersion.chord_sets', []));
    $this->actingAs($user)->put("/lieder/fassungen/{$version->id}", [
        'name' => 'Gitarrenfassung', 'language' => 'de',
        'parts' => [['id' => $part->id, 'content' => $part->content, 'is_refrain' => false]],
        'chord_sets' => [['instrument' => 'Gitarre', 'name' => 'Capo 2', 'key_signature' => 'G-Dur', 'chords' => [
            ['song_part_id' => $part->id, 'line_number' => 0, 'character_offset' => 0, 'chord' => 'G'],
            ['song_part_id' => $part->id, 'line_number' => 1, 'character_offset' => 3, 'chord' => 'C'],
            ['song_part_id' => $part->id, 'line_number' => 0, 'repetition' => 1, 'character_offset' => 0, 'chord' => 'Em'],
        ]]],
    ])->assertRedirect();

    expect($version->fresh()->chordSets)->toHaveCount(1)
        ->and($version->fresh()->chordSets->first()->instrument)->toBe('Gitarre')
        ->and($version->fresh()->chordSets->first()->key_signature)->toBe('G-Dur')
        ->and($version->fresh()->chordSets->first()->chords)->toHaveCount(3)
        ->and($version->fresh()->chordSets->first()->chords->firstWhere('repetition', 1)->chord)->toBe('Em');
});

it('erneuert ungültige erzeugte Liedblätter vor dem Download', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'PDF Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'PDF Lied'])->versions()->create(['name' => 'Fassung']);
    Storage::disk('local')->put('songs/generated/old.pdf', "%PDF-1.4\nstartxref\n123\n%%EOF\n/FontName /DejaVuSans");
    $version->update(['generated_sheet_path' => 'songs/generated/old.pdf']);

    $response = $this->actingAs($user)->get("/lieder/fassungen/{$version->id}/liedblatt/erzeugt");

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('PDF Lied A5.pdf');
    expect($response->headers->get('cache-control'))->toContain('no-store');
    $generated = Storage::disk('local')->get($version->fresh()->generated_sheet_path);
    expect($generated)->toStartWith('%PDF-')->toContain('%%EOF');
});

it('erzeugt einen datierten A5-Gruppenliederbuch-Export und einen Druckstand', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Export Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Export Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '7a']);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Exportlied'])->versions()->create(['name' => 'Fassung']);
    $lesson = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Exportstunde', 'position' => 1])->lessons()->create(['title' => 'Erste Stunde', 'position' => 1, 'duration' => 1]);
    $phaseVersion = Song::create(['organization_id' => $organization->id, 'title' => 'Stundenlied'])->versions()->create(['name' => 'Fassung']);
    $lesson->phases()->create(['title' => 'Singen', 'position' => 1])->songs()->attach($phaseVersion->id);
    $book = $group->songbook()->create();
    $book->entries()->create(['song_version_id' => $version->id, 'song_number' => 1, 'added_at' => '2026-09-01']);

    $response = $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/liederbuch/export?format=a5&through_date=2026-09-30");
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($book->fresh()->entries)->toHaveCount(1)->and($book->fresh()->entries->pluck('song_version_id')->all())->not->toContain($phaseVersion->id)
        ->and($book->fresh()->exports)->toHaveCount(1)->and($book->fresh()->checkpoints)->toHaveCount(1);

    $newSongs = app(SongbookContentsResolver::class)->resolve($book->fresh(), null, now()->subDay()->toDateString());
    expect($newSongs->pluck('song_version_id')->all())->not->toContain($version->id)->toContain($phaseVersion->id);
});

it('übernimmt Bibliotheksbilder in Liedfassungen und löscht sie wieder', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Bild Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Bildlied'])->versions()->create(['name' => 'Fassung']);

    $this->actingAs($user)->post('/ressourcen/bibliothek/dateien', ['resource' => UploadedFile::fake()->image('quelle.png'), 'copyrights' => 'Bibliothek / Ada Beispiel'])->assertRedirect();
    $resource = ResourceReference::firstOrFail();
    $this->actingAs($user)->post("/lieder/fassungen/{$version->id}/bilder/bibliothek", ['resource_id' => $resource->id])->assertRedirect();
    $image = $version->fresh()->images->firstOrFail();
    expect($image->original_name)->toBe('quelle.png')->and($image->copyrights)->toBe('Bibliothek / Ada Beispiel');

    Storage::disk('local')->put('songs/images/cache.png', 'image-data');
    $image->update(['storage_path' => 'songs/images/cache.png', 'mime_type' => 'image/png']);
    $imageResponse = $this->actingAs($user)->get("/lieder/fassungen/{$version->id}/bilder/{$image->id}?v={$image->updated_at->timestamp}");
    $imageResponse->assertOk();
    expect($imageResponse->headers->get('cache-control'))->toContain('no-store');

    $version->update(['layout_data' => ['images' => [['id' => $image->id, 'x' => 20, 'y' => 20]]]]);
    $this->actingAs($user)->delete("/lieder/fassungen/{$version->id}/bilder/{$image->id}")->assertRedirect();
    expect($version->fresh()->images)->toBeEmpty()->and($version->fresh()->layout_data['images'])->toBeEmpty();
});
