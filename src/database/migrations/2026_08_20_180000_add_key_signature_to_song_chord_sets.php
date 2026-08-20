<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_chord_sets', function (Blueprint $table): void {
            $table->string('key_signature', 32)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('song_chord_sets', function (Blueprint $table): void {
            $table->dropColumn('key_signature');
        });
    }
};
