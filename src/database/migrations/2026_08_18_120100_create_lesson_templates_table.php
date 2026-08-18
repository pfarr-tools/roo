<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('copied_from_id')->nullable()->constrained('lesson_templates')->nullOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('objective')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'is_active', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_templates');
    }
};
