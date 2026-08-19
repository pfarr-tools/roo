<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_lessons', function (Blueprint $table): void {
            $table->date('actual_on')->nullable()->after('status');
            $table->text('execution_notes')->nullable()->after('actual_on');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_lessons', function (Blueprint $table): void {
            $table->dropColumn(['actual_on', 'execution_notes']);
        });
    }
};
