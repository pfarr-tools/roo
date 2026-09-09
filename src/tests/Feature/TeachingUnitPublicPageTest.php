<?php

use App\Models\ResourceReference;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function publicUnitFixture(): array
{
    $user = User::factory()->create(['name' => 'Lehrkraft Beispiel', 'email' => 'lehrkraft@example.test', 'public_phone' => '+49 170 1234567']);
    $school = School::create(['user_id' => $user->id, 'name' => 'Öffentliche Schule', 'messenger_name' => 'Untis']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'created_by_user_id' => $user->id, 'title' => 'Wasser des Lebens', 'position' => 1, 'introduction_text' => 'Eine Einführung für Familien.']);
    $lesson = $unit->lessons()->create(['title' => 'Wasserbilder', 'position' => 1, 'duration' => 1]);
    $phase = $lesson->phases()->create(['title' => 'Arbeitsphase', 'position' => 1]);
    $resource = ResourceReference::create(['user_id' => $user->id, 'teaching_unit_id' => $unit->id, 'lesson_id' => $lesson->id, 'original_name' => 'Arbeitsblatt.pdf', 'storage_path' => 'teaching-units/'.$unit->id.'/arbeitsblatt.pdf', 'mime_type' => 'application/pdf', 'security_status' => 'approved']);
    Storage::disk('local')->put($resource->storage_path, 'PDF-Inhalt');
    $phase->resources()->attach($resource, ['publication_status' => 'shared_immediately']);

    return compact('user', 'school', 'year', 'group', 'unit', 'lesson', 'phase', 'resource');
}

it('renders the current public unit page without authentication', function () {
    $fixture = publicUnitFixture();
    $url = URL::signedRoute('public.teaching-units.show', ['teachingUnit' => $fixture['unit']]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Wasser des Lebens')
        ->assertSee('4a')
        ->assertSee('Öffentliche Schule')
        ->assertSee('Lehrkraft Beispiel')
        ->assertSee('Untis')
        ->assertSee('lehrkraft@example.test')
        ->assertSee('+49 170 1234567')
        ->assertSee('Eine Einführung für Familien.')
        ->assertSee('Arbeitsblatt.pdf')
        ->assertHeader('content-type', 'text/html; charset=UTF-8');
});

it('rejects an invalid signature and a revoked public file download', function () {
    $fixture = publicUnitFixture();
    $pageUrl = URL::signedRoute('public.teaching-units.show', ['teachingUnit' => $fixture['unit']]);
    $downloadUrl = URL::signedRoute('public.teaching-units.resources.download', ['teachingUnit' => $fixture['unit'], 'resource' => $fixture['resource']]);

    $this->get($pageUrl.'&tampered=1')->assertForbidden();
    $this->get($downloadUrl)->assertOk()->assertHeader('content-disposition', 'attachment; filename=Arbeitsblatt.pdf');

    $fixture['phase']->resources()->updateExistingPivot($fixture['resource']->id, ['publication_status' => 'not_shared']);
    $this->get($downloadUrl)->assertNotFound();
});

it('uses the sole organization user for contacts on legacy units without a creator', function () {
    $user = User::factory()->create(['email' => 'legacy@example.test', 'public_phone' => '+49 170 7654321']);
    $school = School::create(['user_id' => $user->id, 'name' => 'Legacy Schule', 'messenger_name' => 'Untis']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Alte Einheit', 'position' => 1]);

    $this->get(URL::signedRoute('public.teaching-units.show', ['teachingUnit' => $unit]))
        ->assertOk()
        ->assertSee('legacy@example.test')
        ->assertSee('+49 170 7654321');
});
