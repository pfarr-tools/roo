<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_process_competences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['school_id', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_process_competences');
    }
};
