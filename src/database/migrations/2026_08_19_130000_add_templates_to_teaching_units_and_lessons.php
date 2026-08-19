<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_units', function (Blueprint $table): void {
            $table->foreignId('unit_template_id')->nullable()->after('source_curriculum_topic_id')->constrained('unit_templates')->nullOnDelete();
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->foreignId('lesson_template_id')->nullable()->after('teaching_unit_id')->constrained('lesson_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lesson_template_id');
        });

        Schema::table('teaching_units', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_template_id');
        });
    }
};
