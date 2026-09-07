<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE assessment_tasks AS tasks SET education_plan_id = plans.education_plan_id, education_plan_competency_id = competencies.education_plan_competency_id FROM teaching_unit_competencies AS competencies JOIN education_plan_competencies AS plan_competencies ON plan_competencies.id = competencies.education_plan_competency_id JOIN education_plan_competence_areas AS areas ON areas.id = plan_competencies.education_plan_competence_area_id JOIN education_plan_versions AS plans ON plans.id = areas.education_plan_version_id WHERE tasks.education_plan_competency_id IS NULL AND tasks.teaching_unit_competency_id = competencies.id');
    }

    public function down(): void
    {
        // The migrated direct references are intentionally retained.
    }
};
