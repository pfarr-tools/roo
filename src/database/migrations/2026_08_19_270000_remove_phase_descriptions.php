<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_phases', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
        Schema::table('phase_templates', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_phases', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });
        Schema::table('phase_templates', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });
    }
};
