<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('copied_from_id')->nullable()->constrained('phase_templates')->nullOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('social_form')->nullable();
            $table->text('description')->nullable();
            $table->text('material')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'lesson_template_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_templates');
    }
};
