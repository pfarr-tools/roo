<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('student_evaluation_competence_ratings', 'teaching_unit_competency_id')) {
            Schema::table('student_evaluation_competence_ratings', function (Blueprint $table): void {
                $table->renameColumn('teaching_unit_competency_id', 'education_plan_competency_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_evaluation_competence_ratings', 'education_plan_competency_id')) {
            Schema::table('student_evaluation_competence_ratings', function (Blueprint $table): void {
                $table->renameColumn('education_plan_competency_id', 'teaching_unit_competency_id');
            });
        }
    }
};
