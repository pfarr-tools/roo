<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_student_result_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['not_evaluated', 'missing']);
            $table->timestamps();
            $table->unique(['assessment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_student_result_statuses');
    }
};
