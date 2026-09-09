<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'data_sources', 'schools', 'school_years', 'students', 'teaching_groups',
        'unit_templates', 'lesson_templates', 'phase_templates', 'social_forms',
        'tags', 'material_items', 'resource_references', 'teaching_units',
        'group_year_plans', 'teaching_group_rituals', 'resource_links', 'songs',
        'observation_types', 'assessments', 'assessment_tasks', 'report_periods',
        'text_block_templates', 'curriculum_school_assignments',
        'curriculum_import_runs', 'education_plan_import_runs',
        'education_plans', 'curricula',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        $this->assertOrganizationsHaveAtMostOneUser();

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('organization_id')
                ->orderBy('id')
                ->eachById(function (object $row) use ($table): void {
                    $userId = DB::table('users')->where('organization_id', $row->organization_id)->value('id');

                    if ($userId !== null) {
                        DB::table($table)->where('id', $row->id)->update(['user_id' => $userId]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['user_id']);
                $blueprint->dropColumn('user_id');
            });
        }
    }

    private function assertOrganizationsHaveAtMostOneUser(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'organization_id')) {
            return;
        }

        $conflicts = DB::table('users')
            ->select('organization_id')
            ->whereNotNull('organization_id')
            ->groupBy('organization_id')
            ->havingRaw('count(*) > 1')
            ->pluck('organization_id');

        if ($conflicts->isNotEmpty()) {
            throw new RuntimeException('Die Besitzmigration wurde abgebrochen: Eine Organisation enthält mehrere Benutzerkonten (IDs: '.$conflicts->implode(', ').').');
        }
    }
};
