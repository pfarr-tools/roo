<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('report_period_id')->nullable()->after('teaching_group_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['report_period_id']);
            $table->dropColumn('report_period_id');
        });
    }
};
