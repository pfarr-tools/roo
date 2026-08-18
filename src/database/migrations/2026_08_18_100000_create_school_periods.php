<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('period_number');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
            $table->unique(['school_id', 'period_number']);
        });

        Schema::create('teaching_group_periods', function (Blueprint $table): void {
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->primary(['teaching_group_id', 'school_period_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_group_periods');
        Schema::dropIfExists('school_periods');
    }
};
