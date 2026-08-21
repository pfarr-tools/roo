<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('schedule_slot_id')->nullable()->after('teaching_group_id')->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['schedule_slot_id']);
            $table->dropUnique(['schedule_slot_id']);
            $table->dropColumn('schedule_slot_id');
        });
    }
};
