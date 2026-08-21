<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_topic_perspectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_topic_id')->constrained()->cascadeOnDelete();
            $table->string('denomination');
            $table->text('text');
            $table->timestamps();
            $table->unique(['curriculum_topic_id', 'denomination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_topic_perspectives');
    }
};
