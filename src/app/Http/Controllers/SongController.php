<?php

namespace App\Http\Controllers;

use App\Models\ResourceReference;
use App\Models\Song;
use App\Models\SongImage;
use App\Models\SongVersion;
use App\Services\SongbookPdfExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SongController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Songs/Index', $this->editorProps($request));
    }

    public function edit(Request $request, SongVersion $songVersion): Response
    {
        $this->authorizeEditableVersion($request, $songVersion);
        $songVersion->load(['song:id,organization_id,title,composer,author,copyright_notice,age_group,topics,notes', 'sheet', 'parts', 'images', 'chordSets.chords']);

        return Inertia::render('Songs/Index', $this->editorProps($request, $songVersion));
    }

    private function editorProps(Request $request, ?SongVersion $songVersion = null): array
    {
        return [
            'songVersion' => $songVersion,
            'isCreating' => $songVersion === null,
            'libraryImages' => ResourceReference::where('organization_id', $request->user()->organization_id)->where('mime_type', 'like', 'image/%')->orderBy('original_name')->get(['id', 'original_name', 'mime_type']),
            'flux' => ['enabled' => filled($request->user()->flux_api_key), 'userName' => $request->user()->name, 'models' => config('flux.models')],
            'songStyles' => collect(config('songs'))->only([
                'title_font_family', 'title_font_size', 'title_font_weight',
                'text_font_family', 'text_font_size', 'text_font_weight',
                'refrain_font_family', 'refrain_font_size', 'refrain_font_weight',
            ])->all(),
        ];
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
        if ($request->hasFile('sheet')) {
            $this->storeSheet($version, $request->file('sheet'));
        }

        return to_route('songs.versions.edit', $version)->with('success', 'Lied wurde gespeichert.');
    }

    public function destroy(Request $request, Song $song): RedirectResponse
    {
        abort_unless($song->organization_id === $request->user()->organization_id, 404);

        $song->load(['versions.sheet', 'versions.images']);
        foreach ($song->versions as $version) {
            if ($version->sheet) {
                Storage::disk('local')->delete($version->sheet->storage_path);
            }
            if ($version->generated_sheet_path) {
                Storage::disk('local')->delete($version->generated_sheet_path);
            }
            if ($version->generated_sheet_a4_path) {
                Storage::disk('local')->delete($version->generated_sheet_a4_path);
            }
            foreach ($version->images as $image) {
                Storage::disk('local')->delete($image->storage_path);
            }
        }
        $song->delete();

        return back()->with('success', 'Lied wurde gelöscht.');
    }

    public function uploadSheet(Request $request, SongVersion $songVersion): RedirectResponse
    {
        $this->authorizeVersion($request, $songVersion);
        $data = $request->validate(['sheet' => ['required', 'file', 'mimes:pdf', 'max:51200']]);
        if ($songVersion->sheet) {
            Storage::disk('local')->delete($songVersion->sheet->storage_path);
        }
        $this->storeSheet($songVersion, $data['sheet']);

        return back()->with('success', 'Liedblatt wurde hochgeladen.');
    }

    public function updateVersion(Request $request, SongVersion $songVersion, SongbookPdfExporter $exporter): RedirectResponse
    {
        $this->authorizeEditableVersion($request, $songVersion);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'language' => ['required', 'string', 'max:10'], 'song' => ['sometimes', 'array'], 'song.title' => ['required_with:song', 'string', 'max:255'], 'song.composer' => ['nullable', 'string', 'max:255'], 'song.author' => ['nullable', 'string', 'max:255'], 'song.copyright_notice' => ['nullable', 'string', 'max:255'], 'song.age_group' => ['nullable', 'string', 'max:255'], 'song.topics' => ['nullable', 'string', 'max:255'], 'song.notes' => ['nullable', 'string'], 'parts' => ['sometimes', 'array'], 'parts.*.id' => ['nullable', 'integer'], 'parts.*.content' => ['required', 'string'], 'parts.*.is_refrain' => ['sometimes', 'boolean'], 'parts.*.is_repeated' => ['sometimes', 'boolean'], 'parts.*.repeat_count' => ['nullable', 'integer', 'min:2'], 'parts.*.is_numbered' => ['sometimes', 'boolean'], 'parts.*.number' => ['nullable', 'integer', 'min:1'], 'chord_sets' => ['sometimes', 'array'], 'chord_sets.*.id' => ['nullable', 'integer'], 'chord_sets.*.instrument' => ['required', 'string', 'max:100', 'distinct'], 'chord_sets.*.name' => ['nullable', 'string', 'max:255'], 'chord_sets.*.key_signature' => ['nullable', 'string', 'max:32'], 'chord_sets.*.chords' => ['sometimes', 'array'], 'chord_sets.*.chords.*.song_part_id' => ['required', 'integer'], 'chord_sets.*.chords.*.line_number' => ['required', 'integer', 'min:0'], 'chord_sets.*.chords.*.repetition' => ['sometimes', 'integer', 'min:0'], 'chord_sets.*.chords.*.character_offset' => ['required', 'integer', 'min:0'], 'chord_sets.*.chords.*.chord' => ['required', 'string', 'max:32'], 'layout_data' => ['nullable', 'array']]);
        DB::transaction(function () use ($data, $songVersion): void {
            $lockedVersion = SongVersion::query()->lockForUpdate()->findOrFail($songVersion->id);
            $lockedVersion->update(collect($data)->only(['name', 'language', 'layout_data'])->all());
            if (isset($data['song'])) {
                $lockedVersion->song->update(collect($data['song'])->only(['title', 'composer', 'author', 'copyright_notice', 'age_group', 'topics', 'notes'])->all());
            }
            if (array_key_exists('parts', $data)) {
                $submittedIds = collect($data['parts'])->pluck('id')->filter()->map(fn ($id): int => (int) $id);
                $lockedVersion->parts()->whereNotIn('id', $submittedIds)->delete();
                foreach (collect($data['parts'])->values() as $position => $part) {
                    $attributes = ['content' => $part['content'], 'position' => $position + 1, 'is_refrain' => $part['is_refrain'] ?? false, 'is_repeated' => $part['is_repeated'] ?? false, 'repeat_count' => ($part['is_repeated'] ?? false) ? ($part['repeat_count'] ?? 2) : null, 'is_numbered' => $part['is_numbered'] ?? false, 'number' => $part['number'] ?? null];
                    $existing = ! empty($part['id']) ? $lockedVersion->parts()->find($part['id']) : null;
                    $existing ? $existing->update($attributes) : $lockedVersion->parts()->create($attributes);
                }
            }
            if (array_key_exists('chord_sets', $data)) {
                $lockedVersion->chordSets()->delete();
                $partIds = $lockedVersion->parts()->pluck('id')->all();
                foreach ($data['chord_sets'] as $set) {
                    $chordSet = $lockedVersion->chordSets()->create(['instrument' => $set['instrument'], 'name' => $set['name'] ?? null, 'key_signature' => $set['key_signature'] ?? null]);
                    $chordSet->chords()->createMany(collect($set['chords'] ?? [])->filter(fn (array $chord): bool => in_array((int) $chord['song_part_id'], $partIds, true))->map(fn (array $chord): array => ['song_part_id' => $chord['song_part_id'], 'line_number' => $chord['line_number'], 'repetition' => $chord['repetition'] ?? 0, 'character_offset' => $chord['character_offset'], 'chord' => $chord['chord']])->all());
                }
            }
        });
        $this->regenerateSheets($songVersion->refresh(), $exporter, $request->user()->name);

        return back()->with('success', 'Liedfassung wurde gespeichert.');
    }

    public function uploadImages(Request $request, SongVersion $songVersion): RedirectResponse
    {
        $this->authorizeEditableVersion($request, $songVersion);
        $data = $request->validate(['images' => ['required', 'array', 'max:20'], 'images.*' => ['image', 'max:10240'], 'copyrights' => ['nullable', 'string', 'max:1000']]);
        foreach ($data['images'] as $image) {
            $songVersion->images()->create(['original_name' => $image->getClientOriginalName(), 'copyrights' => $data['copyrights'] ?? null, 'storage_path' => $image->store('songs/images', 'local'), 'mime_type' => $image->getMimeType(), 'size' => $image->getSize()]);
        }

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
        $songVersion->images()->create(['original_name' => $resource->original_name, 'copyrights' => $resource->copyrights, 'storage_path' => $path, 'mime_type' => $resource->mime_type, 'size' => Storage::disk('local')->size($path)]);

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
        $this->regenerateSheets($songVersion, $exporter, $request->user()->name);

        return back()->with('success', 'A5-Liedblatt wurde erzeugt.');
    }

    public function generatedSheet(Request $request, SongVersion $songVersion, SongbookPdfExporter $exporter, string $format = 'a5')
    {
        $this->authorizeVersion($request, $songVersion);
        abort_unless(in_array($format, ['a5', 'a4'], true), 404);
        $pathColumn = $format === 'a4' ? 'generated_sheet_a4_path' : 'generated_sheet_path';
        $path = $songVersion->{$pathColumn};
        if (! $path || ! Storage::disk('local')->exists($path) || ! $this->isValidPdf($path)) {
            $this->regenerateSheets($songVersion, $exporter, $request->user()->name);
            $songVersion->refresh();
            $path = $songVersion->{$pathColumn};
        }

        return response()->download(Storage::disk('local')->path($path), $this->songSheetFilename($songVersion->song->title, $format), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function regenerateSheets(SongVersion $version, SongbookPdfExporter $exporter, ?string $author = null): void
    {
        $version->load(['song', 'parts', 'images']);
        $oldA5Path = $version->generated_sheet_path;
        $oldA4Path = $version->generated_sheet_a4_path;
        $a5Path = $exporter->generateSongVersion($version, $author);
        $a4Path = $exporter->generateSongVersionA4($version, $author);
        $version->update(['generated_sheet_path' => $a5Path, 'generated_sheet_at' => now(), 'generated_sheet_a4_path' => $a4Path, 'generated_sheet_a4_at' => now()]);
        if ($oldA5Path) {
            Storage::disk('local')->delete($oldA5Path);
        }
        if ($oldA4Path) {
            Storage::disk('local')->delete($oldA4Path);
        }
    }

    private function songSheetFilename(string $title, string $format): string
    {
        $safeTitle = preg_replace('/[<>:"\/\\|?*\x00-\x1F]/u', '-', $title) ?: 'Lied';
        $safeTitle = rtrim(trim($safeTitle), '. ');

        return ($safeTitle !== '' ? $safeTitle : 'Lied').($format === 'a5' ? ' A5' : '').'.pdf';
    }

    private function isValidPdf(string $path): bool
    {
        if (! Storage::disk('local')->exists($path) || Storage::disk('local')->size($path) < 100) {
            return false;
        }
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
