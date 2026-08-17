<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('education_plan_competencies', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('text');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('education_plan_competencies', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};
