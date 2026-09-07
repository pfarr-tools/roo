<?php

if (! function_exists('round_percentage')) {
    function round_percentage(int|float|null $percentage): int
    {
        return max(0, min(100, (int) round((float) ($percentage ?? 0), 0, PHP_ROUND_HALF_UP)));
    }
}

if (! function_exists('percentage_to_grade')) {
    function percentage_to_grade(int|float|null $percentage): string
    {
        $quarter = (int) round(round_percentage($percentage) / 5, 0, PHP_ROUND_HALF_UP);

        return [
            '6', '6+', '5,5', '5-', '5', '5+', '4,5', '4-', '4', '4+', '3,5',
            '3-', '3', '3+', '2,5', '2-', '2', '2+', '1,5', '1-', '1',
        ][$quarter];
    }
}
