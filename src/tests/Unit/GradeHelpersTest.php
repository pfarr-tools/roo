<?php

it('rounds percentages mathematically and clamps them to the valid range', function () {
    expect(round_percentage(66.5))->toBe(67)
        ->and(round_percentage(-5))->toBe(0)
        ->and(round_percentage(120))->toBe(100);
});

it('converts rounded percentages to quarter-grade labels', function () {
    expect(percentage_to_grade(0))->toBe('6')
        ->and(percentage_to_grade(5))->toBe('6+')
        ->and(percentage_to_grade(67))->toBe('3+')
        ->and(percentage_to_grade(100))->toBe('1');
});
