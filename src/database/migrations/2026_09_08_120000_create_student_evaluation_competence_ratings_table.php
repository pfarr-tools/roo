<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_evaluation_competence_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_plan_competency_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('include_in_text')->default(true);
            $table->boolean('include_in_grade')->default(true);
            $table->timestamps();
            $table->unique(['student_evaluation_id', 'education_plan_competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_evaluation_competence_ratings');
    }
};
