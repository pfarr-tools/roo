<?php

use App\Models\AssessmentTask;
use App\Models\MaterialItem;
use App\Models\Organization;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Song;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('shows the complete organization library and protects its CRUD actions', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Bibliothek']);
    $otherOrganization = Organization::create(['name' => 'Andere Bibliothek']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $file = UploadedFile::fake()->create('Arbeitsblatt.pdf', 20, 'application/pdf');
    Storage::disk('local')->put('library/test.pdf', $file->getContent());
    $reference = ResourceReference::create(['organization_id' => $organization->id, 'original_name' => 'Arbeitsblatt.pdf', 'storage_path' => 'library/test.pdf', 'mime_type' => 'application/pdf', 'size' => 20]);
    ResourceLink::create(['organization_id' => $organization->id, 'title' => 'Religionspädagogik', 'url' => 'https://example.test/ru']);
    MaterialItem::create(['organization_id' => $organization->id, 'name' => 'Erzählkarten']);
    ResourceLink::create(['organization_id' => $otherOrganization->id, 'title' => 'Nicht sichtbar', 'url' => 'https://example.test/other']);

    $this->actingAs($user)->get('/ressourcen/bibliothek')->assertInertia(fn ($page) => $page->component('Resources/Library')->has('items', 3)->where('counts.resource', 1)->where('counts.total', 3));
    $this->actingAs($user)->post('/ressourcen/bibliothek/ressourcen', ['title' => 'Neue Quelle', 'url' => 'https://example.test/new'])->assertRedirect();
    $this->actingAs($user)->get('/ressourcen/bibliothek?q=Erzählkarten&type=material')->assertInertia(fn ($page) => $page->has('items', 1));
    $this->actingAs($user)->get('/ressourcen/bibliothek/dateien/'.$reference->id.'/download')->assertOk();
    expect(ResourceLink::where('organization_id', $organization->id)->count())->toBe(2);
});

it('zeigt Lieder mit Musikcredits in der Bibliothek', function () {
    $organization = Organization::create(['name' => 'Lieder Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $version = Song::create([
        'organization_id' => $organization->id,
        'title' => 'Unser Lied',
        'author' => 'Ada Text',
        'composer' => 'Ben Musik',
    ])->versions()->create(['name' => 'Standardfassung']);

    $this->actingAs($user)->get('/bibliothek')->assertInertia(fn ($page) => $page
        ->where('items.0.kind', 'song')
        ->where('items.0.name', 'Unser Lied')
        ->where('items.0.description', 'Text: Ada Text / Musik: Ben Musik')
        ->where('counts.song', 1));

    expect($version->fresh()->song->title)->toBe('Unser Lied');
});

it('öffnet den Liededitor unter der Bibliotheksroute', function () {
    $organization = Organization::create(['name' => 'Editor Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $version = Song::create(['organization_id' => $organization->id, 'title' => 'Editorlied'])
        ->versions()->create(['name' => 'Standardfassung']);

    $this->actingAs($user)->get('/bibliothek/lied/'.$version->id)
        ->assertInertia(fn ($page) => $page->component('Songs/Index')
            ->where('songVersion.id', $version->id)
            ->where('isCreating', false));

    $this->actingAs($user)->get('/bibliothek/lied/neu')
        ->assertInertia(fn ($page) => $page->component('Songs/Index')->where('isCreating', true));
});

it('speichert Beschreibung und Copyrights bei hochgeladenen Bibliotheksdateien', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Copyright Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->post('/ressourcen/bibliothek/dateien', [
        'resource' => UploadedFile::fake()->image('flux.png'),
        'description' => 'Eine freundliche Strichzeichnung',
        'copyrights' => 'FLUX.2 [flex] / Black Forest Labs / Ada Beispiel',
    ])->assertRedirect();

    $resource = ResourceReference::firstOrFail();
    expect($resource->description)->toBe('Eine freundliche Strichzeichnung')
        ->and($resource->copyrights)->toBe('FLUX.2 [flex] / Black Forest Labs / Ada Beispiel');
});

it('macht hochgeladene Bilder zu geschützten Bibliotheksressourcen', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Bildbibliothek']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $response = $this->actingAs($user)->postJson('/ressourcen/bibliothek/bilder', [
        'image' => UploadedFile::fake()->image('Karte.png'),
        'description' => 'Eine Karte',
        'copyrights' => 'Ada Beispiel',
    ]);

    $response->assertCreated()->assertJsonPath('image.name', 'Karte.png')->assertJsonPath('image.description', 'Eine Karte')->assertJsonPath('image.copyrights', 'Ada Beispiel');
    $resource = ResourceReference::firstOrFail();
    expect($resource->mime_type)->toBe('image/png')
        ->and($resource->source)->toBe('user_upload')
        ->and($resource->description)->toBe('Eine Karte')
        ->and($resource->copyrights)->toBe('Ada Beispiel')
        ->and(Storage::disk('local')->exists($resource->storage_path))->toBeTrue();
});

it('weist externe Bild-URLs bei Prüfungsaufgaben zurück', function () {
    $organization = Organization::create(['name' => 'Keine Bild-URLs']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', [
        'title' => 'Bildaufgabe',
        'task_type' => 'free_text_images',
        'content' => ['prompt' => 'Ordne zu'],
        'images' => [['url' => 'https://example.test/bild.png', 'label' => '', 'answer' => '']],
        'expectations' => [],
        'levels' => [],
    ])->assertSessionHasErrors('images.0.resource_id');
});

it('speichert Referenzpunkte für Bildbeschriftungen', function () {
    $organization = Organization::create(['name' => 'Beschriftungs Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Beschriftungsschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '6a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Pflanzen', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann beschriften']);
    $image = ResourceReference::create(['organization_id' => $organization->id, 'original_name' => 'Pflanze.png', 'storage_path' => 'library/pflanze.png', 'mime_type' => 'image/png', 'size' => 10]);

    $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', [
        'title' => 'Beschrifte die Pflanze',
        'task_type' => 'image_labeling',
        'content' => ['prompt' => 'Beschrifte die Pflanze.', 'image_label_width_cm' => 6.5, 'image_label_layout' => 'left', 'points_per_correct_answer' => 2, 'show_solutions' => true],
        'images' => [['resource_id' => $image->id]],
        'image_labels' => [['position' => 0, 'x_percent' => 25.5, 'y_percent' => 75, 'solution' => 'Stamm']],
        'expectations' => [],
        'competency_id' => $competency->id,
        'levels' => [],
    ])->assertRedirect();

    $task = AssessmentTask::firstOrFail();
    expect($task->task_type)->toBe('image_labeling')
        ->and($task->max_points)->toBe(2)
        ->and($task->content['image_label_layout'])->toBe('left')
        ->and($task->images)->toHaveCount(1)
        ->and($task->images->first()->labels)->toHaveCount(1)
        ->and((float) $task->images->first()->labels->first()->x_percent)->toBe(25.5);
});

it('legt wiederverwendbare Prüfungsaufgaben kompetenzbezogen an und ordnet sie Stunden zu', function () {
    $organization = Organization::create(['name' => 'Aufgaben Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Aufgabenschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann begründen']);
    $image = ResourceReference::create(['organization_id' => $organization->id, 'original_name' => 'Karte.png', 'storage_path' => 'library/karte.png', 'mime_type' => 'image/png', 'size' => 10]);

    $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', ['title' => 'Begründe deine Antwort', 'task_type' => 'free_text_images', 'content' => ['prompt' => 'Begründe deine Antwort', 'lines' => 5, 'lineated' => true], 'images' => [['resource_id' => $image->id, 'label' => 'Bild', 'answer' => 'Karte']], 'expectations' => [['text' => 'Korrektes Merkmal benannt', 'points' => 1, 'repetitions' => 3]], 'competency_id' => $competency->id, 'levels' => ['G', 'M']])->assertRedirect();

    $task = AssessmentTask::firstOrFail();
    expect($task->teaching_unit_competency_id)->toBe($competency->id)
        ->and($task->task_type)->toBe('free_text_images')
        ->and($task->content['lines'])->toBe(5)
        ->and($task->content['lineated'])->toBeTrue()
        ->and($task->max_points)->toBe(3)
        ->and($task->expectations)->toHaveCount(1)
        ->and($task->expectations->first()->repetitions)->toBe(3)
        ->and($task->levels()->pluck('level')->all())->toBe(['G', 'M'])
        ->and($task->images)->toHaveCount(1)
        ->and($task->images->first()->resource_reference_id)->toBe($image->id);
    $this->actingAs($user)->get('/bibliothek/pruefungsaufgaben/neu')
        ->assertInertia(fn ($page) => $page->component('AssessmentTask/Edit')->where('libraryMode', true)->where('method', 'post'));
    $this->actingAs($user)->get("/bibliothek/pruefungsaufgaben/{$task->id}/bearbeiten")
        ->assertInertia(fn ($page) => $page->component('AssessmentTask/Edit')->where('libraryMode', true)->where('task.title', 'Begründe deine Antwort'));
    $this->actingAs($user)->get('/bibliothek?type=assessment-task')->assertInertia(fn ($page) => $page->where('items.0.description', 'Kann begründen · G, M'));

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/ressourcen/assessment-task/{$task->id}/zuordnen", ['target_type' => 'lesson', 'target_id' => $lesson->id])->assertRedirect();
    expect($lesson->fresh()->assessmentTasks)->toHaveCount(1);
});
