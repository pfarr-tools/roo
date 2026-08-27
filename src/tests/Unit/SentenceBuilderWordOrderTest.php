<?php

use App\Services\AssessmentEvaluation\SentenceBuilderWordOrder;

it('creates and preserves a shuffled word order', function () {
    $order = app(SentenceBuilderWordOrder::class);

    $content = $order->apply(['words' => 'Die, Katze, schläft']);
    $words = array_map('mb_strtolower', $content['shuffled_words']);
    sort($words);
    $expected = ['die', 'katze', 'schläft'];
    sort($expected);
    expect($content['shuffled_words'])->toHaveCount(3)
        ->and($words)->toBe($expected);

    expect($order->apply($content, $content)['shuffled_words'])->toBe($content['shuffled_words']);
});

it('extracts the word list directly from a sentence', function () {
    expect(app(SentenceBuilderWordOrder::class)->words('Die Katze schläft.'))->toBe(['Die', 'Katze', 'schläft']);
});
