<?php

use App\Models\ResourceReference;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('uploads multiple images to the lesson gallery and serves them on the signed public page', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Galerie Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'created_by_user_id' => $user->id, 'title' => 'Galerieeinheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Fotostunde', 'position' => 1, 'duration' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-10', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45', 'status' => 'free']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id, 'status' => 'planned']);

    $this->actingAs($user)->post(route('year-plans.lessons.gallery.store', [$group, $lesson]), [
        'images' => [
            UploadedFile::fake()->image('erstes-bild.jpg', 100, 80),
            UploadedFile::fake()->image('zweites-bild.png', 120, 90),
        ],
    ])->assertRedirect();

    expect($lesson->galleryImages()->count())->toBe(2);
    $galleryImage = $lesson->galleryImages()->with('resource')->first();
    Storage::disk('local')->assertExists($galleryImage->resource->storage_path);

    $pageUrl = URL::signedRoute('public.teaching-units.show', ['teachingUnit' => $unit]);
    $this->get($pageUrl)
        ->assertOk()
        ->assertSee('Bilder vom 10.09.2026')
        ->assertSee('erstes-bild.jpg');

    $imageUrl = URL::signedRoute('public.teaching-units.gallery.image', ['teachingUnit' => $unit, 'galleryImage' => $galleryImage]);
    $this->get($imageUrl)->assertOk()->assertHeader('content-type', 'image/jpeg');

    $this->actingAs($user)->get(route('lessons.show', $slot))
        ->assertInertia(fn ($page) => $page
            ->component('Lessons/Show')
            ->where('lesson.gallery_images.0.name', 'erstes-bild.jpg')
            ->where('lesson.gallery_images.0.preview_url', fn ($url) => str_contains($url, '/ressourcen/bibliothek/dateien/')));
});

it('does not allow a gallery image from another lesson to be deleted', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Galerie Scope Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4b']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Scope', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde A', 'position' => 1, 'duration' => 1]);
    $otherLesson = $unit->lessons()->create(['title' => 'Stunde B', 'position' => 2, 'duration' => 1]);
    $galleryImage = $otherLesson->galleryImages()->create(['resource_reference_id' => ResourceReference::create(['user_id' => $user->id, 'original_name' => 'bild.jpg', 'storage_path' => 'bild.jpg', 'mime_type' => 'image/jpeg'])->id, 'position' => 0]);

    $this->actingAs($user)->delete(route('year-plans.lessons.gallery.destroy', [$group, $lesson, $galleryImage]))->assertNotFound();
});
