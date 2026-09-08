<?php

use App\Models\AttendanceRecord;
use App\Models\CompetenceEvidence;
use App\Models\CustomProcessCompetence;
use App\Models\Observation;
use App\Models\ObservationType;
use App\Models\Organization;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function observationFixture(): array
{
    $organization = Organization::create(['name' => 'Beobachtungsorganisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Beobachtungsschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $student = Student::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'first_name' => 'Mia', 'last_name' => 'Muster', 'class_name' => '4a']);
    $group->students()->attach($student->id);
    $lesson = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Beobachtungsstunde', 'position' => 1])->lessons()->create(['title' => 'Stunde', 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    $scheduledLesson = ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
    $type = ObservationType::create(['organization_id' => $organization->id, 'label' => 'Material fehlt', 'symbol' => 'M']);

    return compact('user', 'group', 'student', 'slot', 'scheduledLesson', 'type');
}

it('zeigt Schüler:innen und konfigurierbare Beobachtungstypen im Stundenarbeitsraum', function () {
    $fixture = observationFixture();

    $this->actingAs($fixture['user'])->get("/unterricht/{$fixture['slot']->id}")->assertInertia(fn ($page) => $page
        ->where('observationStudents.0.id', $fixture['student']->id)
        ->where('observationTypes.0.label', 'Material fehlt'));
});

it('zeigt Beobachtungen organisationsgeschützt, filterbar und sortierbar', function () {
    $fixture = observationFixture();
    $otherStudent = Student::create([
        'organization_id' => $fixture['user']->organization_id,
        'school_id' => $fixture['group']->school_id,
        'first_name' => 'Noah',
        'last_name' => 'Anders',
        'class_name' => '4a',
    ]);
    $fixture['group']->students()->attach($otherStudent->id);
    Observation::create([
        'scheduled_lesson_id' => $fixture['scheduledLesson']->id,
        'student_id' => $otherStudent->id,
        'observation_type_id' => $fixture['type']->id,
        'note' => 'Andere Notiz',
    ]);
    Observation::create([
        'scheduled_lesson_id' => $fixture['scheduledLesson']->id,
        'student_id' => $fixture['student']->id,
        'observation_type_id' => $fixture['type']->id,
        'note' => 'Mia beteiligt sich.',
    ]);

    $this->actingAs($fixture['user'])->get('/beobachtungen?q=beteiligt&group='.$fixture['group']->id.'&type='.$fixture['type']->id.'&sort=student&direction=desc')
        ->assertInertia(fn ($page) => $page
            ->where('filters.q', 'beteiligt')
            ->where('filters.group', $fixture['group']->id)
            ->where('filters.type', $fixture['type']->id)
            ->where('filters.sort', 'student')
            ->has('observations.data', 1)
            ->where('observations.data.0.student.first_name', 'Mia')
            ->where('observations.data.0.type.label', 'Material fehlt')
            ->where('observations.data.0.scheduled_lesson.lesson.title', 'Stunde'));
});

it('speichert Anwesenheit und Beobachtungen nur für Schüler:innen der Gruppe', function () {
    $fixture = observationFixture();

    $this->actingAs($fixture['user'])->put("/unterricht/{$fixture['slot']->id}/beobachtungen", [
        'students' => [[
            'student_id' => $fixture['student']->id,
            'attendance' => 'late',
            'note' => 'Kam nach dem Ritual.',
            'observation_type_ids' => [$fixture['type']->id],
        ]],
    ])->assertRedirect();

    expect(AttendanceRecord::first()->status)->toBe('late')
        ->and(AttendanceRecord::first()->note)->toBe('Kam nach dem Ritual.')
        ->and(Observation::first()->observation_type_id)->toBe($fixture['type']->id);
});

it('verhindert Beobachtungen für fremde Gruppen', function () {
    $fixture = observationFixture();
    $foreignStudent = Student::create(['organization_id' => $fixture['user']->organization_id, 'school_id' => $fixture['group']->school_id, 'first_name' => 'Fremd', 'last_name' => 'Kind', 'class_name' => '4b']);

    $this->actingAs($fixture['user'])->put("/unterricht/{$fixture['slot']->id}/beobachtungen", [
        'students' => [['student_id' => $foreignStudent->id, 'attendance' => 'present']],
    ])->assertStatus(422);
    expect(AttendanceRecord::count())->toBe(0);
});

it('speichert eine einzelne Beobachtung sofort ohne andere Schülerdaten zu überschreiben', function () {
    $fixture = observationFixture();
    $otherStudent = Student::create(['organization_id' => $fixture['user']->organization_id, 'school_id' => $fixture['group']->school_id, 'first_name' => 'Noah', 'last_name' => 'Anders', 'class_name' => '4a']);
    $fixture['group']->students()->attach($otherStudent->id);

    $this->actingAs($fixture['user'])->put("/unterricht/{$fixture['slot']->id}/beobachtungen/{$fixture['student']->id}", [
        'attendance' => 'absent',
        'note' => 'Fehlt heute.',
        'observation_type_ids' => [$fixture['type']->id],
    ])->assertRedirect();

    expect(AttendanceRecord::where('student_id', $fixture['student']->id)->value('status'))->toBe('absent')
        ->and(Observation::where('student_id', $fixture['student']->id)->exists())->toBeTrue()
        ->and(AttendanceRecord::where('student_id', $otherStudent->id)->exists())->toBeFalse();
});

it('bewertet mit alle bewerten nur leere Felder anwesender Schüler:innen', function () {
    $fixture = observationFixture();
    $otherStudent = Student::create(['organization_id' => $fixture['user']->organization_id, 'school_id' => $fixture['group']->school_id, 'first_name' => 'Noah', 'last_name' => 'Anders', 'class_name' => '4a']);
    $fixture['group']->students()->attach($otherStudent->id);
    $lessonCompetency = officialCompetency($fixture['scheduledLesson']->lesson->unit, 'Erklärt religiöse Fragen');
    $fixture['scheduledLesson']->lesson->educationPlanCompetencies()->attach($lessonCompetency->id);
    AttendanceRecord::create(['scheduled_lesson_id' => $fixture['scheduledLesson']->id, 'student_id' => $otherStudent->id, 'status' => 'absent']);

    $this->actingAs($fixture['user'])->post("/unterricht/{$fixture['slot']->id}/beobachtungen/bewerten", [
        'scale' => 4,
    ])->assertRedirect();

    expect(CompetenceEvidence::where('student_id', $fixture['student']->id)->where('education_plan_competency_id', $lessonCompetency->id)->value('scale'))->toBe('4')
        ->and(CompetenceEvidence::where('student_id', $otherStudent->id)->exists())->toBeFalse();
});

it('zeigt schulische Prozesskompetenzen und speichert eine Beobachtungsstufe', function () {
    $fixture = observationFixture();
    $fixture['group']->update(['grading_model' => 'observation_scales']);
    $competence = CustomProcessCompetence::create(['school_id' => $fixture['group']->school_id, 'text' => 'Religiöse Fragen besprechen', 'position' => 1]);

    $this->actingAs($fixture['user'])->get("/unterricht/{$fixture['slot']->id}")->assertInertia(fn ($page) => $page
        ->where('customProcessCompetences.0.id', $competence->id)
        ->where('customProcessCompetenceScaleIntervalCount', 4));

    $this->actingAs($fixture['user'])->put("/unterricht/{$fixture['slot']->id}/beobachtungen", [
        'students' => [[
            'student_id' => $fixture['student']->id,
            'attendance' => 'present',
            'evidences' => [['custom_process_competence_id' => $competence->id, 'custom_scale_level' => 3]],
        ]],
    ])->assertRedirect();

    expect(CompetenceEvidence::first()->custom_process_competence_id)->toBe($competence->id)
        ->and(CompetenceEvidence::first()->custom_scale_level)->toBe(3);
});

it('speichert ne als separaten Status für schulische Prozesskompetenzen', function () {
    $fixture = observationFixture();
    $fixture['group']->update(['grading_model' => 'observation_scales']);
    $competence = CustomProcessCompetence::create(['school_id' => $fixture['group']->school_id, 'text' => 'Wahrnehmen und beschreiben', 'position' => 1]);

    $this->actingAs($fixture['user'])->put("/unterricht/{$fixture['slot']->id}/beobachtungen", [
        'students' => [[
            'student_id' => $fixture['student']->id,
            'attendance' => 'present',
            'evidences' => [['custom_process_competence_id' => $competence->id, 'custom_scale_status' => 'ne']],
        ]],
    ])->assertRedirect();

    expect(CompetenceEvidence::first()->custom_scale_status)->toBe('ne')
        ->and(CompetenceEvidence::first()->custom_scale_level)->toBeNull();
});

it('bewertet schulische Prozesskompetenzen im Bulk mit der gewählten Skala', function () {
    $fixture = observationFixture();
    $fixture['group']->update(['grading_model' => 'observation_scales']);
    $competence = CustomProcessCompetence::create(['school_id' => $fixture['group']->school_id, 'text' => 'Wahrnehmen', 'position' => 1]);

    $this->actingAs($fixture['user'])->postJson("/unterricht/{$fixture['slot']->id}/beobachtungen/bewerten", [
        'scale' => 4,
        'custom_scale_level' => null,
        'custom_scale_status' => 'ne',
    ])->assertOk();

    expect(CompetenceEvidence::where('student_id', $fixture['student']->id)->where('custom_process_competence_id', $competence->id)->value('custom_scale_status'))->toBe('ne');
});

it('liefert beim normalen Bulk-Bewerten eine JSON-Erfolgsmeldung', function () {
    $fixture = observationFixture();

    $this->actingAs($fixture['user'])->postJson("/unterricht/{$fixture['slot']->id}/beobachtungen/bewerten", [
        'scale' => 3,
    ])->assertOk()->assertJsonPath('message', 'Noch nicht gesetzte Bewertungen wurden eingetragen.');
});

it('speichert den konfigurierbaren Beginn des zweiten Halbjahres', function () {
    $fixture = observationFixture();

    $this->actingAs($fixture['user'])->put("/schulen/{$fixture['group']->school->slug}/{$fixture['group']->schoolYear->slug}", [
        'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'second_half_start_on' => '2027-02-01', 'timezone' => 'Europe/Berlin',
    ])->assertRedirect();

    expect($fixture['group']->schoolYear->fresh()->second_half_start_on->toDateString())->toBe('2027-02-01');
});
