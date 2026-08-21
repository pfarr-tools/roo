<?php

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\Schema::table('assessment_tasks', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->foreignId('education_plan_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->foreignId('education_plan_competency_id')->nullable()->after('education_plan_id')->constrained()->nullOnDelete();
        });

        \Illuminate\Support\Facades\DB::statement('UPDATE assessment_tasks AS tasks SET education_plan_competency_id = competencies.education_plan_competency_id FROM teaching_unit_competencies AS competencies WHERE competencies.id = tasks.teaching_unit_competency_id AND competencies.education_plan_competency_id IS NOT NULL');
        \Illuminate\Support\Facades\DB::statement('UPDATE assessment_tasks AS tasks SET education_plan_id = plans.education_plan_id FROM education_plan_competencies AS competencies JOIN education_plan_competence_areas AS areas ON areas.id = competencies.education_plan_competence_area_id JOIN education_plan_versions AS plans ON plans.id = areas.education_plan_version_id WHERE competencies.id = tasks.education_plan_competency_id');
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\Schema::table('assessment_tasks', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->dropForeign(['education_plan_competency_id']);
            $table->dropForeign(['education_plan_id']);
            $table->dropColumn(['education_plan_id', 'education_plan_competency_id']);
        });
    }
};
