<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('assessed_on')->nullable();
            $table->string('status', 32)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['teaching_group_id', 'assessed_on']);
        });
        Schema::create('assessment_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_unit_competency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('solution')->nullable();
            $table->unsignedInteger('max_points')->nullable();
            $table->string('level', 8)->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });
        Schema::create('student_assessment_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points')->nullable();
            $table->string('level', 8)->nullable();
            $table->string('numeric_grade', 8)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['assessment_task_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_assessment_results');
        Schema::dropIfExists('assessment_tasks');
        Schema::dropIfExists('assessments');
    }
};
