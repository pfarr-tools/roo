<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\SongVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SongController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $songs = Song::where(fn ($builder) => $builder->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))
            ->with(['versions.sheet'])->when($query !== '', fn ($builder) => $builder->where('title', 'like', "%{$query}%"))
            ->orderBy('title')->get();

        return Inertia::render('Songs/Index', ['songs' => $songs, 'filters' => ['q' => $query]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'composer' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'], 'copyright_notice' => ['nullable', 'string', 'max:255'],
            'age_group' => ['nullable', 'string', 'max:255'], 'topics' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'], 'version_name' => ['required', 'string', 'max:255'],
            'lyrics' => ['nullable', 'string'], 'notation' => ['nullable', 'string'], 'chords' => ['nullable', 'string'],
            'rights_status' => ['required', 'in:unknown,cleared,restricted,licensed'],
            'rights_note' => ['nullable', 'string'], 'text_export_allowed' => ['sometimes', 'boolean'],
            'metadata_export_allowed' => ['sometimes', 'boolean'],
            'sheet' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $song = Song::create(collect($data)->only(['title', 'composer', 'author', 'copyright_notice', 'age_group', 'topics', 'notes'])->merge(['organization_id' => $request->user()->organization_id])->all());
        $version = $song->versions()->create(collect($data)->only(['version_name', 'lyrics', 'notation', 'chords', 'rights_status', 'rights_note', 'text_export_allowed', 'metadata_export_allowed'])->merge(['name' => $data['version_name']])->all());
        if ($request->hasFile('sheet')) $this->storeSheet($version, $request->file('sheet'));

        return back()->with('success', 'Lied wurde gespeichert.');
    }

    public function uploadSheet(Request $request, SongVersion $songVersion): RedirectResponse
    {
        $this->authorizeVersion($request, $songVersion);
        $data = $request->validate(['sheet' => ['required', 'file', 'mimes:pdf', 'max:51200']]);
        if ($songVersion->sheet) Storage::disk('local')->delete($songVersion->sheet->storage_path);
        $this->storeSheet($songVersion, $data['sheet']);
        return back()->with('success', 'Liedblatt wurde hochgeladen.');
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
}
