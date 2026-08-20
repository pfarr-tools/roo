<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_chord_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('song_version_id')->constrained()->cascadeOnDelete();
            $table->string('instrument');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->unique(['song_version_id', 'instrument']);
        });

        Schema::create('song_chords', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('song_chord_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_part_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->unsignedInteger('character_offset');
            $table->string('chord', 32);
            $table->timestamps();
            $table->unique(['song_chord_set_id', 'song_part_id', 'line_number', 'character_offset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_chords');
        Schema::dropIfExists('song_chord_sets');
    }
};
