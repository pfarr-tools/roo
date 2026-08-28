<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_group_grade_components', function (Blueprint $table): void {
            $table->string('stable_key', 80)->nullable()->after('type');
            $table->boolean('is_active')->default(true)->after('percentage');
            $table->index(['teaching_group_id', 'is_active']);
        });

        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('grade_component_id')->nullable()->after('report_period_id')->constrained('teaching_group_grade_components')->nullOnDelete();
            $table->string('grade_component_label')->nullable()->after('grade_component_id');
        });

        Schema::table('assessment_booklets', function (Blueprint $table): void {
            $table->string('source', 20)->default('scan')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_booklets', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['grade_component_id']);
            $table->dropColumn(['grade_component_id', 'grade_component_label']);
        });
        Schema::table('teaching_group_grade_components', function (Blueprint $table): void {
            $table->dropIndex(['teaching_group_id', 'is_active']);
            $table->dropColumn(['stable_key', 'is_active']);
        });
    }
};
