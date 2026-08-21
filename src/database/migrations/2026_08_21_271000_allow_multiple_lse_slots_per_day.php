<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table): void {
            $table->foreignId('assessment_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });
        DB::statement('UPDATE schedule_slots SET assessment_id = assessments.id FROM assessments WHERE assessments.schedule_slot_id = schedule_slots.id');
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['schedule_slot_id']);
            $table->dropUnique(['schedule_slot_id']);
            $table->dropColumn('schedule_slot_id');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('schedule_slot_id')->nullable()->after('teaching_group_id')->unique()->constrained()->nullOnDelete();
        });
        DB::statement('UPDATE assessments SET schedule_slot_id = schedule_slots.id FROM schedule_slots WHERE schedule_slots.assessment_id = assessments.id AND schedule_slots.id = (SELECT MIN(other_slots.id) FROM schedule_slots AS other_slots WHERE other_slots.assessment_id = assessments.id)');
        Schema::table('schedule_slots', function (Blueprint $table): void {
            $table->dropForeign(['assessment_id']);
            $table->dropColumn('assessment_id');
        });
    }
};
