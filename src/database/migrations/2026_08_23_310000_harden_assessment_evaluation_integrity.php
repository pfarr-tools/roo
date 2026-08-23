<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_scan_materializations', function (Blueprint $table): void {
            $table->json('warnings')->nullable()->after('booklet_ids');
        });

        Schema::table('student_assessment_results', function (Blueprint $table): void {
            $table->foreignId('assessment_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['assessment_task_id', 'student_id']);
            $table->unique(['assessment_id', 'assessment_task_id', 'student_id'], 'student_assessment_results_assessment_task_student_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_assessment_results', function (Blueprint $table): void {
            $table->dropUnique('student_assessment_results_assessment_task_student_unique');
            $table->dropConstrainedForeignId('assessment_id');
        });

        Schema::table('assessment_scan_materializations', function (Blueprint $table): void {
            $table->dropColumn('warnings');
        });
    }
};
