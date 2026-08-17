<?php

use Illuminate\Support\Str;
use Meilisearch\Client;

it('reaches Meilisearch and searches an index', function () {
    $client = app(Client::class);
    $indexName = 'roo-health-'.Str::lower(Str::random(12));
    $token = Str::random(24);

    expect(config('scout.driver'))->toBe('meilisearch')
        ->and($client->health()['status'])->toBe('available');

    try {
        $index = $client->index($indexName);
        $task = $index->addDocuments([
            ['id' => $token, 'content' => "Roo search health check {$token}"],
        ]);

        expect($client->waitForTask($task['taskUid'])['status'])->toBe('succeeded');

        $hits = $index->search($token)->getHits();

        expect($hits)->toHaveCount(1)
            ->and($hits[0]['id'])->toBe($token);
    } finally {
        $client->deleteIndex($indexName);
    }
});
