<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_task_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_reference_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('label')->nullable();
            $table->text('answer')->nullable();
            $table->timestamps();
            $table->unique(['assessment_task_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_task_images');
    }
};
