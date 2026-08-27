<?php

use App\Services\Assessment\ClozeTextParser;

it('extracts ordered blanks and retains points for unchanged blank ids', function () {
    $parser = new ClozeTextParser;

    $first = $parser->parse('Die [Kirche] steht neben dem [Rathaus].');
    $second = $parser->parse('Die [Kirche] steht am [Bahnhof].', $first['blanks']);

    expect($first['blanks'])->toBe([
        ['id' => 'blank-1', 'solution' => 'Kirche', 'points' => 1],
        ['id' => 'blank-2', 'solution' => 'Rathaus', 'points' => 1],
    ])
        ->and($second['blanks'])->toBe([
            ['id' => 'blank-1', 'solution' => 'Kirche', 'points' => 1],
            ['id' => 'blank-2', 'solution' => 'Bahnhof', 'points' => 1],
        ]);
});

it('retains points by position when a solution changes', function () {
    $parser = new ClozeTextParser;

    $parsed = $parser->parse('Ein [Baum] und ein [Haus].', [
        ['id' => 'blank-1', 'solution' => 'Baum', 'points' => 3],
        ['id' => 'blank-2', 'solution' => 'Haus', 'points' => 2],
    ]);

    expect($parsed['blanks'][0]['points'])->toBe(3)
        ->and($parsed['blanks'][1]['points'])->toBe(2);
});

it('rejects empty, unclosed, and nested blanks', function (string $prompt) {
    expect(fn () => (new ClozeTextParser)->parse($prompt))
        ->toThrow(InvalidArgumentException::class);
})->with(['Keine Lücke.', 'Unvollständig [Lücke.', 'Verschachtelt [eine [Lücke]].', 'Leer []']);

it('splits a multiword blank into independently renderable words', function () {
    $parser = new ClozeTextParser;

    $parsed = $parser->parse('Jesus sagt [Ich bin der Weg].');

    expect($parsed['fragments'])->toBe([
        ['type' => 'text', 'text' => 'Jesus sagt '],
        ['type' => 'blank', 'id' => 'blank-1', 'solution' => 'Ich bin der Weg', 'words' => ['Ich', 'bin', 'der', 'Weg']],
        ['type' => 'text', 'text' => '.'],
    ]);
});
