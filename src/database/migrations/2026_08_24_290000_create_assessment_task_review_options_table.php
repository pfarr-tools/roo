<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_task_review_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_task_review_id')->constrained()->cascadeOnDelete();
            $table->string('option_id', 100);
            $table->boolean('selected')->default(false);
            $table->timestamps();
            $table->unique(['assessment_task_review_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_task_review_options');
    }
};
