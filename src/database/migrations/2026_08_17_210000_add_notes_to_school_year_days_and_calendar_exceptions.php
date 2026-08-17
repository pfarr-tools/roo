<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_exceptions', function (Blueprint $table): void {
            $table->text('notes')->nullable();
        });

        Schema::table('school_year_days', function (Blueprint $table): void {
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('school_year_days', fn (Blueprint $table) => $table->dropColumn('notes'));
        Schema::table('calendar_exceptions', fn (Blueprint $table) => $table->dropColumn('notes'));
    }
};
