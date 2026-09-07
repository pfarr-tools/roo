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

it('erstellt gedroppte Dateien und URLs als Bibliothekseinträge und gibt sie zurück', function () {
    Storage::fake('local');
    $organization = Organization::create(['name' => 'Drop Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $response = $this->actingAs($user)->postJson('/ressourcen/bibliothek/drop', [
        'files' => [UploadedFile::fake()->create('Arbeitsblatt.pdf', 20, 'application/pdf')],
        'urls' => ['https://example.test/arbeitsblatt'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('items.0.kind', 'file')
        ->assertJsonPath('items.0.name', 'Arbeitsblatt.pdf')
        ->assertJsonPath('items.1.kind', 'resource')
        ->assertJsonPath('items.1.url', 'https://example.test/arbeitsblatt');
    expect(ResourceReference::where('organization_id', $organization->id)->count())->toBe(1)
        ->and(ResourceLink::where('organization_id', $organization->id)->count())->toBe(1);
});

it('isoliert gedroppte Bibliothekseinträge nach Organisation', function () {
    $organization = Organization::create(['name' => 'Eigene Drop Organisation']);
    $otherOrganization = Organization::create(['name' => 'Andere Drop Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)->postJson('/ressourcen/bibliothek/drop', [
        'urls' => ['https://example.test/eigene-ressource'],
    ])->assertCreated();

    expect(ResourceLink::where('organization_id', $organization->id)->count())->toBe(1)
        ->and(ResourceLink::where('organization_id', $otherOrganization->id)->count())->toBe(0);
});

it('speichert eine URL-Ressource aus dem geöffneten Drop-Editor', function () {
    $organization = Organization::create(['name' => 'Drop Editor Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $resource = ResourceLink::create([
        'organization_id' => $organization->id,
        'title' => 'https://example.test/alt',
        'url' => 'https://example.test/alt',
    ]);

    $this->actingAs($user)->put('/ressourcen/bibliothek/resource/'.$resource->id, [
        'title' => 'Neue Quelle',
        'url' => 'https://example.test/neu',
        'description' => 'Aus dem Drop-Editor gespeichert',
    ])->assertRedirect();

    expect($resource->fresh()->title)->toBe('Neue Quelle')
        ->and($resource->fresh()->url)->toBe('https://example.test/neu');
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
        'task_type' => 'free_text',
        'content' => ['prompt' => 'Ordne zu'],
        'images' => [['url' => 'https://example.test/bild.png', 'label' => '', 'answer' => '']],
        'expectations' => [],
        'levels' => [],
    ])->assertSessionHasErrors('images.0.resource_id');
});

it('speichert Sätze sortieren mit stabilen Satz-IDs und Punkten pro Satz', function () {
    $organization = Organization::create(['name' => 'Sortier Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Sortierschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann ordnen']);

    $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', [
        'title' => 'Sätze ordnen',
        'task_type' => 'sorting',
        'content' => [
            'prompt' => 'Bringe die Sätze in die richtige Reihenfolge.',
            'points_per_sentence' => 2,
            'questions' => [
                ['id' => 'sentence-a', 'label' => 'A'],
                ['id' => 'sentence-b', 'label' => 'B'],
            ],
        ],
        'expectations' => [],
        'competency_id' => $competency->id,
        'levels' => [],
    ])->assertRedirect();

    $task = AssessmentTask::firstOrFail();
    expect($task->content['points_per_sentence'])->toBe(2)
        ->and($task->content['questions'][0]['id'])->toBe('sentence-a')
        ->and($task->max_points)->toBe(4);
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

    $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', ['title' => 'Begründe deine Antwort', 'task_type' => 'free_text', 'content' => ['prompt' => 'Begründe deine Antwort', 'lines' => 5, 'lineated' => true, 'image_width_cm' => 3.5, 'optional_reading_text' => 'Lies diesen Text.', 'rating_scale' => 'stars', 'rating_scale_label' => 'Wie sicher bist du?'], 'images' => [['resource_id' => $image->id, 'label' => 'Bild', 'answer' => 'Karte']], 'expectations' => [['text' => 'Korrektes Merkmal benannt', 'points' => 1, 'repetitions' => 3]], 'competency_id' => $competency->id, 'levels' => ['G', 'M']])->assertRedirect();

    $task = AssessmentTask::firstOrFail();
    expect($task->teaching_unit_competency_id)->toBe($competency->id)
        ->and($task->task_type)->toBe('free_text')
        ->and($task->content['lines'])->toBe(5)
        ->and($task->content['lineated'])->toBeTrue()
        ->and($task->content['image_width_cm'])->toBe(3.5)
        ->and($task->content['optional_reading_text'])->toBe('Lies diesen Text.')
        ->and($task->content['rating_scale'])->toBe('stars')
        ->and($task->content['rating_scale_label'])->toBe('Wie sicher bist du?')
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
    $this->actingAs($user)->get('/bibliothek?type=assessment-task&education_plan_competency_id='.$competency->education_plan_competency_id)
        ->assertInertia(fn ($page) => $page->where('items.0.id', $task->id));

    $lesson->assessmentTasks()->detach($task->id);
    expect($lesson->fresh()->assessmentTasks)->toHaveCount(0);

    $this->actingAs($user)->postJson("/jahresplanung/{$group->id}/ressourcen/assessment-task/{$task->id}/zuordnen", ['target_type' => 'lesson', 'target_id' => $lesson->id])
        ->assertOk()
        ->assertJsonPath('task.id', $task->id)
        ->assertJsonPath('task.title', 'Begründe deine Antwort')
        ->assertJsonPath('task.teaching_unit_competency_id', $competency->id);
    expect($lesson->fresh()->assessmentTasks)->toHaveCount(1);

    $this->actingAs($user)->postJson("/jahresplanung/{$group->id}/ressourcen/assessment-task/{$task->id}/trennen", ['target_type' => 'lesson', 'target_id' => $lesson->id])
        ->assertOk()
        ->assertJsonPath('message', 'Prüfungsaufgabe wurde entfernt.');
    expect($lesson->fresh()->assessmentTasks)->toHaveCount(0);
});

it('speichert eine Tabelle mit Teilaufgaben ohne generische Spaltenüberschriften', function () {
    $organization = Organization::create(['name' => 'Teilaufgaben Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Teilaufgabenschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann zuordnen']);

    $response = $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', [
        'title' => 'Teilaufgaben',
        'task_type' => 'subtask_table',
        'content' => [
            'prompt' => 'Bearbeite die Teilaufgaben.',
            'show_solutions' => true,
            'lineated' => true,
            'subtasks' => [
                ['key' => 'a', 'label' => 'Nenne ein Beispiel.', 'solution' => 'Ein Beispiel', 'lines' => 2, 'points' => 1],
                ['key' => 'b', 'label' => 'Begründe.', 'solution' => '', 'lines' => 4],
            ],
        ],
        'expectations' => [['subtask_key' => 'b', 'text' => 'Begründung nennt das Merkmal.', 'points' => 2, 'repetitions' => 1]],
        'competency_id' => $competency->id,
        'levels' => [],
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $task = AssessmentTask::firstOrFail();
    expect($task->content['subtasks'])->toHaveCount(2)
        ->and($task->content['show_solutions'])->toBeTrue()
        ->and($task->expectations)->toHaveCount(2)
        ->and($task->expectations->first()->subtask_key)->toBe('a')
        ->and($task->expectations->first()->text)->toBe('Lösung: Ein Beispiel')
        ->and($task->expectations->last()->subtask_key)->toBe('b')
        ->and($task->maximumPoints())->toBe(3);
});

it('speichert eine Tabelle mit Bildern und Lösungsfeldern als Bildzeilen', function () {
    $organization = Organization::create(['name' => 'Bildtabellen Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Bildtabellenschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann darstellen']);
    $image = ResourceReference::create(['organization_id' => $organization->id, 'original_name' => 'Baum.png', 'storage_path' => 'library/baum.png', 'mime_type' => 'image/png', 'size' => 10]);

    $response = $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', [
        'title' => 'Bildtabelle',
        'task_type' => 'image_answer_table',
        'content' => [
            'prompt' => 'Bearbeite die Bildtabelle.',
            'image_width_cm' => 2.5,
            'show_solutions' => true,
            'lineated' => true,
            'subtasks' => [
                ['key' => 'a', 'image_identifier' => 'pair-a', 'solution' => 'Baum', 'lines' => 2, 'points' => 1],
                ['key' => 'b', 'image_identifier' => 'pair-b', 'solution' => '', 'lines' => 3],
            ],
        ],
        'images' => [['identifier' => 'pair-a', 'resource_id' => $image->id]],
        'expectations' => [['subtask_key' => 'b', 'text' => 'Merkmal genannt.', 'points' => 2, 'repetitions' => 1]],
        'competency_id' => $competency->id,
        'levels' => [],
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $task = AssessmentTask::firstOrFail();
    expect($task->task_type)->toBe('image_answer_table')
        ->and($task->content['image_width_cm'])->toBe(2.5)
        ->and($task->content['subtasks'][0]['image_identifier'])->toBe('pair-a')
        ->and($task->expectations)->toHaveCount(2)
        ->and($task->expectations->first()->text)->toBe('Lösung: Baum')
        ->and($task->maximumPoints())->toBe(3)
        ->and($task->images)->toHaveCount(1);
});

it('speichert eine Gestaltungsaufgabe mit manuellen Erwartungen', function () {
    $organization = Organization::create(['name' => 'Gestaltungsaufgaben Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Gestaltungsschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann gestalten']);

    $response = $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', [
        'title' => 'Gestaltungsaufgabe',
        'task_type' => 'drawing',
        'content' => ['prompt' => 'Male ein Bild.', 'height_cm' => 7.5, 'bordered' => true],
        'expectations' => [['text' => 'Das Bild enthält ein religiöses Symbol.', 'points' => 2, 'repetitions' => 1]],
        'competency_id' => $competency->id,
        'levels' => [],
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $task = AssessmentTask::firstOrFail();
    expect($task->task_type)->toBe('drawing')
        ->and($task->content['height_cm'])->toBe(7.5)
        ->and($task->content['bordered'])->toBeTrue()
        ->and($task->expectations)->toHaveCount(1)
        ->and($task->maximumPoints())->toBe(2);
});

it('speichert Lückentexte mit automatisch synchronisierten Erwartungen', function () {
    $organization = Organization::create(['name' => 'Lückentext Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Lückentextschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann ergänzen']);

    $response = $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', [
        'title' => 'Lückentext',
        'task_type' => 'cloze',
        'content' => [
            'prompt' => 'Die [Kirche] steht neben dem [Rathaus].',
            'show_solutions' => true,
            'lineated' => true,
            'split_blank_words' => false,
            'blanks' => [
                ['id' => 'blank-1', 'solution' => 'Kirche', 'points' => 2],
                ['id' => 'blank-2', 'solution' => 'Rathaus', 'points' => 3],
            ],
        ],
        'expectations' => [],
        'competency_id' => $competency->id,
        'levels' => [],
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $task = AssessmentTask::firstOrFail();
    expect($task->task_type)->toBe('cloze')
        ->and($task->content['blanks'])->toBe([
            ['id' => 'blank-1', 'solution' => 'Kirche', 'points' => 2],
            ['id' => 'blank-2', 'solution' => 'Rathaus', 'points' => 3],
        ])
        ->and($task->expectations)->toHaveCount(2)
        ->and($task->expectations[0]->text)->toBe('Du hast korrekt ausgefüllt: Kirche')
        ->and((int) $task->expectations[1]->points)->toBe(3)
        ->and($task->maximumPoints())->toBe(5);
});

it('speichert Überschriften-Tabellen mit Zelllösungen und Erwartungen', function () {
    $organization = Organization::create(['name' => 'Überschriftentabellen Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Überschriftenschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann ordnen']);

    $response = $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', [
        'title' => 'Überschriftentabelle',
        'task_type' => 'heading_table',
        'content' => [
            'prompt' => 'Fülle die Tabelle aus.',
            'show_solutions' => true,
            'lineated' => true,
            'columns' => [
                ['key' => 'c1', 'heading' => 'Kategorie', 'solution' => '', 'expectations' => []],
                ['key' => 'c2', 'heading' => '', 'solution' => 'Antwort', 'expectations' => []],
            ],
            'rows' => [
                ['key' => 'r1', 'lines' => 2, 'header' => ['key' => 'r1h', 'heading' => 'A', 'solution' => '', 'expectations' => []], 'cells' => [
                    ['key' => 'r1c1', 'heading' => '', 'solution' => '', 'expectations' => [['text' => 'Merkmal genannt.', 'points' => 1, 'repetitions' => 1]]],
                    ['key' => 'r1c2', 'heading' => '', 'solution' => '', 'expectations' => []],
                ]],
                ['key' => 'r2', 'lines' => 3, 'header' => ['key' => 'r2h', 'heading' => 'B', 'solution' => '', 'expectations' => []], 'cells' => [
                    ['key' => 'r2c1', 'heading' => '', 'solution' => '', 'expectations' => []],
                    ['key' => 'r2c2', 'heading' => '', 'solution' => '', 'expectations' => []],
                ]],
            ],
        ],
        'expectations' => [['subtask_key' => 'r1c1', 'text' => 'Merkmal genannt.', 'points' => 1, 'repetitions' => 1]],
        'competency_id' => $competency->id,
        'levels' => [],
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $task = AssessmentTask::firstOrFail();
    expect($task->content['columns'][1]['heading'])->toBeNull()
        ->and($task->content['rows'][0]['lines'])->toBe(2)
        ->and($task->expectations)->toHaveCount(2)
        ->and($task->expectations->last()->subtask_key)->toBe('r1c1')
        ->and($task->maximumPoints())->toBe(2);
});

it('speichert Zuordnungstabellen und berechnet beide Bewertungsmodi', function () {
    $organization = Organization::create(['name' => 'Zuordnungstabellen Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Zuordnungsschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann zuordnen']);

    $payload = [
        'title' => 'Kategorien zuordnen',
        'task_type' => 'matching_table',
        'content' => [
            'prompt' => 'Ordne die Texte zu.',
            'points_per_correct_answer' => 2,
            'matching_scoring_mode' => 'per_category',
            'categories' => [
                ['id' => 'c1', 'text' => 'Ja'],
                ['id' => 'c2', 'text' => 'Nein'],
            ],
            'rows' => [
                ['id' => 'r1', 'text' => 'Text eins', 'category_ids' => ['c1', 'c2']],
                ['id' => 'r2', 'text' => 'Text zwei', 'category_ids' => ['c1']],
            ],
        ],
        'competency_id' => $competency->id,
        'levels' => [],
    ];

    $response = $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', $payload);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $task = AssessmentTask::firstOrFail();
    expect($task->content['categories'])->toHaveCount(2)
        ->and($task->content['rows'][0]['category_ids'])->toBe(['c1', 'c2'])
        ->and($task->maximumPoints())->toBe(6);

    $payload['title'] = 'Kategorien als vollständige Zeilen';
    $payload['content']['matching_scoring_mode'] = 'complete_row';
    $payload['content']['categories'] = [['id' => 'c1', 'text' => 'Ja']];
    $payload['content']['rows'][0]['category_ids'] = ['c1'];
    $payload['content']['rows'][1]['category_ids'] = ['c1'];
    $response = $this->actingAs($user)->post('/ressourcen/bibliothek/pruefungsaufgaben', $payload);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $completeRowTask = AssessmentTask::latest('id')->firstOrFail();
    expect($completeRowTask->content['rows'][0]['category_ids'])->toBe(['c1'])
        ->and($completeRowTask->maximumPoints())->toBe(4);
});
