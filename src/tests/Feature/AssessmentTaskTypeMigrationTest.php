<?php

use App\Models\AssessmentTask;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('converts multiple choice tasks to checkbox and keeps rollback information', function () {
    $organization = Organization::create(['name' => 'Migration organisation']);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $organization->id,
        'title' => 'Alte Multiple-Choice-Aufgabe',
        'task_type' => 'multiple_choice',
        'content' => ['options' => []],
    ]));

    Schema::dropIfExists('assessment_task_type_migration_backups');
    $migration = require base_path('database/migrations/2026_08_24_291000_normalize_assessment_task_types.php');
    $migration->up();

    expect($task->fresh()->task_type)->toBe('checkbox');

    $migration->down();
    expect($task->fresh()->task_type)->toBe('multiple_choice');
});

it('preserves legacy checkbox expectations while marking their evaluation mode', function () {
    $organization = Organization::create(['name' => 'Legacy organisation']);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $organization->id,
        'title' => 'Alte Checkbox-Aufgabe',
        'task_type' => 'checkbox',
        'content' => ['automatic_expectations' => true, 'options' => [['text' => 'Ja', 'correct' => true]]],
    ]));

    Schema::dropIfExists('assessment_task_type_migration_backups');
    $migration = require base_path('database/migrations/2026_08_24_291000_normalize_assessment_task_types.php');
    $migration->up();

    expect($task->fresh()->content['evaluation_mode'])->toBe('legacy_checkbox');
});

it('normalizes old checkbox options for specialized evaluation', function () {
    $organization = Organization::create(['name' => 'Checkbox organisation']);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $organization->id,
        'title' => 'Alte Checkbox-Aufgabe',
        'task_type' => 'checkbox',
        'content' => ['options' => [['text' => 'Ja', 'correct' => true]]],
    ]));

    $migration = require base_path('database/migrations/2026_08_24_292000_normalize_checkbox_content.php');
    $migration->up();

    expect($task->fresh()->content)->toMatchArray([
        'options' => [['text' => 'Ja', 'correct' => true, 'id' => 'option-1']],
        'points_per_correct_answer' => 1,
    ]);
});
