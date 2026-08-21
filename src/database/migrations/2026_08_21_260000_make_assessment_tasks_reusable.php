<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_tasks', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
        DB::statement('UPDATE assessment_tasks SET organization_id = assessments.organization_id FROM assessments WHERE assessments.id = assessment_tasks.assessment_id');
        Schema::create('assessment_task_assessment', function (Blueprint $table): void {
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_task_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->primary(['assessment_id', 'assessment_task_id']);
            $table->unique(['assessment_id', 'position']);
        });
        Schema::create('assessment_task_levels', function (Blueprint $table): void {
            $table->foreignId('assessment_task_id')->constrained()->cascadeOnDelete();
            $table->string('level', 1);
            $table->primary(['assessment_task_id', 'level']);
        });
        Schema::create('lesson_assessment_tasks', function (Blueprint $table): void {
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_task_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->primary(['lesson_id', 'assessment_task_id']);
        });
        DB::statement('INSERT INTO assessment_task_assessment (assessment_id, assessment_task_id, position, created_at, updated_at) SELECT assessment_id, id, position, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM assessment_tasks WHERE assessment_id IS NOT NULL');
        DB::statement("INSERT INTO assessment_task_levels (assessment_task_id, level) SELECT id, level FROM assessment_tasks WHERE level IN ('G', 'M', 'E')");
        Schema::table('assessment_tasks', function (Blueprint $table): void {
            $table->dropForeign(['assessment_id']);
            $table->dropColumn('assessment_id');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_tasks', function (Blueprint $table): void {
            $table->foreignId('assessment_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
        });
        DB::statement('UPDATE assessment_tasks SET assessment_id = assessment_task_assessment.assessment_id FROM assessment_task_assessment WHERE assessment_task_assessment.assessment_task_id = assessment_tasks.id');
        Schema::dropIfExists('lesson_assessment_tasks');
        Schema::dropIfExists('assessment_task_levels');
        Schema::dropIfExists('assessment_task_assessment');
        Schema::table('assessment_tasks', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
