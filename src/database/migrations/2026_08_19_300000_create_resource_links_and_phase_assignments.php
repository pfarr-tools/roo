<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('url');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['teaching_unit_id', 'lesson_id']);
        });

        Schema::create('lesson_phase_resources', function (Blueprint $table): void {
            $table->foreignId('lesson_phase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_reference_id')->constrained()->cascadeOnDelete();
            $table->primary(['lesson_phase_id', 'resource_reference_id']);
        });

        Schema::create('lesson_phase_resource_links', function (Blueprint $table): void {
            $table->foreignId('lesson_phase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_link_id')->constrained()->cascadeOnDelete();
            $table->primary(['lesson_phase_id', 'resource_link_id']);
        });

        Schema::create('lesson_phase_material_items', function (Blueprint $table): void {
            $table->foreignId('lesson_phase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_item_id')->constrained()->cascadeOnDelete();
            $table->primary(['lesson_phase_id', 'material_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_phase_material_items');
        Schema::dropIfExists('lesson_phase_resource_links');
        Schema::dropIfExists('lesson_phase_resources');
        Schema::dropIfExists('resource_links');
    }
};
