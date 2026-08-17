<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_identifier');
            $table->string('country', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('subject');
            $table->string('title');
            $table->timestamps();
            $table->unique(['organization_id', 'external_identifier']);
        });

        Schema::create('education_plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_plan_id')->constrained()->cascadeOnDelete();
            $table->string('external_identifier')->nullable();
            $table->string('schema_version');
            $table->string('title');
            $table->date('version_date')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_complete')->default(true);
            $table->json('conversion_metadata')->nullable();
            $table->json('raw_payload');
            $table->text('supplementary_content_raw')->nullable();
            $table->timestamps();
            $table->unique(['education_plan_id', 'external_identifier']);
        });

        Schema::create('education_plan_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('education_plan_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_path');
            $table->string('source_checksum', 64)->nullable();
            $table->string('schema_version')->nullable();
            $table->string('status');
            $table->json('statistics')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['source_path', 'source_checksum']);
        });

        Schema::create('education_plan_school_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_plan_version_id')->constrained()->cascadeOnDelete();
            $table->string('external_identifier');
            $table->string('label');
            $table->unsignedInteger('position');
            $table->unique(['education_plan_version_id', 'external_identifier']);
        });

        Schema::create('education_plan_grade_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_plan_version_id')->constrained()->cascadeOnDelete();
            $table->string('external_identifier');
            $table->string('label')->nullable();
            $table->unsignedInteger('numeric_value')->nullable();
            $table->unsignedInteger('position');
            $table->unique(['education_plan_version_id', 'external_identifier']);
        });

        Schema::create('education_plan_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_plan_version_id')->constrained()->cascadeOnDelete();
            $table->string('external_identifier');
            $table->string('label');
            $table->unsignedInteger('position');
            $table->unique(['education_plan_version_id', 'external_identifier']);
        });

        Schema::create('education_plan_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_plan_version_id')->constrained()->cascadeOnDelete();
            $table->string('external_identifier');
            $table->string('label');
            $table->string('course_identifier')->nullable();
            $table->string('course_label')->nullable();
            $table->unsignedInteger('position');
            $table->json('raw_data')->nullable();
            $table->unique(['education_plan_version_id', 'external_identifier']);
        });

        Schema::create('education_plan_stage_grade_level', function (Blueprint $table): void {
            $table->foreignId('education_plan_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_plan_grade_level_id')->constrained()->cascadeOnDelete();
            $table->primary(['education_plan_stage_id', 'education_plan_grade_level_id']);
        });

        Schema::create('education_plan_stage_level', function (Blueprint $table): void {
            $table->foreignId('education_plan_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_plan_level_id')->constrained()->cascadeOnDelete();
            $table->primary(['education_plan_stage_id', 'education_plan_level_id']);
        });

        Schema::create('education_plan_guiding_principles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_plan_version_id')->constrained()->cascadeOnDelete();
            $table->string('external_identifier')->nullable();
            $table->string('title');
            $table->text('text');
            $table->unsignedInteger('position');
        });

        Schema::create('education_plan_competence_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_plan_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_plan_stage_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('education_plan_competence_areas')->nullOnDelete();
            $table->string('kind');
            $table->string('external_identifier');
            $table->string('title');
            $table->text('introduction')->nullable();
            $table->json('notes')->nullable();
            $table->text('source_raw')->nullable();
            $table->unsignedInteger('position');
            $table->unique(['education_plan_version_id', 'education_plan_stage_id', 'external_identifier']);
        });

        Schema::create('education_plan_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_plan_competence_area_id')->constrained()->cascadeOnDelete();
            $table->string('external_identifier');
            $table->unsignedInteger('number')->nullable();
            $table->text('text')->nullable();
            $table->unsignedInteger('position');
            $table->unique(['education_plan_competence_area_id', 'external_identifier']);
        });

        Schema::create('education_plan_competence_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_plan_competency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_plan_level_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->unsignedInteger('position');
        });

        Schema::create('education_plan_competence_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_competency_id')->constrained('education_plan_competencies')->cascadeOnDelete();
            $table->foreignId('target_competency_id')->nullable()->constrained('education_plan_competencies')->nullOnDelete();
            $table->string('relation_type')->nullable();
            $table->string('target_plan_identifier')->nullable();
            $table->string('target_external_identifier')->nullable();
            $table->text('raw_reference');
            $table->unsignedInteger('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_plan_competence_relations');
        Schema::dropIfExists('education_plan_competence_variants');
        Schema::dropIfExists('education_plan_competencies');
        Schema::dropIfExists('education_plan_competence_areas');
        Schema::dropIfExists('education_plan_guiding_principles');
        Schema::dropIfExists('education_plan_stage_level');
        Schema::dropIfExists('education_plan_stage_grade_level');
        Schema::dropIfExists('education_plan_stages');
        Schema::dropIfExists('education_plan_levels');
        Schema::dropIfExists('education_plan_grade_levels');
        Schema::dropIfExists('education_plan_school_types');
        Schema::dropIfExists('education_plan_import_runs');
        Schema::dropIfExists('education_plan_versions');
        Schema::dropIfExists('education_plans');
    }
};
