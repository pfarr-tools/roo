<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->text('copyrights')->nullable()->after('description');
        });
        Schema::table('song_images', function (Blueprint $table): void {
            $table->text('copyrights')->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('song_images', function (Blueprint $table): void {
            $table->dropColumn('copyrights');
        });
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->dropColumn('copyrights');
        });
    }
};
