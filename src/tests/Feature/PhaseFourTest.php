<?php

use App\Models\Curriculum;
use App\Models\Lesson;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolPeriod;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

function phaseFourUser(): User
{
    return User::factory()->create();
}

function phaseFourSchoolYear(User $user): array
{
    $school = School::create(['user_id' => $user->id, 'name' => 'Schule Phase 4']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'timezone' => 'Europe/Berlin']);

    return [$school, $year];
}

it('creates an empty teaching group with multiple grade levels', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);

    $this->actingAs($user)->post('/unterrichtsgruppen', ['school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '2ab', 'grade_levels' => ['2', '3']])->assertRedirect();

    $this->assertDatabaseHas('teaching_groups', ['user_id' => $user->id, 'name' => '2ab']);
    $this->assertDatabaseCount('teaching_group_memberships', 0);
    $this->assertDatabaseHas('teaching_group_grade_levels', ['grade_level' => '2']);
    $this->assertDatabaseHas('teaching_group_grade_levels', ['grade_level' => '3']);
});

it('stores the denomination when creating a teaching group', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);

    $this->actingAs($user)->post('/unterrichtsgruppen', [
        'school_id' => $school->id,
        'school_year_id' => $year->id,
        'name' => 'Konfessionsgruppe',
        'denomination' => 'catholic',
        'grade_levels' => ['3'],
    ])->assertRedirect();

    $this->assertDatabaseHas('teaching_groups', ['name' => 'Konfessionsgruppe', 'denomination' => 'catholic']);
});

it('stores whether a student receives grades and their pronoun set when creating', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Notengruppe']);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/schuelerinnen", [
        'school_id' => $school->id,
        'first_name' => 'Mia',
        'last_name' => 'Beispiel',
        'class_name' => '4a',
        'receives_grades' => true,
        'pronoun_set' => 'sie',
    ])->assertRedirect();

    $this->assertDatabaseHas('students', [
        'first_name' => 'Mia',
        'receives_grades' => true,
        'pronoun_set' => 'sie',
    ]);
});

it('defaults new students to no grades and the er pronoun set', function () {
    $user = phaseFourUser();
    [$school] = phaseFourSchoolYear($user);

    $student = Student::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'first_name' => 'Mia',
        'last_name' => 'Beispiel',
        'class_name' => '4a',
    ]);

    expect($student->fresh()->receives_grades)->toBeFalse()
        ->and($student->fresh()->pronoun_set)->toBe('er');
});

it('updates a students grading flag and pronoun set', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Updategruppe']);
    $student = Student::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'first_name' => 'Mia',
        'last_name' => 'Beispiel',
        'class_name' => '4a',
    ]);
    $group->students()->attach($student->id);

    $this->actingAs($user)->put("/schuelerinnen/{$student->id}", [
        'first_name' => 'Mia',
        'last_name' => 'Beispiel',
        'class_name' => '4a',
        'receives_grades' => true,
        'pronoun_set' => 'sie',
    ])->assertRedirect();

    $this->assertDatabaseHas('students', ['id' => $student->id, 'receives_grades' => true, 'pronoun_set' => 'sie']);
});

it('rejects unsupported student pronoun sets', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Validierungsgruppe']);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/schuelerinnen", [
        'school_id' => $school->id,
        'first_name' => 'Mia',
        'last_name' => 'Beispiel',
        'class_name' => '4a',
        'pronoun_set' => 'divers',
    ])->assertSessionHasErrors('pronoun_set');
});

it('allows editing and deleting organization students from the global student routes', function () {
    $user = phaseFourUser();
    [$school] = phaseFourSchoolYear($user);
    $student = Student::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'first_name' => 'Mia',
        'last_name' => 'Beispiel',
        'class_name' => '4a',
    ]);

    $this->actingAs($user)->put("/schuelerinnen/{$student->id}", [
        'first_name' => 'Mia',
        'last_name' => 'Neu',
        'class_name' => '5b',
    ])->assertRedirect();
    $this->assertDatabaseHas('students', ['id' => $student->id, 'last_name' => 'Neu', 'class_name' => '5b']);

    $this->actingAs($user)->delete("/schuelerinnen/{$student->id}")->assertRedirect();
    $this->assertDatabaseMissing('students', ['id' => $student->id]);
});

it('shows the organization-wide searchable and filterable student list', function () {
    $user = phaseFourUser();
    [$school] = phaseFourSchoolYear($user);
    Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Anna', 'last_name' => 'Ziegler', 'class_name' => '2a']);
    Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Ben', 'last_name' => 'Albrecht', 'class_name' => '2b']);
    $otherUser = phaseFourUser();
    [$otherSchool] = phaseFourSchoolYear($otherUser);
    Student::create(['user_id' => $otherUser->id, 'school_id' => $otherSchool->id, 'first_name' => 'Fremd', 'last_name' => 'Person', 'class_name' => '9']);

    $this->actingAs($user)->get('/schueler:innen?q=Anna&class_name=2a&sort=first_name&direction=desc')->assertSuccessful()->assertInertia(fn ($page) => $page
        ->where('filters.q', 'Anna')
        ->where('filters.class_name', '2a')
        ->where('filters.sort', 'first_name')
        ->has('students.data', 1)
        ->where('students.data.0.first_name', 'Anna')
        ->where('students.data.0.school.name', 'Schule Phase 4'));
});

it('searches all global search record types case insensitively', function () {
    $user = phaseFourUser();
    [$school] = phaseFourSchoolYear($user);
    Student::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'first_name' => 'Simon',
        'last_name' => 'Schäberle',
        'class_name' => '7a',
    ]);

    $this->actingAs($user)->get('/suche?q=sCHäBERLE')->assertSuccessful()->assertInertia(fn ($page) => $page
        ->has('results.schools', 0)
        ->has('results.students', 1)
        ->where('results.students.0.last_name', 'Schäberle'));
});

it('filters students by the numeric grade level prefix', function () {
    $user = phaseFourUser();
    [$school] = phaseFourSchoolYear($user);
    Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Anna', 'last_name' => 'Sieben', 'class_name' => '7a']);
    Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Ben', 'last_name' => 'Sieben', 'class_name' => '7b']);
    Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Clara', 'last_name' => 'Acht', 'class_name' => '8a']);

    $this->actingAs($user)->get('/schueler:innen?grade_level=7')->assertSuccessful()->assertInertia(fn ($page) => $page
        ->where('filters.grade_level', '7')
        ->has('students.data', 2)
        ->where('students.data.0.class_name', '7a')
        ->where('students.data.1.class_name', '7b'));
});

it('keeps student records out of the global search index', function () {
    expect(class_uses_recursive(Student::class))->not->toContain(Searchable::class);
});

it('exports only the organizations students with their school years', function () {
    $user = phaseFourUser();
    [$school, $year] = phaseFourSchoolYear($user);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Exportgruppe']);
    $student = Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Anna', 'last_name' => 'Export', 'class_name' => '2a']);
    $group->students()->attach($student->id);
    Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Nicht', 'last_name' => 'Export', 'class_name' => '3a']);
    $otherUser = phaseFourUser();
    [$otherSchool] = phaseFourSchoolYear($otherUser);
    Student::create(['user_id' => $otherUser->id, 'school_id' => $otherSchool->id, 'first_name' => 'Fremd', 'last_name' => 'Person', 'class_name' => '9']);

    $response = $this->actingAs($user)->get('/schueler:innen/export?class_name=2a');
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toBe('text/csv; charset=UTF-8')
        ->and($content)->toContain('Export;Anna;2a;"Schule Phase 4";2026/27')
        ->and($content)->not->toContain('Nicht;Export;3a')
        ->and($content)->not->toContain('Fremd');
});

it('redirects the legacy student list path to the canonical path', function () {
    $user = phaseFourUser();

    $response = $this->actingAs($user)->get('/schuelerinnen');

    expect($response->getStatusCode())->toBe(302)
        ->and(urldecode($response->headers->get('Location')))->toEndWith('/schueler:innen');
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

    $students = collect(['A', 'B'])->map(fn (string $lastName) => Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Mehrfach', 'last_name' => $lastName, 'class_name' => '2b']));
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/mitglieder", ['student_ids' => $students->pluck('id')->all()])->assertRedirect();
    expect($group->fresh()->students)->toHaveCount(3);
});

it('allows editing and deleting a student only within the organization', function () {
    $user = phaseFourUser();
    [$school] = phaseFourSchoolYear($user);
    $student = Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Anna', 'last_name' => 'A', 'class_name' => '2a']);

    $this->actingAs($user)->put("/schuelerinnen/{$student->id}", ['first_name' => 'Anja', 'last_name' => 'A', 'class_name' => '2b', 'denomination' => 'catholic'])->assertRedirect();
    expect($student->fresh()->first_name)->toBe('Anja')->and($student->fresh()->class_name)->toBe('2b')->and($student->fresh()->denomination)->toBe('catholic');

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
    $school->update(['short_name' => 'DGS']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Dashboardgruppe']);
    DB::table('teaching_group_periods')->insert(['teaching_group_id' => $group->id, 'school_period_id' => $period->id, 'weekday' => 2]);
    $unit = TeachingUnit::create(['user_id' => $user->id, 'teaching_group_id' => $group->id, 'title' => 'Schöpfung', 'position' => 1]);
    $lesson = Lesson::create(['teaching_unit_id' => $unit->id, 'title' => 'Die erste Stunde', 'duration' => 1, 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-15', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45', 'status' => 'absent']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id, 'status' => 'prepared']);

    $this->actingAs($user)->get('/stundenplan')->assertSuccessful()->assertInertia(fn ($page) => $page->where('week', '2026-09-14')->where('days.1.entries.0.group_name', 'Dashboardgruppe')->where('days.1.entries.0.period_number', 1)->where('days.1.entries.0.school_short_name', 'DGS')->where('days.1.entries.0.school_slug', $school->slug)->where('days.1.entries.0.schedule_slot_id', $slot->id)->where('days.1.entries.0.slot_status', 'absent')->where('days.1.entries.0.lesson.title', 'Die erste Stunde')->where('days.1.entries.0.lesson.unit_title', 'Schöpfung')->where('days.1.entries.0.lesson.status', 'prepared'));
    Carbon::setTestNow();
});

it('jumps to the next school-year week when the current week is outside a school year', function () {
    Carbon::setTestNow('2026-08-17 10:00:00');
    $user = phaseFourUser();
    phaseFourSchoolYear($user);

    $this->actingAs($user)->get('/stundenplan')->assertSuccessful()->assertInertia(fn ($page) => $page->where('week', '2026-08-31')->where('weekOptions.0.value', '2026-08-31'));
    Carbon::setTestNow();
});
