<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_parts', function (Blueprint $table): void {
            $table->boolean('is_numbered')->default(false)->after('is_refrain');
            $table->unsignedInteger('number')->nullable()->after('is_numbered');
        });
    }

    public function down(): void
    {
        Schema::table('song_parts', function (Blueprint $table): void {
            $table->dropColumn(['is_numbered', 'number']);
        });
    }
};
