<?php

use App\Models\Organization;
use App\Models\ResourceReference;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\ScheduleSlot;
use App\Models\ScheduledLesson;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('saves the introduction and downloads a parent letter', function () {
    $organization = Organization::create(['name' => 'Elternbrief Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id, 'name' => 'Lehrkraft Elternbrief', 'email' => 'lehrkraft@example.test', 'public_phone' => '+49 170 1234567']);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Elternbrief Schule', 'city' => 'Stuttgart', 'messenger_name' => 'Untis']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4b', 'aktenzeichen' => '62.53']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'created_by_user_id' => $user->id, 'title' => 'Wasser des Lebens', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Wasserbilder', 'position' => 1, 'duration' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-10', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45', 'status' => 'free']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id, 'status' => 'planned']);
    $phase = $lesson->phases()->create(['title' => 'Arbeitsphase', 'position' => 1]);
    $resource = ResourceReference::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'lesson_id' => $lesson->id, 'original_name' => 'Arbeitsblatt.pdf', 'storage_path' => 'parent-letter/arbeitsblatt.pdf', 'mime_type' => 'application/pdf', 'security_status' => 'pending']);
    Storage::disk('local')->put($resource->storage_path, 'PDF-Inhalt');
    $phase->resources()->attach($resource, ['publication_status' => 'shared_immediately']);

    $response = $this->actingAs($user)->post(route('teaching-units.parent-letter.download', $unit), [
        'format' => 'docx',
        'introduction_text' => 'Eine Einführung für die Familien.',
    ]);

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertHeader('content-disposition', 'attachment; filename="62.53_4b_20260910 Elternbrief.docx"');
    expect($response->getContent())->toStartWith('PK');
    expect(parentLetterFeatureArchiveText($response->getContent()))
        ->toContain('Materialien online')
        ->toContain('Auf der Online-Seite zur Unterrichtseinheit')
        ->toContain(URL::route('public.teaching-units.show', ['teachingUnit' => $unit]))
        ->toContain('Untis')
        ->toContain('lehrkraft@example.test')
        ->toContain('+49 170 1234567')
        ->toContain('Lehrkraft Elternbrief');
    expect(parentLetterFeatureArchiveText($response->getContent()))
        ->toContain('Stuttgart, 10.09.2026');
    expect($unit->refresh()->introduction_text)->toBe('Eine Einführung für die Familien.');
    expect($user->preferences()->where('key', 'documents.parent-letter.format')->value('value'))->toBe(['format' => 'docx']);
});

it('liefert das zuletzt gewählte Elternbrief-Format für den nächsten Export', function () {
    $organization = Organization::create(['name' => 'Format Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Format Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Formatgruppe']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'created_by_user_id' => $user->id, 'title' => 'Format Einheit', 'position' => 1]);

    $this->actingAs($user)->post(route('teaching-units.parent-letter.download', $unit), [
        'format' => 'odt',
        'introduction_text' => '',
    ])->assertOk();

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}")->assertInertia(fn ($page) => $page
        ->where('parentLetterFormat', 'odt'));
});

function parentLetterFeatureArchiveText(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'roo-parent-letter-feature-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    $archive->open($path);
    $text = '';
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $name = (string) $archive->getNameIndex($index);
        if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
            $text .= (string) $archive->getFromIndex($index);
        }
    }
    $archive->close();
    unlink($path);

    return $text;
}
