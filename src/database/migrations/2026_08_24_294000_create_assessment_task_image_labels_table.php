<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_task_image_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_task_image_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->decimal('x_percent', 6, 3);
            $table->decimal('y_percent', 6, 3);
            $table->string('solution', 2000);
            $table->timestamps();
            $table->unique(['assessment_task_image_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_task_image_labels');
    }
};
