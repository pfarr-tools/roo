<?php

use App\Jobs\QueueHealthCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

it('executes a queued job through the configured worker', function () {
    $token = (string) Str::uuid();
    $cacheKey = "queue-health-check:{$token}";

    Cache::forget($cacheKey);
    QueueHealthCheck::dispatch($token);

    $this->artisan('queue:work', [
        'connection' => config('queue.default'),
        '--once' => true,
        '--tries' => 1,
    ])->assertExitCode(0);

    expect(Cache::get($cacheKey))->toBeTrue();
});
