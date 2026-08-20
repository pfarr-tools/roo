<?php

namespace App\Http\Controllers;

use App\Models\GroupSongbook;
use App\Models\PrintCheckpoint;
use App\Models\SongVersion;
use App\Models\TeachingGroup;
use App\Services\SongbookPdfExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SongbookController extends Controller
{
    public function updateSongs(Request $request, TeachingGroup $teachingGroup): RedirectResponse
    {
        $this->authorize('update', $teachingGroup);
        $data = $request->validate(['song_version_ids' => ['sometimes', 'array'], 'song_version_ids.*' => ['integer']]);
        $ids = collect($data['song_version_ids'] ?? [])->unique()->values();
        abort_unless(SongVersion::whereIn('id', $ids)->whereHas('song', fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $teachingGroup->organization_id))->count() === $ids->count(), 422, 'Ein Lied ist nicht verfügbar.');
        $book = $teachingGroup->songbook()->firstOrCreate([]);
        $existing = $book->entries()->pluck('song_version_id');
        $book->entries()->whereNotIn('song_version_id', $ids)->delete();
        foreach ($ids as $id) {
            if (! $existing->contains($id)) $book->entries()->create(['song_version_id' => $id, 'song_number' => ((int) $book->entries()->max('song_number')) + 1, 'added_at' => now()]);
        }
        return back()->with('success', 'Ausgangsbestand des Gruppenliederbuchs wurde gespeichert.');
    }

    public function export(Request $request, TeachingGroup $teachingGroup, SongbookPdfExporter $exporter)
    {
        $this->authorize('view', $teachingGroup);
        $data = $request->validate(['format' => ['required', 'in:a5,a4,brochure,new'], 'through_date' => ['nullable', 'date']]);
        $book = $teachingGroup->songbook()->firstOrCreate([]);
        $after = null;
        if ($data['format'] === 'new') {
            $after = $book->checkpoints()->latest('printed_at')->value('printed_at');
            $format = 'a5';
        } else $format = $data['format'];
        $path = $exporter->export($book, $format, $data['through_date'] ?? null, $after);
        $export = $book->exports()->create(['format' => $data['format'], 'through_date' => $data['through_date'] ?? null, 'storage_path' => $path, 'entry_count' => $book->entries()->when($data['through_date'] ?? null, fn ($query) => $query->whereDate('added_at', '<=', $data['through_date']))->count()]);
        $book->checkpoints()->create(['printed_at' => now(), 'entry_count' => $export->entry_count]);
        return Storage::disk('local')->download($path, 'Gruppenliederbuch-'.$format.'.pdf');
    }
}
