<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });
        Schema::create('text_block_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('area');
            $table->string('level', 8)->nullable();
            $table->text('text');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('student_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('draft_text')->nullable();
            $table->text('teacher_note')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['report_period_id', 'student_id']);
        });
        Schema::create('evaluation_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('text_block_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('area');
            $table->text('text');
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_blocks');
        Schema::dropIfExists('student_evaluations');
        Schema::dropIfExists('text_block_templates');
        Schema::dropIfExists('report_periods');
    }
};
