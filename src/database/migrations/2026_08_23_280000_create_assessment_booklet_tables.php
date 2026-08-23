<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_booklets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->enum('status', ['open', 'discarded'])->default('open');
            $table->string('name_fragment_path')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'number']);
            $table->index(['assessment_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX assessment_booklets_active_student_unique ON assessment_booklets (assessment_id, student_id) WHERE student_id IS NOT NULL AND status = 'open'");

        Schema::create('assessment_booklet_fragments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_booklet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_task_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->unsignedInteger('page');
            $table->decimal('start_y_cm', 8, 3);
            $table->decimal('end_y_cm', 8, 3);
            $table->timestamps();
            $table->unique(['assessment_booklet_id', 'assessment_task_id']);
        });

        Schema::create('assessment_task_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_booklet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_task_id')->constrained()->cascadeOnDelete();
            $table->decimal('extra_points', 8, 2)->default(0);
            $table->text('extra_note')->nullable();
            $table->timestamps();
            $table->unique(['assessment_booklet_id', 'assessment_task_id']);
        });

        Schema::create('assessment_task_review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_task_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_task_expectation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('occurrence');
            $table->decimal('awarded_points', 8, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['assessment_task_review_id', 'assessment_task_expectation_id', 'occurrence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_task_review_items');
        Schema::dropIfExists('assessment_task_reviews');
        Schema::dropIfExists('assessment_booklet_fragments');
        Schema::dropIfExists('assessment_booklets');
    }
};
