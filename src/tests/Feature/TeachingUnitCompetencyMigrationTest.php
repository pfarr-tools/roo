<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('provides direct official competency references for planning and evidence', function () {
    expect(Schema::hasTable('teaching_unit_competencies'))->toBeFalse()
        ->and(Schema::hasTable('teaching_unit_education_plan_competencies'))->toBeTrue()
        ->and(Schema::hasColumn('lesson_competencies', 'education_plan_competency_id'))->toBeTrue()
        ->and(Schema::hasColumn('competence_evidences', 'education_plan_competency_id'))->toBeTrue();

    expect(DB::table('lesson_competencies')->whereNull('education_plan_competency_id')->count())->toBe(0)
        ->and(Schema::hasColumn('lesson_competencies', 'teaching_unit_competency_id'))->toBeFalse()
        ->and(Schema::hasColumn('competence_evidences', 'teaching_unit_competency_id'))->toBeFalse();
});

it('keeps assignment context on the direct teaching-unit pivot', function () {
    expect(Schema::hasColumn('teaching_unit_education_plan_competencies', 'curriculum_topic_education_plan_reference_id'))->toBeTrue()
        ->and(Schema::hasColumn('teaching_unit_education_plan_competencies', 'source_curriculum_topic_id'))->toBeTrue()
        ->and(Schema::hasColumn('teaching_unit_education_plan_competencies', 'is_secondary'))->toBeTrue();
});
