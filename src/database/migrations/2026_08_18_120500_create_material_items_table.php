<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('phase_template_material_items', function (Blueprint $table): void {
            $table->foreignId('phase_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->primary(['phase_template_id', 'material_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_template_material_items');
        Schema::dropIfExists('material_items');
    }
};
