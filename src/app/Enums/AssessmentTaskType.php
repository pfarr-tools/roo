<?php

namespace App\Enums;

enum AssessmentTaskType: string
{
    case FREE_TEXT = 'free_text';
    case DRAWING = 'drawing';
    case CHECKBOX = 'checkbox';
    case MATCHING_TABLE = 'matching_table';
    case IMAGE_MATCHING = 'image_matching';
    case IMAGE_LABELING = 'image_labeling';
    case SUBTASK_TABLE = 'subtask_table';
    case IMAGE_ANSWER_TABLE = 'image_answer_table';
    case HEADING_TABLE = 'heading_table';
    case SENTENCE_BUILDER = 'sentence_builder';
    case SORTING = 'sorting';

    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
