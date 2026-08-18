<?php

use App\Models\Curriculum;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolPeriod;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

function phaseFourUser(): User
{
    $organization = Organization::create(['name' => 'Phase 4 Organisation']);

    return User::factory()->create(['organization_id' => $organization->id]);
}

function phaseFourSchoolYear(User $user): array
{
    $school = School::create(['organization_id' => $user->organization_id, 'name' => 'Schule Phase 4']);
    $year = SchoolYear::create(['organization_id' => $user->organization_id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'timezone' => 'Europe/Berlin']);

    return [$school, $year];
}

it('creates an empty teaching group with multiple grade levels', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);

    $this->actingAs($user)->post('/unterrichtsgruppen', ['school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '2ab', 'grade_levels' => ['2', '3']])->assertRedirect();

    $this->assertDatabaseHas('teaching_groups', ['organization_id' => $user->organization_id, 'name' => '2ab']);
    $this->assertDatabaseCount('teaching_group_memberships', 0);
    $this->assertDatabaseHas('teaching_group_grade_levels', ['grade_level' => '2']);
    $this->assertDatabaseHas('teaching_group_grade_levels', ['grade_level' => '3']);
});

it('shows the organization-wide searchable and filterable student list', function () {
    $user = phaseFourUser();
    [$school] = phaseFourSchoolYear($user);
    Student::create(['organization_id' => $user->organization_id, 'school_id' => $school->id, 'first_name' => 'Anna', 'last_name' => 'Ziegler', 'class_name' => '2a']);
    Student::create(['organization_id' => $user->organization_id, 'school_id' => $school->id, 'first_name' => 'Ben', 'last_name' => 'Albrecht', 'class_name' => '2b']);
    $otherUser = phaseFourUser();
    [$otherSchool] = phaseFourSchoolYear($otherUser);
    Student::create(['organization_id' => $otherUser->organization_id, 'school_id' => $otherSchool->id, 'first_name' => 'Fremd', 'last_name' => 'Person', 'class_name' => '9']);

    $this->actingAs($user)->get('/schüler:innen?q=Anna&class_name=2a&sort=first_name&direction=desc')->assertSuccessful()->assertInertia(fn ($page) => $page
        ->where('filters.q', 'Anna')
        ->where('filters.class_name', '2a')
        ->where('filters.sort', 'first_name')
        ->has('students.data', 1)
        ->where('students.data.0.first_name', 'Anna')
        ->where('students.data.0.school.name', 'Schule Phase 4'));
});

it('indexes only the students minimal search fields and includes teaching groups', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $group = TeachingGroup::create([
        'organization_id' => $user->organization_id,
        'school_id' => $school->id,
        'school_year_id' => $year->id,
        'name' => 'Suchgruppe 2ab',
    ]);
    $student = Student::create([
        'organization_id' => $user->organization_id,
        'school_id' => $school->id,
        'first_name' => 'Mina',
        'last_name' => 'Beispiel',
        'class_name' => '2a',
        'notes' => 'Vertrauliche Beobachtung',
    ]);
    $group->students()->attach($student->id);
    $searchable = $student->fresh()->toSearchableArray();

    expect($searchable['teaching_groups'])->toContain('Suchgruppe 2ab')
        ->and($searchable['search_text'])->toContain('Mina')
        ->and($searchable['search_text'])->toContain('Beispiel')
        ->and($searchable['search_text'])->toContain('2a')
        ->and($searchable['search_text'])->toContain('Suchgruppe 2ab')
        ->and($searchable)->not->toHaveKey('notes');
});

it('exports only the organizations students with their school years', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $group = TeachingGroup::create(['organization_id' => $user->organization_id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Exportgruppe']);
    $student = Student::create(['organization_id' => $user->organization_id, 'school_id' => $school->id, 'first_name' => 'Anna', 'last_name' => 'Export', 'class_name' => '2a']);
    $group->students()->attach($student->id);
    $otherUser = phaseFourUser();
    [$otherSchool] = phaseFourSchoolYear($otherUser);
    Student::create(['organization_id' => $otherUser->organization_id, 'school_id' => $otherSchool->id, 'first_name' => 'Fremd', 'last_name' => 'Person', 'class_name' => '9']);

    $response = $this->actingAs($user)->get('/schüler:innen/export');
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toBe('text/csv; charset=UTF-8')
        ->and($content)->toContain('Export;Anna;2a;"Schule Phase 4";2026/27')
        ->and($content)->not->toContain('Fremd');
});

it('requires at least one grade level when creating a teaching group', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);

    $this->actingAs($user)->post('/unterrichtsgruppen', ['school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Ohne Jahrgang', 'grade_levels' => []])->assertSessionHasErrors('grade_levels');
});

it('stores a students actual class and assigns students from different classes to one group', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $this->actingAs($user)->post('/unterrichtsgruppen', ['school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '2ab', 'grade_levels' => ['2']]);
    $group = TeachingGroup::firstOrFail();

    $this->actingAs($user)->post('/schuelerinnen', ['school_id' => $school->id, 'first_name' => 'Anna', 'last_name' => 'A', 'class_name' => '2a'])->assertRedirect();
    $this->actingAs($user)->post('/schuelerinnen', ['school_id' => $school->id, 'first_name' => 'Ben', 'last_name' => 'B', 'class_name' => '2b'])->assertRedirect();
    $studentIds = Student::query()->pluck('id');
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/mitglieder", ['student_id' => $studentIds[0], 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31'])->assertRedirect();
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/mitglieder", ['student_id' => $studentIds[1]])->assertRedirect();

    expect($group->fresh()->students)->toHaveCount(2);
    expect(Student::where('class_name', '2a')->exists())->toBeTrue();
    expect(Student::where('class_name', '2b')->exists())->toBeTrue();
    expect($group->fresh()->students->firstWhere('id', $studentIds[0])->pivot->starts_on)->toBe('2026-09-01');
});

it('creates a student on a group page and assigns several existing students at once', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $this->actingAs($user)->post('/unterrichtsgruppen', ['school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '2ab', 'grade_levels' => ['2']]);
    $group = TeachingGroup::firstOrFail();
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/schuelerinnen", ['school_id' => $school->id, 'first_name' => 'Neu', 'last_name' => 'Gruppe', 'class_name' => '2a'])->assertRedirect();
    expect($group->fresh()->students)->toHaveCount(1);

    $students = collect(['A', 'B'])->map(fn (string $lastName) => Student::create(['organization_id' => $user->organization_id, 'school_id' => $school->id, 'first_name' => 'Mehrfach', 'last_name' => $lastName, 'class_name' => '2b']));
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/mitglieder", ['student_ids' => $students->pluck('id')->all()])->assertRedirect();
    expect($group->fresh()->students)->toHaveCount(3);
});

it('allows editing and deleting a student only within the organization', function () {
    $user = phaseFourUser();
    [$school] = phaseFourSchoolYear($user);
    $student = Student::create(['organization_id' => $user->organization_id, 'school_id' => $school->id, 'first_name' => 'Anna', 'last_name' => 'A', 'class_name' => '2a']);

    $this->actingAs($user)->put("/schuelerinnen/{$student->id}", ['first_name' => 'Anja', 'last_name' => 'A', 'class_name' => '2b'])->assertRedirect();
    expect($student->fresh()->first_name)->toBe('Anja')->and($student->fresh()->class_name)->toBe('2b');

    $otherUser = phaseFourUser();
    $this->actingAs($otherUser)->put("/schuelerinnen/{$student->id}", ['first_name' => 'Fremd', 'last_name' => 'Konto', 'class_name' => '9'])->assertForbidden();
    $this->actingAs($user)->delete("/schuelerinnen/{$student->id}")->assertRedirect();
    $this->assertDatabaseMissing('students', ['id' => $student->id]);
});

it('imports students from a delimited csv file into the selected school', function () {
    $user = phaseFourUser();
    [$school] = phaseFourSchoolYear($user);
    $csv = "Vorname;Nachname;Klasse;Notizen\nAnna;A;2a;Förderbedarf\nBen;B;2b;";

    $this->actingAs($user)->post('/schuelerinnen/importieren', [
        'school_id' => $school->id,
        'students' => UploadedFile::fake()->createWithContent('schueler.csv', $csv),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Student::where('school_id', $school->id)->count())->toBe(2);
    $this->assertDatabaseHas('students', ['first_name' => 'Anna', 'class_name' => '2a', 'notes' => 'Förderbedarf']);
});

it('supports several timetable slots and one primary curriculum', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $this->actingAs($user)->post('/unterrichtsgruppen', ['school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Gruppe', 'grade_levels' => ['5', '6']]);
    $group = TeachingGroup::firstOrFail();
    $curriculum = Curriculum::create(['title' => 'Curriculum', 'grades' => [5, 6], 'denominations' => []]);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/stundenplan", ['weekday' => 2, 'starts_at' => '08:00', 'ends_at' => '08:45'])->assertRedirect();
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/stundenplan", ['weekday' => 4, 'starts_at' => '10:00', 'ends_at' => '10:45'])->assertRedirect();
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/curricula", ['curriculum_assignments' => [['curriculum_id' => $curriculum->id, 'role' => 'primary']]])->assertRedirect();

    expect($group->fresh()->timetableSlots)->toHaveCount(2);
    expect($group->fresh()->curricula)->toHaveCount(1);
});

it('stores one school period definition and assigns it on multiple weekdays', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);

    $this->actingAs($user)->put("/schulen/{$school->slug}/stundenraster", ['periods' => [['period_number' => 1, 'starts_at' => '08:00']]])->assertRedirect()->assertSessionDoesntHaveErrors();
    $period = SchoolPeriod::firstOrFail();
    expect($period->ends_at->format('H:i'))->toBe('08:45');
    $this->actingAs($user)->put("/schulen/{$school->slug}/stundenraster", ['periods' => [['period_number' => 1, 'starts_at' => '08:10']]])->assertRedirect()->assertSessionDoesntHaveErrors();
    expect(SchoolPeriod::count())->toBe(1)->and(SchoolPeriod::first()->starts_at->format('H:i'))->toBe('08:10');

    $this->actingAs($user)->post('/unterrichtsgruppen', ['school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Morgenkurs', 'grade_levels' => ['5']]);
    $group = TeachingGroup::where('name', 'Morgenkurs')->firstOrFail();
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/stundenraster", ['periods' => [['school_period_id' => $period->id, 'weekday' => 2], ['school_period_id' => $period->id, 'weekday' => 4]]])->assertRedirect();

    expect($group->fresh()->schoolPeriods)->toHaveCount(2);
    $this->assertDatabaseCount('school_periods', 1);
});

it('shows the selected groups in the weekly dashboard', function () {
    Carbon::setTestNow('2026-09-14 10:00:00');
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $period = SchoolPeriod::create(['school_id' => $school->id, 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    $group = TeachingGroup::create(['organization_id' => $user->organization_id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Dashboardgruppe']);
    DB::table('teaching_group_periods')->insert(['teaching_group_id' => $group->id, 'school_period_id' => $period->id, 'weekday' => 2]);

    $this->actingAs($user)->get('/dashboard')->assertSuccessful()->assertInertia(fn ($page) => $page->where('week', '2026-09-14')->where('days.1.entries.0.group_name', 'Dashboardgruppe')->where('days.1.entries.0.period_number', 1));
    Carbon::setTestNow();
});

it('jumps to the next school-year week when the current week is outside a school year', function () {
    Carbon::setTestNow('2026-08-17 10:00:00');
    $user = phaseFourUser();
    phaseFourSchoolYear($user);

    $this->actingAs($user)->get('/dashboard')->assertSuccessful()->assertInertia(fn ($page) => $page->where('week', '2026-08-31')->where('weekOptions.0.value', '2026-08-31'));
    Carbon::setTestNow();
});
