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

    /** @return array{fragment_id:string, checksum:string} */
    public function storeGeneratedFragment(string $sessionId, array $metadata, string $contents): array
    {
        $manifest = $this->manifest($sessionId);
        abort_unless($manifest !== null, 404);
        $fragmentId = (string) Str::ulid();
        $path = "assessment-scans/{$sessionId}/{$fragmentId}.png";
        $this->filesystem()->put($path, $contents);
        $checksum = hash('sha256', $contents);
        $manifest['fragments'][] = compact('fragmentId', 'path', 'checksum', 'metadata') + ['fragment_id' => $fragmentId];
        $this->filesystem()->put($this->manifestPath($sessionId), json_encode($manifest, JSON_THROW_ON_ERROR));

        return ['fragment_id' => $fragmentId, 'checksum' => $checksum];
    }

    /** @return array{page:int, path:string} */
    public function storePage(string $sessionId, int $page, UploadedFile $image): array
    {
        $manifest = $this->manifest($sessionId);
        abort_unless($manifest !== null, 404);
        $path = $image->storeAs("assessment-scans/{$sessionId}/pages", "page-{$page}.png", 'temporary');
        $manifest['pages'] = array_values(array_filter($manifest['pages'] ?? [], fn (array $item): bool => (int) $item['page'] !== $page));
        $manifest['pages'][] = ['page' => $page, 'path' => $path, 'markers' => []];
        $this->filesystem()->put($this->manifestPath($sessionId), json_encode($manifest, JSON_THROW_ON_ERROR));

        return ['page' => $page, 'path' => $path];
    }

    public function storePageMarkers(string $sessionId, int $page, array $markers): void
    {
        $manifest = $this->manifest($sessionId);
        abort_unless($manifest !== null, 404);
        $manifest['pages'] = array_map(
            fn (array $item): array => (int) $item['page'] === $page
                ? [...$item, 'markers' => $markers]
                : $item,
            $manifest['pages'] ?? [],
        );
        $this->filesystem()->put($this->manifestPath($sessionId), json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    /** @return list<array{page:int, path:string, markers:list<array<string,mixed>>}> */
    public function pages(string $sessionId): array
    {
        return $this->manifest($sessionId)['pages'] ?? [];
    }

    public function pageContents(string $sessionId, int $page): string
    {
        $stored = collect($this->pages($sessionId))->firstWhere('page', $page);
        if ($stored === null) {
            throw new \RuntimeException('Die Scan-Seite wurde nicht gefunden.');
        }

        return $this->filesystem()->get($stored['path']);
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
        return Storage::disk('temporary');
    }
}
