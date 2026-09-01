<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_evaluation_observation_scales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_process_competence_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('competence_text_snapshot');
            $table->unsignedInteger('position_snapshot');
            $table->unsignedTinyInteger('interval_count_snapshot');
            $table->unsignedTinyInteger('custom_scale_level')->nullable();
            $table->string('custom_scale_status', 8)->nullable();
            $table->timestamps();
            $table->unique(['student_evaluation_id', 'custom_process_competence_id'], 'student_evaluation_observation_scales_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_evaluation_observation_scales');
    }
};
