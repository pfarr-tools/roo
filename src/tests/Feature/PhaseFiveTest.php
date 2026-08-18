<?php

use App\Models\LessonTemplate;
use App\Models\Organization;
use App\Models\PhaseTemplate;
use App\Models\SocialForm;
use App\Models\Tag;
use App\Models\UnitTemplate;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

function phaseFiveUser(): User
{
    $organization = Organization::create(['name' => 'Phase 5 Organisation']);

    return User::factory()->create(['organization_id' => $organization->id]);
}

it('creates and lists unit templates within the users organization', function () {
    $user = phaseFiveUser();
    $otherOrganization = Organization::create(['name' => 'Andere Organisation']);
    UnitTemplate::create(['organization_id' => $otherOrganization->id, 'title' => 'Fremde Vorlage']);

    $this->actingAs($user)->post('/unterrichtseinheiten-vorlagen', [
        'title' => 'Schöpfung bewahren',
        'description' => 'Eine wiederverwendbare Unterrichtseinheit.',
        'expected_hours' => 4,
        'notes' => 'Material frühzeitig prüfen.',
    ])->assertRedirect('/unterrichtseinheiten-vorlagen');

    $this->assertDatabaseHas('unit_templates', [
        'organization_id' => $user->organization_id,
        'title' => 'Schöpfung bewahren',
        'expected_hours' => 4,
        'version' => 1,
    ]);

    $this->actingAs($user)->get('/unterrichtseinheiten-vorlagen')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('templates', 1)
            ->where('templates.0.title', 'Schöpfung bewahren'));
});

it('filters unit templates by title without exposing another organization', function () {
    $user = phaseFiveUser();
    UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Mose']);
    UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Noah']);
    $otherUser = phaseFiveUser();
    UnitTemplate::create(['organization_id' => $otherUser->organization_id, 'title' => 'Mose fremd']);

    $this->actingAs($user)->get('/unterrichtseinheiten-vorlagen?q=Mose')
        ->assertInertia(fn ($page) => $page
            ->where('filters.q', 'Mose')
            ->has('templates', 1)
            ->where('templates.0.title', 'Mose'));
});

it('copies each template type as a new version one record with provenance', function () {
    $user = phaseFiveUser();
    $unitTemplate = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'UE']);
    $lessonTemplate = LessonTemplate::create(['organization_id' => $user->organization_id, 'unit_template_id' => $unitTemplate->id, 'title' => 'Stunde']);
    $phaseTemplate = PhaseTemplate::create(['organization_id' => $user->organization_id, 'lesson_template_id' => $lessonTemplate->id, 'title' => 'Phase']);

    $this->actingAs($user)->post("/unterrichtseinheiten-vorlagen/{$unitTemplate->id}/kopieren")->assertRedirect();
    $this->actingAs($user)->post("/stunden-vorlagen/{$lessonTemplate->id}/kopieren")->assertRedirect();
    $this->actingAs($user)->post("/phasen-vorlagen/{$phaseTemplate->id}/kopieren")->assertRedirect();

    expect(UnitTemplate::where('copied_from_id', $unitTemplate->id)->first()->version)->toBe(1)
        ->and(LessonTemplate::where('copied_from_id', $lessonTemplate->id)->first()->version)->toBe(1)
        ->and(PhaseTemplate::where('copied_from_id', $phaseTemplate->id)->first()->version)->toBe(1);
});

it('stores, updates and copies organization tags on unit templates', function () {
    $user = phaseFiveUser();
    $template = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Tagged']);

    $this->actingAs($user)->put("/unterrichtseinheiten-vorlagen/{$template->id}", ['title' => 'Tagged', 'tags' => ['Ritual', 'Gespräch']])->assertRedirect();
    expect($template->fresh()->tags->pluck('name')->all())->toEqualCanonicalizing(['Ritual', 'Gespräch']);

    $this->actingAs($user)->post("/unterrichtseinheiten-vorlagen/{$template->id}/kopieren")->assertRedirect();
    expect(UnitTemplate::where('copied_from_id', $template->id)->first()->tags->pluck('name')->all())->toEqualCanonicalizing(['Ritual', 'Gespräch']);
    expect(Tag::where('organization_id', $user->organization_id)->count())->toBe(2);
});

it('validates the required title for a unit template', function () {
    $user = phaseFiveUser();

    $this->actingAs($user)->post('/unterrichtseinheiten-vorlagen', ['title' => ''])
        ->assertSessionHasErrors('title');
});

it('does not list inactive unit templates', function () {
    $user = phaseFiveUser();
    UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Archivierte Vorlage', 'is_active' => false]);

    $this->actingAs($user)->get('/unterrichtseinheiten-vorlagen')
        ->assertInertia(fn ($page) => $page->has('templates', 0));
});

it('edits a unit template and increments its version', function () {
    $user = phaseFiveUser();
    $template = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Alte Vorlage']);

    $this->actingAs($user)->put("/unterrichtseinheiten-vorlagen/{$template->id}", [
        'title' => 'Überarbeitete Vorlage',
        'expected_hours' => 3,
    ])->assertRedirect('/unterrichtseinheiten-vorlagen');

    expect($template->fresh()->title)->toBe('Überarbeitete Vorlage')
        ->and($template->fresh()->version)->toBe(2);
});

it('deletes a unit template only within the users organization', function () {
    $user = phaseFiveUser();
    $template = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Löschen']);
    $otherUser = phaseFiveUser();
    $foreignTemplate = UnitTemplate::create(['organization_id' => $otherUser->organization_id, 'title' => 'Fremd']);

    $this->actingAs($otherUser)->delete("/unterrichtseinheiten-vorlagen/{$template->id}")->assertForbidden();
    $this->actingAs($user)->delete("/unterrichtseinheiten-vorlagen/{$template->id}")->assertRedirect('/unterrichtseinheiten-vorlagen');

    $this->assertDatabaseMissing('unit_templates', ['id' => $template->id]);
    $this->assertDatabaseHas('unit_templates', ['id' => $foreignTemplate->id]);
});

it('creates, lists and versions lesson templates for an organization unit template', function () {
    $user = phaseFiveUser();
    $unitTemplate = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Schöpfung']);

    $this->actingAs($user)->post('/stunden-vorlagen', [
        'unit_template_id' => $unitTemplate->id,
        'title' => 'Einstieg',
        'duration_minutes' => 45,
        'objective' => 'Die Kinder formulieren erste Fragen.',
    ])->assertRedirect('/stunden-vorlagen');

    $lessonTemplate = LessonTemplate::firstOrFail();
    expect($lessonTemplate->unit_template_id)->toBe($unitTemplate->id);

    $this->actingAs($user)->put("/stunden-vorlagen/{$lessonTemplate->id}", [
        'unit_template_id' => $unitTemplate->id,
        'title' => 'Einstieg und Fragen',
        'duration_minutes' => 60,
    ])->assertRedirect('/stunden-vorlagen');

    expect($lessonTemplate->fresh()->title)->toBe('Einstieg und Fragen')
        ->and($lessonTemplate->fresh()->version)->toBe(2);

    $this->actingAs($user)->get('/stunden-vorlagen')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('templates', 1)
            ->has('unitTemplates', 1)
            ->where('templates.0.unit_template.title', 'Schöpfung'));
});

it('filters lesson templates by objective', function () {
    $user = phaseFiveUser();
    $unitTemplate = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Thema']);
    LessonTemplate::create(['organization_id' => $user->organization_id, 'unit_template_id' => $unitTemplate->id, 'title' => 'Start', 'objective' => 'Fragen entwickeln']);
    LessonTemplate::create(['organization_id' => $user->organization_id, 'unit_template_id' => $unitTemplate->id, 'title' => 'Abschluss', 'objective' => 'Ergebnisse sichern']);

    $this->actingAs($user)->get('/stunden-vorlagen?q=Fragen')
        ->assertInertia(fn ($page) => $page->has('templates', 1)->where('templates.0.title', 'Start'));
});

it('rejects lesson templates using another organizations unit template', function () {
    $user = phaseFiveUser();
    $otherUser = phaseFiveUser();
    $foreignUnitTemplate = UnitTemplate::create(['organization_id' => $otherUser->organization_id, 'title' => 'Fremd']);

    $this->actingAs($user)->post('/stunden-vorlagen', [
        'unit_template_id' => $foreignUnitTemplate->id,
        'title' => 'Nicht erlaubt',
    ])->assertForbidden();
});

it('deletes a lesson template only within the users organization', function () {
    $user = phaseFiveUser();
    $otherUser = phaseFiveUser();
    $unitTemplate = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Eigene']);
    $lessonTemplate = LessonTemplate::create(['organization_id' => $user->organization_id, 'unit_template_id' => $unitTemplate->id, 'title' => 'Löschen']);

    $this->actingAs($otherUser)->delete("/stunden-vorlagen/{$lessonTemplate->id}")->assertForbidden();
    $this->actingAs($user)->delete("/stunden-vorlagen/{$lessonTemplate->id}")->assertRedirect('/stunden-vorlagen');

    $this->assertDatabaseMissing('lesson_templates', ['id' => $lessonTemplate->id]);
});

it('creates, lists and versions phase templates for an organization lesson template', function () {
    $user = phaseFiveUser();
    $unitTemplate = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Schöpfung']);
    $lessonTemplate = LessonTemplate::create(['organization_id' => $user->organization_id, 'unit_template_id' => $unitTemplate->id, 'title' => 'Einstieg']);

    $this->actingAs($user)->post('/phasen-vorlagen', [
        'lesson_template_id' => $lessonTemplate->id,
        'title' => 'Ritualisierter Einstieg',
        'duration_minutes' => 10,
        'social_form' => 'Plenum',
        'material' => 'Bildkarte',
    ])->assertRedirect('/phasen-vorlagen');

    $phaseTemplate = PhaseTemplate::firstOrFail();
    expect($phaseTemplate->position)->toBe(1)
        ->and($phaseTemplate->socialForm->name)->toBe('Plenum');

    $this->actingAs($user)->put("/phasen-vorlagen/{$phaseTemplate->id}", [
        'lesson_template_id' => $lessonTemplate->id,
        'title' => 'Überarbeiteter Einstieg',
        'position' => 2,
    ])->assertRedirect('/phasen-vorlagen');

    expect($phaseTemplate->fresh()->title)->toBe('Überarbeiteter Einstieg')
        ->and($phaseTemplate->fresh()->version)->toBe(2);
    expect(SocialForm::where('organization_id', $user->organization_id)->count())->toBe(1);

    $this->actingAs($user)->get('/phasen-vorlagen')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('templates', 1)
            ->has('lessonTemplates', 1)
            ->where('templates.0.lesson_template.title', 'Einstieg'));
});

it('filters phase templates by material', function () {
    $user = phaseFiveUser();
    $unitTemplate = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Thema']);
    $lessonTemplate = LessonTemplate::create(['organization_id' => $user->organization_id, 'unit_template_id' => $unitTemplate->id, 'title' => 'Stunde']);
    PhaseTemplate::create(['organization_id' => $user->organization_id, 'lesson_template_id' => $lessonTemplate->id, 'title' => 'Bild', 'material' => 'Bildkarte']);
    PhaseTemplate::create(['organization_id' => $user->organization_id, 'lesson_template_id' => $lessonTemplate->id, 'title' => 'Gespräch', 'material' => 'Heft']);

    $this->actingAs($user)->get('/phasen-vorlagen?q=Bildkarte')
        ->assertInertia(fn ($page) => $page->has('templates', 1)->where('templates.0.title', 'Bild'));
});

it('rejects phase templates using another organizations lesson template', function () {
    $user = phaseFiveUser();
    $otherUser = phaseFiveUser();
    $foreignUnitTemplate = UnitTemplate::create(['organization_id' => $otherUser->organization_id, 'title' => 'Fremd UE']);
    $foreignLessonTemplate = LessonTemplate::create(['organization_id' => $otherUser->organization_id, 'unit_template_id' => $foreignUnitTemplate->id, 'title' => 'Fremde Stunde']);

    $this->actingAs($user)->post('/phasen-vorlagen', [
        'lesson_template_id' => $foreignLessonTemplate->id,
        'title' => 'Nicht erlaubt',
    ])->assertForbidden();
});
