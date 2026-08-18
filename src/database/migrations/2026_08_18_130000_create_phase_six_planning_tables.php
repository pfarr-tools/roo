<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_year_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique('teaching_group_id');
        });

        Schema::create('planned_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_year_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('curriculum_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedInteger('planned_hours')->default(1);
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_interrupted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['group_year_plan_id', 'starts_on']);
        });

        Schema::create('planned_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planned_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('lesson_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planned_lesson_id')->constrained()->cascadeOnDelete();
            $table->date('planned_on');
            $table->date('actual_on')->nullable();
            $table->string('status', 20)->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['planned_lesson_id', 'planned_on']);
            $table->index(['planned_on', 'status']);
        });

        Schema::create('plan_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_year_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('action');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['group_year_plan_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_revisions');
        Schema::dropIfExists('lesson_occurrences');
        Schema::dropIfExists('planned_lessons');
        Schema::dropIfExists('planned_units');
        Schema::dropIfExists('group_year_plans');
    }
};
