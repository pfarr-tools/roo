<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_versions', function (Blueprint $table): void {
            $table->json('generated_chord_sheet_paths')->nullable()->after('generated_sheet_a4_at');
            $table->timestamp('generated_chord_sheet_at')->nullable()->after('generated_chord_sheet_paths');
        });
    }

    public function down(): void
    {
        Schema::table('song_versions', function (Blueprint $table): void {
            $table->dropColumn(['generated_chord_sheet_paths', 'generated_chord_sheet_at']);
        });
    }
};
