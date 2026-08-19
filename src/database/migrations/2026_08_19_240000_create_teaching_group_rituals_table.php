<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_group_rituals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phase_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['teaching_group_id', 'phase_template_id']);
            $table->index(['organization_id', 'teaching_group_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_group_rituals');
    }
};
