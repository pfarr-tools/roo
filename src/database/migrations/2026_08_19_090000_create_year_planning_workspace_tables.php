<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_curriculum_topic_id')->nullable()->constrained('curriculum_topics')->nullOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['teaching_group_id', 'position']);
        });

        Schema::create('teaching_unit_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_plan_competency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('curriculum_topic_competency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_curriculum_topic_id')->nullable()->constrained('curriculum_topics')->nullOnDelete();
            $table->text('local_wording')->nullable();
            $table->timestamps();
            $table->index(['teaching_unit_id', 'education_plan_competency_id']);
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_unit_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('duration')->default(1);
            $table->unsignedInteger('position')->default(1);
            $table->text('learning_goals')->nullable();
            $table->text('materials')->nullable();
            $table->text('homework')->nullable();
            $table->text('assessment_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['teaching_unit_id', 'position']);
        });

        Schema::create('lesson_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_unit_competency_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['lesson_id', 'teaching_unit_competency_id']);
        });

        Schema::create('lesson_phases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phase_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(1);
            $table->text('description')->nullable();
            $table->text('materials')->nullable();
            $table->timestamps();
            $table->index(['lesson_id', 'position']);
        });

        Schema::create('schedule_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('period_number');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('status', 30)->default('free');
            $table->string('label')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['teaching_group_id', 'date', 'period_number']);
            $table->index(['teaching_group_id', 'date', 'status']);
        });

        Schema::create('scheduled_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_slot_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('planned');
            $table->timestamps();
            $table->unique(['lesson_id', 'schedule_slot_id']);
            $table->unique('schedule_slot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_lessons');
        Schema::dropIfExists('schedule_slots');
        Schema::dropIfExists('lesson_phases');
        Schema::dropIfExists('lesson_competencies');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('teaching_unit_competencies');
        Schema::dropIfExists('teaching_units');
    }
};
