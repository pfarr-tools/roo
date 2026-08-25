<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_unit_competencies', function (Blueprint $table): void {
            $table->foreignId('curriculum_topic_education_plan_reference_id')
                ->nullable()
                ->after('education_plan_competency_id')
                ->constrained('curriculum_topic_education_plan_references')
                ->nullOnDelete();
        });

        DB::statement('UPDATE teaching_unit_competencies SET curriculum_topic_education_plan_reference_id = curriculum_topic_competency_id');

        Schema::table('teaching_unit_competencies', function (Blueprint $table): void {
            $table->dropForeign(['curriculum_topic_competency_id']);
            $table->dropColumn('curriculum_topic_competency_id');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_unit_competencies', function (Blueprint $table): void {
            $table->foreignId('curriculum_topic_competency_id')
                ->nullable()
                ->after('education_plan_competency_id')
                ->constrained('curriculum_topic_education_plan_references')
                ->nullOnDelete();
        });

        DB::statement('UPDATE teaching_unit_competencies SET curriculum_topic_competency_id = curriculum_topic_education_plan_reference_id');

        Schema::table('teaching_unit_competencies', function (Blueprint $table): void {
            $table->dropForeign(['curriculum_topic_education_plan_reference_id']);
            $table->dropColumn('curriculum_topic_education_plan_reference_id');
        });
    }
};
