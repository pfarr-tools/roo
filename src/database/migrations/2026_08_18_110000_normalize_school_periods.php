<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('school_periods', 'weekday')) {
            DB::table('school_periods')->select('school_id', 'period_number')->groupBy('school_id', 'period_number')->havingRaw('COUNT(*) > 1')->get()->each(function (object $duplicate): void {
                $periods = DB::table('school_periods')->where('school_id', $duplicate->school_id)->where('period_number', $duplicate->period_number)->orderBy('id')->get();
                $periods->skip(1)->each(function (object $period): void {
                    DB::table('teaching_group_periods')->where('school_period_id', $period->id)->delete();
                    DB::table('school_periods')->where('id', $period->id)->delete();
                });
            });

            Schema::table('school_periods', function (Blueprint $table): void {
                $table->dropUnique('school_periods_school_id_weekday_period_number_unique');
                $table->dropColumn('weekday');
                $table->unique(['school_id', 'period_number']);
            });

            Schema::table('teaching_group_periods', function (Blueprint $table): void {
                $table->dropPrimary();
                $table->unsignedTinyInteger('weekday')->default(1);
                $table->primary(['teaching_group_id', 'school_period_id', 'weekday']);
            });
        }
    }

    public function down(): void
    {
        // The normalized model is intentionally not downgraded because weekday-specific times cannot be reconstructed.
    }
};
