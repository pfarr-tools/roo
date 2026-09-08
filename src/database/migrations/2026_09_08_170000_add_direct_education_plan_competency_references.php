<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('teaching_unit_competencies')->whereNull('education_plan_competency_id')->exists()) {
            throw new RuntimeException('Cannot migrate TeachingUnitCompetency assignments: at least one assignment has no official education-plan competency. Resolve these assignments before migrating.');
        }

        Schema::create('teaching_unit_education_plan_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_plan_competency_id')->constrained()->restrictOnDelete();
            $table->foreignId('curriculum_topic_education_plan_reference_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_curriculum_topic_id')->nullable()->constrained('curriculum_topics')->nullOnDelete();
            $table->boolean('is_secondary')->default(false);
            $table->timestamps();
            $table->index(['teaching_unit_id', 'education_plan_competency_id']);
        });

        Schema::table('lesson_competencies', function (Blueprint $table): void {
            $table->dropForeign(['teaching_unit_competency_id']);
            $table->unsignedBigInteger('teaching_unit_competency_id')->nullable()->change();
            $table->foreignId('education_plan_competency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('curriculum_topic_education_plan_reference_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['lesson_id', 'education_plan_competency_id']);
        });

        Schema::table('competence_evidences', function (Blueprint $table): void {
            $table->foreignId('education_plan_competency_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['scheduled_lesson_id', 'student_id', 'education_plan_competency_id']);
        });

        $unitAssignments = DB::table('teaching_unit_competencies')->orderBy('id')->get();
        foreach ($unitAssignments as $assignment) {
            DB::table('teaching_unit_education_plan_competencies')->insert([
                'id' => $assignment->id,
                'teaching_unit_id' => $assignment->teaching_unit_id,
                'education_plan_competency_id' => $assignment->education_plan_competency_id,
                'curriculum_topic_education_plan_reference_id' => $assignment->curriculum_topic_education_plan_reference_id,
                'source_curriculum_topic_id' => $assignment->source_curriculum_topic_id,
                'is_secondary' => $assignment->is_secondary ?? false,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ]);
        }

        DB::table('lesson_competencies as lesson_competency')
            ->join('teaching_unit_competencies as assignment', 'assignment.id', '=', 'lesson_competency.teaching_unit_competency_id')
            ->select('lesson_competency.id', 'assignment.education_plan_competency_id')
            ->orderBy('lesson_competency.id')
            ->get()
            ->each(fn ($row) => DB::table('lesson_competencies')->where('id', $row->id)->update(['education_plan_competency_id' => $row->education_plan_competency_id]));

        DB::table('lesson_competencies as lesson_competency')
            ->join('teaching_unit_competencies as assignment', 'assignment.id', '=', 'lesson_competency.teaching_unit_competency_id')
            ->whereNotNull('assignment.curriculum_topic_education_plan_reference_id')
            ->select('lesson_competency.id', 'assignment.curriculum_topic_education_plan_reference_id')
            ->orderBy('lesson_competency.id')
            ->get()
            ->each(fn ($row) => DB::table('lesson_competencies')->where('id', $row->id)->update(['curriculum_topic_education_plan_reference_id' => $row->curriculum_topic_education_plan_reference_id]));

        DB::table('competence_evidences as evidence')
            ->join('teaching_unit_competencies as assignment', 'assignment.id', '=', 'evidence.teaching_unit_competency_id')
            ->select('evidence.id', 'assignment.education_plan_competency_id')
            ->orderBy('evidence.id')
            ->get()
            ->each(fn ($row) => DB::table('competence_evidences')->where('id', $row->id)->update(['education_plan_competency_id' => $row->education_plan_competency_id]));

        DB::table('assessment_tasks as task')
            ->join('teaching_unit_competencies as assignment', 'assignment.id', '=', 'task.teaching_unit_competency_id')
            ->whereNull('task.education_plan_competency_id')
            ->select('task.id', 'assignment.education_plan_competency_id')
            ->orderBy('task.id')
            ->get()
            ->each(fn ($row) => DB::table('assessment_tasks')->where('id', $row->id)->update(['education_plan_competency_id' => $row->education_plan_competency_id]));

        DB::table('lesson_competencies')->whereNotNull('teaching_unit_competency_id')->update(['teaching_unit_competency_id' => null]);
        DB::table('competence_evidences')->whereNotNull('teaching_unit_competency_id')->update(['teaching_unit_competency_id' => null]);
        DB::table('assessment_tasks')->whereNotNull('teaching_unit_competency_id')->update(['teaching_unit_competency_id' => null]);
    }

    public function down(): void
    {
        Schema::table('competence_evidences', function (Blueprint $table): void {
            $table->dropForeign(['education_plan_competency_id']);
            $table->dropColumn('education_plan_competency_id');
        });

        Schema::table('lesson_competencies', function (Blueprint $table): void {
            $table->dropForeign(['education_plan_competency_id']);
            $table->dropColumn('education_plan_competency_id');
        });

        Schema::dropIfExists('teaching_unit_education_plan_competencies');
    }
};
