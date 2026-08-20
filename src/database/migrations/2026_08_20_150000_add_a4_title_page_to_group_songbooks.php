<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_songbooks', function (Blueprint $table): void {
            $table->string('title_page_a4_path')->nullable()->after('title_page_path');
        });
    }

    public function down(): void
    {
        Schema::table('group_songbooks', function (Blueprint $table): void {
            $table->dropColumn('title_page_a4_path');
        });
    }
};
