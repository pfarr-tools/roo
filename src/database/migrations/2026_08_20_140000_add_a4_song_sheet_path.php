<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_versions', function (Blueprint $table): void {
            $table->string('generated_sheet_a4_path')->nullable()->after('generated_sheet_path');
            $table->timestamp('generated_sheet_a4_at')->nullable()->after('generated_sheet_at');
        });
    }

    public function down(): void
    {
        Schema::table('song_versions', function (Blueprint $table): void {
            $table->dropColumn(['generated_sheet_a4_path', 'generated_sheet_a4_at']);
        });
    }
};
