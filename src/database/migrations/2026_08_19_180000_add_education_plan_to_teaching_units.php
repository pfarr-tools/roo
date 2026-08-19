<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_units', function (Blueprint $table): void {
            $table->foreignId('education_plan_id')->nullable()->after('teaching_group_id')->constrained()->nullOnDelete();
            $table->index(['teaching_group_id', 'education_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::table('teaching_units', function (Blueprint $table): void {
            $table->dropForeign(['education_plan_id']);
            $table->dropIndex(['teaching_group_id', 'education_plan_id']);
            $table->dropColumn('education_plan_id');
        });
    }
};
