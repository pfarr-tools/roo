<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_topics', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_topics', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }
};
