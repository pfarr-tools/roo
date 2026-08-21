<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_task_expectations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_task_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->unsignedInteger('points');
            $table->unsignedInteger('repetitions')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_task_expectations');
    }
};
