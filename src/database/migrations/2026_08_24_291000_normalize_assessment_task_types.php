<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_task_type_migration_backups', function (Blueprint $table): void {
            $table->foreignId('assessment_task_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('original_task_type', 100);
        });

        DB::table('assessment_tasks')
            ->where('task_type', 'multiple_choice')
            ->orderBy('id')
            ->each(function (object $task): void {
                DB::table('assessment_task_type_migration_backups')->insert([
                    'assessment_task_id' => $task->id,
                    'original_task_type' => 'multiple_choice',
                ]);
                DB::table('assessment_tasks')->where('id', $task->id)->update(['task_type' => 'checkbox']);
            });

        DB::table('assessment_tasks')
            ->where('task_type', 'checkbox')
            ->orderBy('id')
            ->each(function (object $task): void {
                $content = is_string($task->content) ? json_decode($task->content, true) : $task->content;

                if (! is_array($content) || ($content['automatic_expectations'] ?? null) === false) {
                    return;
                }

                $content['evaluation_mode'] = 'legacy_checkbox';
                DB::table('assessment_tasks')->where('id', $task->id)->update([
                    'content' => json_encode($content, JSON_THROW_ON_ERROR),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('assessment_task_type_migration_backups')
            ->orderBy('assessment_task_id')
            ->each(function (object $backup): void {
                DB::table('assessment_tasks')
                    ->where('id', $backup->assessment_task_id)
                    ->update(['task_type' => $backup->original_task_type]);
            });

        Schema::dropIfExists('assessment_task_type_migration_backups');
    }
};
