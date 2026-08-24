<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('assessment_task_reviews')->update([
            'extra_points' => DB::raw('ROUND(extra_points)'),
        ]);

        Schema::table('assessment_task_reviews', function (Blueprint $table): void {
            $table->integer('extra_points')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_task_reviews', function (Blueprint $table): void {
            $table->decimal('extra_points', 8, 2)->default(0)->change();
        });
    }
};
