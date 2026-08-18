<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_template_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_template_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('phase_template_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'original_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_references');
    }
};
