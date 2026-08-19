<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_phases', function (Blueprint $table): void {
            $table->foreignId('social_form_id')->nullable()->after('duration_minutes')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lesson_phases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('social_form_id');
        });
    }
};
