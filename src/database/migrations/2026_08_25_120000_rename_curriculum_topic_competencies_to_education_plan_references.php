<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The old importer persisted unresolved curriculum competencies. The
        // corrected JSON package is the authoritative source for this data;
        // discard the stale curriculum aggregate before enforcing direct
        // EducationPlan references. Curriculum-independent planning data uses
        // nullOnDelete foreign keys and remains intact.
        DB::table('curricula')->delete();

        Schema::rename('curriculum_topic_competencies', 'curriculum_topic_education_plan_references');

        Schema::table('curriculum_topic_education_plan_references', function (Blueprint $table): void {
            $table->dropColumn(['external_identifier', 'display', 'text', 'raw_text']);
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_topic_education_plan_references', function (Blueprint $table): void {
            $table->string('external_identifier')->nullable();
            $table->string('display')->nullable();
            $table->text('text')->nullable();
            $table->text('raw_text')->nullable();
        });

        Schema::rename('curriculum_topic_education_plan_references', 'curriculum_topic_competencies');
    }
};
