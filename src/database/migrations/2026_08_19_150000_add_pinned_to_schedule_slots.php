<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table): void {
            $table->boolean('is_pinned')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table): void {
            $table->dropColumn('is_pinned');
        });
    }
};
