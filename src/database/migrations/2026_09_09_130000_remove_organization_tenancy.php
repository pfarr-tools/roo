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

    private array $organizationIndexes = [
        'schools_organization_id_name_unique', 'schools_organization_id_slug_unique',
        'school_years_organization_id_slug_unique', 'curricula_organization_id_external_identifier_unique',
        'education_plans_organization_id_external_identifier_unique', 'songs_organization_id_title_index',
        'students_organization_id_school_id_class_name_index', 'curriculum_school_assignments_organization_id_school_id_index',
        'unit_templates_organization_id_is_active_title_index', 'lesson_templates_organization_id_is_active_title_index',
        'phase_templates_organization_id_lesson_template_id_position_index', 'social_forms_organization_id_name_unique',
        'tags_organization_id_name_unique', 'material_items_organization_id_name_unique',
        'resource_references_organization_id_original_name_index', 'teaching_units_organization_id_title_index',
        'teaching_group_rituals_organization_id_teaching_group_id_position_index',
        'observation_types_organization_id_is_active_position_index',
    ];

    public function up(): void
    {
        $required = array_diff($this->tables, ['curriculum_import_runs', 'education_plan_import_runs', 'education_plans', 'curricula']);
        foreach ($required as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id') && DB::table($table)->whereNull('user_id')->exists()) {
                throw new RuntimeException("Die Besitzmigration wurde abgebrochen: {$table} enthält Datensätze ohne Benutzerkonto.");
            }
        }

        foreach ($this->organizationIndexes as $index) {
            foreach ($this->tables as $table) {
                if (Schema::hasTable($table)) {
                    try {
                        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
                    } catch (Throwable) {
                        // Indexes differ slightly between historical installations.
                    }
                }
            }
        }

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'organization_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    try {
                        $blueprint->dropForeign(['organization_id']);
                    } catch (Throwable) {
                        // Historical installations may not have a foreign key here.
                    }
                    $blueprint->dropColumn('organization_id');
                });
            }
        }

        if (Schema::hasColumn('users', 'organization_id')) {
            Schema::table('users', function (Blueprint $blueprint): void {
                try {
                    $blueprint->dropForeign(['organization_id']);
                } catch (Throwable) {
                    // Historical installations may not have a foreign key here.
                }
                $blueprint->dropColumn('organization_id');
            });
        }

        if (Schema::hasTable('organizations')) {
            Schema::drop('organizations');
        }

        $this->addPrivateIndexes();
    }

    public function down(): void
    {
        throw new RuntimeException('Die Entfernung der Organisation-Mandantenstruktur ist nicht automatisch rückgängig machbar. Bitte eine Sicherung wiederherstellen.');
    }

    private function addPrivateIndexes(): void
    {
        $indexes = [
            ['schools', 'user_id', 'schools_user_id_name_unique', true],
            ['schools', 'user_id', 'schools_user_id_slug_unique', true],
            ['school_years', 'user_id', 'school_years_user_id_slug_unique', true],
            ['curriculum_school_assignments', 'user_id', 'curriculum_school_assignments_user_id_school_id_index', false],
            ['students', 'user_id', 'students_user_id_school_id_class_name_index', false],
        ];

        foreach ($indexes as [$table, $column, $name, $unique]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $name, $unique): void {
                $unique ? $blueprint->unique([$column], $name) : $blueprint->index([$column], $name);
            });
        }

        foreach (['curricula', 'education_plans'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'external_identifier')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index('external_identifier', $table.'_external_identifier_index'));
            }
        }
    }
};
