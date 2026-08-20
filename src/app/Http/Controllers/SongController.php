<?php

namespace App\Http\Controllers;

use App\Models\SongVersion;
use App\Models\SongImage;
use App\Models\Song;
use App\Models\ResourceReference;
use App\Services\SongbookPdfExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SongController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $songs = Song::where(fn ($builder) => $builder->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))
            ->with(['versions.song:id,organization_id,title,composer,author,copyright_notice,age_group,topics,notes', 'versions.sheet', 'versions.parts', 'versions.images'])->when($query !== '', fn ($builder) => $builder->where('title', 'like', "%{$query}%"))
            ->orderBy('title')->get();

        $songs->each(fn (Song $song) => $song->setAttribute('can_delete', $song->organization_id === $request->user()->organization_id));

        return Inertia::render('Songs/Index', [
            'songs' => $songs,
            'filters' => ['q' => $query],
            'libraryImages' => ResourceReference::where('organization_id', $request->user()->organization_id)->where('mime_type', 'like', 'image/%')->orderBy('original_name')->get(['id', 'original_name', 'mime_type']),
            'songStyles' => collect(config('songs'))->only([
                'title_font_family', 'title_font_size', 'title_font_weight',
                'text_font_family', 'text_font_size', 'text_font_weight',
                'refrain_font_family', 'refrain_font_size', 'refrain_font_weight',
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'composer' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'], 'copyright_notice' => ['nullable', 'string', 'max:255'],
            'age_group' => ['nullable', 'string', 'max:255'], 'topics' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'], 'version_name' => ['required', 'string', 'max:255'],
            'lyrics' => ['nullable', 'string'], 'notation' => ['nullable', 'string'], 'chords' => ['nullable', 'string'],
            'text_export_allowed' => ['sometimes', 'boolean'],
            'metadata_export_allowed' => ['sometimes', 'boolean'],
            'sheet' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $song = Song::create(collect($data)->only(['title', 'composer', 'author', 'copyright_notice', 'age_group', 'topics', 'notes'])->merge(['organization_id' => $request->user()->organization_id])->all());
        $version = $song->versions()->create(collect($data)->only(['version_name', 'lyrics', 'notation', 'chords', 'text_export_allowed', 'metadata_export_allowed'])->merge(['name' => $data['version_name']])->all());
        if ($request->hasFile('sheet')) $this->storeSheet($version, $request->file('sheet'));

        return back()->with('success', 'Lied wurde gespeichert.');
    }

    public function destroy(Request $request, Song $song): RedirectResponse
    {
        abort_unless($song->organization_id === $request->user()->organization_id, 404);

        $song->load(['versions.sheet', 'versions.images']);
        foreach ($song->versions as $version) {
            if ($version->sheet) Storage::disk('local')->delete($version->sheet->storage_path);
            if ($version->generated_sheet_path) Storage::disk('local')->delete($version->generated_sheet_path);
            foreach ($version->images as $image) Storage::disk('local')->delete($image->storage_path);
        }
        $song->delete();

        return back()->with('success', 'Lied wurde gelöscht.');
    }

    public function uploadSheet(Request $request, SongVersion $songVersion): RedirectResponse
    {
        $this->authorizeVersion($request, $songVersion);
        $data = $request->validate(['sheet' => ['required', 'file', 'mimes:pdf', 'max:51200']]);
        if ($songVersion->sheet) Storage::disk('local')->delete($songVersion->sheet->storage_path);
        $this->storeSheet($songVersion, $data['sheet']);
        return back()->with('success', 'Liedblatt wurde hochgeladen.');
    }

    public function updateVersion(Request $request, SongVersion $songVersion): RedirectResponse
    {
        $this->authorizeEditableVersion($request, $songVersion);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'language' => ['required', 'string', 'max:10'], 'song' => ['sometimes', 'array'], 'song.title' => ['required_with:song', 'string', 'max:255'], 'song.composer' => ['nullable', 'string', 'max:255'], 'song.author' => ['nullable', 'string', 'max:255'], 'song.copyright_notice' => ['nullable', 'string', 'max:255'], 'song.age_group' => ['nullable', 'string', 'max:255'], 'song.topics' => ['nullable', 'string', 'max:255'], 'song.notes' => ['nullable', 'string'], 'parts' => ['sometimes', 'array'], 'parts.*.id' => ['nullable', 'integer'], 'parts.*.content' => ['required', 'string'], 'parts.*.is_refrain' => ['sometimes', 'boolean'], 'layout_data' => ['nullable', 'array']]);
        DB::transaction(function () use ($data, $songVersion): void {
            $lockedVersion = SongVersion::query()->lockForUpdate()->findOrFail($songVersion->id);
            $lockedVersion->update(collect($data)->only(['name', 'language', 'layout_data'])->all());
            if (isset($data['song'])) $lockedVersion->song->update(collect($data['song'])->only(['title', 'composer', 'author', 'copyright_notice', 'age_group', 'topics', 'notes'])->all());
            if (array_key_exists('parts', $data)) {
                $lockedVersion->parts()->delete();
                $lockedVersion->parts()->createMany(collect($data['parts'])->values()->map(fn (array $part, int $position): array => ['content' => $part['content'], 'position' => $position + 1, 'is_refrain' => $part['is_refrain'] ?? false])->all());
            }
        });
        return back()->with('success', 'Liedfassung wurde gespeichert.');
    }

    public function uploadImages(Request $request, SongVersion $songVersion): RedirectResponse
    {
        $this->authorizeEditableVersion($request, $songVersion);
        $data = $request->validate(['images' => ['required', 'array', 'max:20'], 'images.*' => ['image', 'max:10240']]);
        foreach ($data['images'] as $image) $songVersion->images()->create(['original_name' => $image->getClientOriginalName(), 'storage_path' => $image->store('songs/images', 'local'), 'mime_type' => $image->getMimeType(), 'size' => $image->getSize()]);
        return back()->with('success', 'Bilder wurden hinzugefügt.');
    }

    public function importLibraryImage(Request $request, SongVersion $songVersion): RedirectResponse
    {
        $this->authorizeEditableVersion($request, $songVersion);
        $data = $request->validate(['resource_id' => ['required', 'integer']]);
        $resource = ResourceReference::where('organization_id', $request->user()->organization_id)->where('mime_type', 'like', 'image/%')->findOrFail($data['resource_id']);
        abort_unless(Storage::disk('local')->exists($resource->storage_path), 404);
        $extension = pathinfo($resource->original_name, PATHINFO_EXTENSION);
        $path = 'songs/images/'.Str::uuid().($extension ? '.'.$extension : '');
        Storage::disk('local')->copy($resource->storage_path, $path);
        $songVersion->images()->create(['original_name' => $resource->original_name, 'storage_path' => $path, 'mime_type' => $resource->mime_type, 'size' => Storage::disk('local')->size($path)]);

        return back()->with('success', 'Bild wurde aus der Bibliothek übernommen.');
    }

    public function destroyImage(Request $request, SongVersion $songVersion, SongImage $songImage): RedirectResponse
    {
        $this->authorizeEditableVersion($request, $songVersion);
        abort_unless($songImage->song_version_id === $songVersion->id, 404);
        Storage::disk('local')->delete($songImage->storage_path);
        $songImage->delete();
        $layout = $songVersion->layout_data ?? [];
        $layout['images'] = collect($layout['images'] ?? [])->reject(fn (array $image): bool => (int) ($image['id'] ?? 0) === $songImage->id)->values()->all();
        $songVersion->update(['layout_data' => $layout]);

        return back()->with('success', 'Bild wurde gelöscht.');
    }

    public function generateSheet(Request $request, SongVersion $songVersion, SongbookPdfExporter $exporter): RedirectResponse
    {
        $this->authorizeEditableVersion($request, $songVersion);
        $path = $exporter->generateSongVersion($songVersion);
        if ($songVersion->generated_sheet_path) Storage::disk('local')->delete($songVersion->generated_sheet_path);
        $songVersion->update(['generated_sheet_path' => $path, 'generated_sheet_at' => now()]);
        return back()->with('success', 'A5-Liedblatt wurde erzeugt.');
    }

    public function generatedSheet(Request $request, SongVersion $songVersion, SongbookPdfExporter $exporter)
    {
        $this->authorizeVersion($request, $songVersion);
        abort_unless($songVersion->generated_sheet_path, 404);
        abort_unless(Storage::disk('local')->exists($songVersion->generated_sheet_path), 404);
        if (! $this->isValidPdf($songVersion->generated_sheet_path)) {
            $path = $exporter->generateSongVersion($songVersion);
            if ($songVersion->generated_sheet_path) Storage::disk('local')->delete($songVersion->generated_sheet_path);
            $songVersion->update(['generated_sheet_path' => $path, 'generated_sheet_at' => now()]);
        }
        return response()->download(Storage::disk('local')->path($songVersion->generated_sheet_path), Str::slug($songVersion->song->title).'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function isValidPdf(string $path): bool
    {
        if (! Storage::disk('local')->exists($path) || Storage::disk('local')->size($path) < 100) return false;
        $contents = Storage::disk('local')->get($path);
        return str_starts_with($contents, '%PDF-')
            && preg_match('/startxref\s+\d+\s+%%EOF/s', $contents) === 1
            && str_contains($contents, 'ComicNeue')
            && str_contains($contents, 'AtkinsonHyperlegibleNext');
    }

    public function image(Request $request, SongVersion $songVersion, SongImage $songImage)
    {
        $this->authorizeVersion($request, $songVersion);
        abort_unless($songImage->song_version_id === $songVersion->id, 404);
        return response()->file(Storage::disk('local')->path($songImage->storage_path), [
            'Content-Type' => $songImage->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function downloadSheet(Request $request, SongVersion $songVersion)
    {
        $this->authorizeVersion($request, $songVersion);
        abort_unless($songVersion->sheet, 404);
        return Storage::disk('local')->download($songVersion->sheet->storage_path, $songVersion->sheet->original_name);
    }

    private function storeSheet(SongVersion $version, UploadedFile $file): void
    {
        $path = $file->storeAs('songs', Str::uuid().'.pdf', 'local');
        $version->sheet()->updateOrCreate([], ['original_name' => $file->getClientOriginalName(), 'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
    }

    private function authorizeVersion(Request $request, SongVersion $version): void
    {
        abort_unless($version->song()->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))->exists(), 404);
    }

    private function authorizeEditableVersion(Request $request, SongVersion $version): void
    {
        abort_unless($version->song()->where('organization_id', $request->user()->organization_id)->exists(), 404);
    }
}
