<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE student_evaluation_competence_ratings DROP CONSTRAINT IF EXISTS student_evaluation_competence_ratings_teaching_unit_competency_id_foreign');
        DB::statement('ALTER TABLE student_evaluation_competence_ratings DROP CONSTRAINT IF EXISTS student_evaluation_competence_ratings_education_plan_competency_id_foreign');
        DB::statement('ALTER TABLE student_evaluation_competence_ratings ADD CONSTRAINT student_evaluation_competence_ratings_education_plan_competency_id_foreign FOREIGN KEY (education_plan_competency_id) REFERENCES education_plan_competencies (id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE student_evaluation_competence_ratings DROP CONSTRAINT IF EXISTS student_evaluation_competence_ratings_education_plan_competency_id_foreign');
        DB::statement('ALTER TABLE student_evaluation_competence_ratings ADD CONSTRAINT student_evaluation_competence_ratings_teaching_unit_competency_id_foreign FOREIGN KEY (education_plan_competency_id) REFERENCES teaching_unit_competencies (id) ON DELETE RESTRICT');
    }
};
