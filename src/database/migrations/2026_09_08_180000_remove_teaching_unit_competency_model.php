<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertMigrationIsSafe();

        if (Schema::hasTable('lesson_competencies') && Schema::hasColumn('lesson_competencies', 'teaching_unit_competency_id')) {
            Schema::table('lesson_competencies', function (Blueprint $table): void {
                $table->dropUnique(['lesson_id', 'teaching_unit_competency_id']);
                $table->dropColumn('teaching_unit_competency_id');
                $table->unique(['lesson_id', 'education_plan_competency_id']);
            });
        }

        if (Schema::hasTable('competence_evidences') && Schema::hasColumn('competence_evidences', 'teaching_unit_competency_id')) {
            Schema::table('competence_evidences', function (Blueprint $table): void {
                $table->dropUnique(['scheduled_lesson_id', 'student_id', 'teaching_unit_competency_id']);
                $table->dropColumn('teaching_unit_competency_id');
                $table->unique(['scheduled_lesson_id', 'student_id', 'education_plan_competency_id']);
            });
        }

        if (Schema::hasTable('assessment_tasks') && Schema::hasColumn('assessment_tasks', 'teaching_unit_competency_id')) {
            Schema::table('assessment_tasks', function (Blueprint $table): void {
                $table->dropForeign(['teaching_unit_competency_id']);
                $table->dropColumn('teaching_unit_competency_id');
            });
        }

        Schema::dropIfExists('teaching_unit_competencies');
    }

    public function down(): void
    {
        throw new RuntimeException('The direct competency migration cannot be safely reversed without reconstructing assignment records from historical snapshots. Restore a database backup instead.');
    }

    private function assertMigrationIsSafe(): void
    {
        $checks = [
            ['lesson_competencies', 'teaching_unit_competency_id'],
            ['competence_evidences', 'teaching_unit_competency_id'],
            ['assessment_tasks', 'teaching_unit_competency_id'],
        ];

        foreach ($checks as [$table, $column]) {
            if (Schema::hasColumn($table, $column) && DB::table($table)->whereNotNull($column)->exists()) {
                throw new RuntimeException("Cannot remove TeachingUnitCompetency: {$table}.{$column} still contains data.");
            }
        }

        $missingAssignments = DB::table('teaching_unit_competencies as legacy')
            ->leftJoin('teaching_unit_education_plan_competencies as direct', 'direct.id', '=', 'legacy.id')
            ->whereNull('direct.id')
            ->exists();
        if ($missingAssignments) {
            throw new RuntimeException('Cannot remove TeachingUnitCompetency: at least one assignment was not migrated.');
        }

        if (Schema::hasColumn('lesson_competencies', 'education_plan_competency_id')
            && DB::table('lesson_competencies')
                ->whereNotNull('education_plan_competency_id')
                ->select('lesson_id', 'education_plan_competency_id')
                ->groupBy('lesson_id', 'education_plan_competency_id')
                ->havingRaw('COUNT(*) > 1')
                ->exists()) {
            throw new RuntimeException('Cannot remove TeachingUnitCompetency: duplicate official competency assignments exist for one lesson. Resolve them before migrating.');
        }

        if (Schema::hasColumn('competence_evidences', 'education_plan_competency_id')
            && DB::table('competence_evidences')
                ->whereNotNull('education_plan_competency_id')
                ->select('scheduled_lesson_id', 'student_id', 'education_plan_competency_id')
                ->groupBy('scheduled_lesson_id', 'student_id', 'education_plan_competency_id')
                ->havingRaw('COUNT(*) > 1')
                ->exists()) {
            throw new RuntimeException('Cannot remove TeachingUnitCompetency: duplicate official evidence exists for one student and lesson. Resolve it before migrating.');
        }
    }
};
