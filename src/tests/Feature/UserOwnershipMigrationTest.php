<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('uses direct user ownership for private records and no organization tenant', function () {
    $privateTables = [
        'schools', 'school_years', 'students', 'teaching_groups', 'teaching_units',
        'unit_templates', 'lesson_templates', 'phase_templates', 'songs',
        'observation_types', 'assessments', 'assessment_tasks', 'report_periods',
        'resource_references', 'resource_links', 'material_items', 'curriculum_school_assignments',
    ];

    foreach ($privateTables as $table) {
        expect(Schema::hasColumn($table, 'user_id'))->toBeTrue("{$table} needs user_id");
        expect(Schema::hasColumn($table, 'organization_id'))->toBeFalse("{$table} must not have organization_id");
    }

    expect(Schema::hasColumn('users', 'organization_id'))->toBeFalse()
        ->and(Schema::hasTable('organizations'))->toBeFalse()
        ->and(Schema::hasColumn('education_plans', 'organization_id'))->toBeFalse()
        ->and(Schema::hasColumn('curricula', 'organization_id'))->toBeFalse()
        ->and(Schema::hasColumn('education_plans', 'user_id'))->toBeTrue()
        ->and(Schema::hasColumn('curricula', 'user_id'))->toBeTrue();
});
