<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_chords', function (Blueprint $table): void {
            $table->dropUnique('song_chords_song_chord_set_id_song_part_id_line_number_character_offset_unique');
            $table->unsignedInteger('repetition')->default(0)->after('line_number');
            $table->unique(['song_chord_set_id', 'song_part_id', 'line_number', 'repetition', 'character_offset']);
        });
    }

    public function down(): void
    {
        Schema::table('song_chords', function (Blueprint $table): void {
            $table->dropUnique('song_chords_song_chord_set_id_song_part_id_line_number_repetition_character_offset_unique');
            $table->dropColumn('repetition');
            $table->unique(['song_chord_set_id', 'song_part_id', 'line_number', 'character_offset']);
        });
    }
};
