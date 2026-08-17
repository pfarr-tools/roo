<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('derived_from_id')->nullable()->constrained('curricula')->nullOnDelete();
            $table->string('external_identifier')->nullable();
            $table->string('title');
            $table->string('country', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('school_type')->nullable();
            $table->json('grades')->nullable();
            $table->string('variant')->nullable();
            $table->string('cooperation_model')->nullable();
            $table->json('denominations')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'external_identifier']);
        });

        Schema::create('curriculum_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_id')->constrained()->cascadeOnDelete();
            $table->string('external_identifier')->nullable();
            $table->string('schema_version')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_format')->nullable();
            $table->boolean('is_editable')->default(false);
            $table->boolean('is_complete')->default(true);
            $table->json('conversion_metadata')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['curriculum_id', 'external_identifier']);
        });

        Schema::create('curriculum_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_version_id')->constrained()->cascadeOnDelete();
            $table->string('external_identifier')->nullable();
            $table->unsignedInteger('number')->nullable();
            $table->string('title');
            $table->unsignedInteger('position');
            $table->unsignedInteger('year')->nullable();
            $table->unsignedInteger('hours')->nullable();
            $table->json('preparation_questions')->nullable();
            $table->json('shared_plan')->nullable();
            $table->json('raw_rows')->nullable();
            $table->timestamps();
        });

        Schema::create('curriculum_topic_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_topic_id')->constrained()->cascadeOnDelete();
            $table->string('denomination')->nullable();
            $table->string('competency_kind');
            $table->string('external_identifier')->nullable();
            $table->string('display')->nullable();
            $table->text('raw_text')->nullable();
            $table->unsignedInteger('position');
        });

        Schema::create('curriculum_topic_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_topic_id')->constrained()->cascadeOnDelete();
            $table->string('denomination');
            $table->json('perspective')->nullable();
            $table->timestamps();
            $table->unique(['curriculum_topic_id', 'denomination']);
        });

        Schema::create('curriculum_education_plan_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_code')->nullable();
            $table->string('role')->nullable();
            $table->string('denomination')->nullable();
            $table->string('subject')->nullable();
            $table->json('raw_data')->nullable();
        });

        Schema::create('curriculum_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('curriculum_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_path');
            $table->string('source_checksum', 64)->nullable();
            $table->string('status');
            $table->json('statistics')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('curriculum_school_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('school_type')->nullable();
            $table->json('grades')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['curriculum_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_school_assignments');
        Schema::dropIfExists('curriculum_import_runs');
        Schema::dropIfExists('curriculum_education_plan_bindings');
        Schema::dropIfExists('curriculum_topic_profiles');
        Schema::dropIfExists('curriculum_topic_competencies');
        Schema::dropIfExists('curriculum_topics');
        Schema::dropIfExists('curriculum_versions');
        Schema::dropIfExists('curricula');
    }
};
