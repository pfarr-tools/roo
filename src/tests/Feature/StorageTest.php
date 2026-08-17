<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('writes and reads a private file in S3-compatible storage', function () {
    $path = 'health-check/'.Str::uuid().'.txt';
    $contents = 'Roo storage health check';
    $disk = Storage::disk('s3');

    try {
        expect($disk->put($path, $contents, ['visibility' => 'private']))->toBeTrue()
            ->and($disk->exists($path))->toBeTrue()
            ->and($disk->get($path))->toBe($contents)
            ->and($disk->getVisibility($path))->toBe('private');
    } finally {
        $disk->delete($path);
    }
});
