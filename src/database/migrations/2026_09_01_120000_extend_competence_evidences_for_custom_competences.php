<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competence_evidences', function (Blueprint $table): void {
            $table->dropForeign(['teaching_unit_competency_id']);
            $table->foreignId('teaching_unit_competency_id')->nullable()->change();
            $table->foreignId('custom_process_competence_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('custom_scale_level')->nullable();
            $table->string('custom_scale_status', 8)->nullable();
            $table->unique(['scheduled_lesson_id', 'student_id', 'custom_process_competence_id'], 'competence_evidences_custom_unique');
        });
    }

    public function down(): void
    {
        Schema::table('competence_evidences', function (Blueprint $table): void {
            $table->dropUnique('competence_evidences_custom_unique');
            $table->dropForeign(['custom_process_competence_id']);
            $table->dropColumn(['custom_process_competence_id', 'custom_scale_level', 'custom_scale_status']);
        });
    }
};
