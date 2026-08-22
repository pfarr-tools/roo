<?php

namespace App\Services\AssessmentScan;

use App\Models\Assessment;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AssessmentScanSessionStore
{
    private const TTL_MINUTES = 60;

    public function __construct(private readonly ?Filesystem $disk = null) {}

    /** @return array{session_id:string, expires_at:string} */
    public function create(Assessment $assessment): array
    {
        $sessionId = (string) Str::ulid();
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);
        $this->filesystem()->put($this->manifestPath($sessionId), json_encode([
            'assessment_id' => (string) $assessment->getKey(),
            'organization_id' => (string) $assessment->organization_id,
            'expires_at' => $expiresAt->toIso8601String(),
            'fragments' => [],
        ], JSON_THROW_ON_ERROR));

        return ['session_id' => $sessionId, 'expires_at' => $expiresAt->toIso8601String()];
    }

    /** @return array{fragment_id:string, checksum:string} */
    public function storeFragment(string $sessionId, array $metadata, UploadedFile $fragment): array
    {
        $manifest = $this->manifest($sessionId);
        abort_unless($manifest !== null, 404);

        $fragmentId = (string) Str::ulid();
        $checksum = hash_file('sha256', $fragment->getRealPath());
        $directory = "assessment-scans/{$sessionId}";
        $path = $fragment->storeAs($directory, "{$fragmentId}.png", 'temporary');
        $manifest['fragments'][] = [
            'fragment_id' => $fragmentId,
            'path' => $path,
            'checksum' => $checksum,
            'metadata' => $metadata,
        ];
        $this->filesystem()->put($this->manifestPath($sessionId), json_encode($manifest, JSON_THROW_ON_ERROR));

        return ['fragment_id' => $fragmentId, 'checksum' => $checksum];
    }

    public function delete(string $sessionId): void
    {
        $this->filesystem()->deleteDirectory("assessment-scans/{$sessionId}");
        $this->filesystem()->delete($this->manifestPath($sessionId));
    }

    public function complete(string $sessionId, array $scan, array $fragmentIds): void
    {
        $manifest = $this->manifest($sessionId);
        abort_unless($manifest !== null, 404);
        $manifest['scan'] = $scan;
        $manifest['fragment_ids'] = $fragmentIds;
        $manifest['status'] = 'completed';
        $this->filesystem()->put($this->manifestPath($sessionId), json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    /** @return list<array<string,mixed>> */
    public function fragments(string $sessionId): array
    {
        return $this->manifest($sessionId)['fragments'] ?? [];
    }

    /** @return array<string,mixed>|null */
    public function fragment(string $sessionId, string $fragmentId): ?array
    {
        return collect($this->fragments($sessionId))->firstWhere('fragment_id', $fragmentId);
    }

    /** @return array<string,mixed>|null */
    public function manifest(string $sessionId): ?array
    {
        $path = $this->manifestPath($sessionId);
        if (! $this->filesystem()->exists($path)) {
            return null;
        }

        $manifest = json_decode($this->filesystem()->get($path), true, 512, JSON_THROW_ON_ERROR);
        if (now()->greaterThanOrEqualTo($manifest['expires_at'])) {
            $this->delete($sessionId);

            return null;
        }

        return $manifest;
    }

    private function manifestPath(string $sessionId): string
    {
        return "assessment-scans/{$sessionId}/session.json";
    }

    private function filesystem(): Filesystem
    {
        return $this->disk ?? Storage::disk('temporary');
    }
}
