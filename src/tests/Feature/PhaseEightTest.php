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
