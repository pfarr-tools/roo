<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('symbol', 32)->nullable();
            $table->string('color', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'is_active', 'position']);
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduled_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('present');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['scheduled_lesson_id', 'student_id']);
        });

        Schema::create('observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduled_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('observation_type_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['scheduled_lesson_id', 'student_id', 'observation_type_id']);
        });

        Schema::create('competence_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduled_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_unit_competency_id')->constrained()->cascadeOnDelete();
            $table->string('scale', 32)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['scheduled_lesson_id', 'student_id', 'teaching_unit_competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competence_evidences');
        Schema::dropIfExists('observations');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('observation_types');
    }
};
