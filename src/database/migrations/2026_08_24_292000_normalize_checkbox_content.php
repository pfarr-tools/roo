<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('assessment_tasks')
            ->where('task_type', 'checkbox')
            ->orderBy('id')
            ->each(function (object $task): void {
                $content = is_string($task->content) ? json_decode($task->content, true) : $task->content;

                if (! is_array($content) || ! is_array($content['options'] ?? null)) {
                    return;
                }

                $content['options'] = array_map(
                    fn (array $option, int $index): array => [
                        ...$option,
                        'id' => is_string($option['id'] ?? null) && $option['id'] !== ''
                            ? $option['id']
                            : 'option-'.($index + 1),
                    ],
                    $content['options'],
                    array_keys($content['options']),
                );
                $content['points_per_correct_answer'] ??= 1;

                DB::table('assessment_tasks')->where('id', $task->id)->update([
                    'content' => json_encode($content, JSON_THROW_ON_ERROR),
                ]);
            });
    }

    public function down(): void
    {
        // The normalization is intentionally not reversed: generated IDs and
        // point values may already be referenced by persisted reviews.
    }
};
