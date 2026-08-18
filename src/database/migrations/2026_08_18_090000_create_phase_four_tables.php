<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('class_name', 50);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'school_id', 'class_name']);
        });

        Schema::create('teaching_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['school_year_id', 'name']);
        });

        Schema::create('teaching_group_grade_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->string('grade_level', 30);
            $table->unique(['teaching_group_id', 'grade_level']);
        });

        Schema::create('teaching_group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->unique(['teaching_group_id', 'student_id']);
        });

        Schema::create('teaching_group_timetable_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room')->nullable();
            $table->timestamps();
        });

        Schema::create('teaching_group_curricula', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();
            $table->unique(['teaching_group_id', 'curriculum_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_group_curricula');
        Schema::dropIfExists('teaching_group_timetable_slots');
        Schema::dropIfExists('teaching_group_memberships');
        Schema::dropIfExists('teaching_group_grade_levels');
        Schema::dropIfExists('teaching_groups');
        Schema::dropIfExists('students');
    }
};
