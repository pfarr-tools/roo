<?php

namespace App\Services;

use App\Models\GroupSongbook;
use App\Models\SongVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class SongbookPdfExporter
{
    public function generateSongVersion(SongVersion $version): string
    {
        $temporary = storage_path('app/temporary/song-'.Str::uuid());
        File::ensureDirectoryExists($temporary);
        $version->load(['song', 'parts', 'images']);
        $pdf = $this->renderVersion($temporary, 0, $version, 'a5');
        $stored = 'songs/generated/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($stored, File::get($pdf));
        File::deleteDirectory($temporary);
        return $stored;
    }

    public function export(GroupSongbook $book, string $format = 'a5', ?string $throughDate = null, ?string $afterDate = null): string
    {
        $book->load(['entries' => fn ($query) => $query->when($throughDate, fn ($nested) => $nested->whereDate('added_at', '<=', $throughDate))->when($afterDate, fn ($nested) => $nested->where('added_at', '>', $afterDate)), 'entries.songVersion.song', 'entries.songVersion.sheet', 'entries.songVersion.parts', 'entries.songVersion.images']);
        $temporary = storage_path('app/temporary/songbook-'.Str::uuid());
        File::ensureDirectoryExists($temporary);
        $pages = [];
        if ($book->title_page_path && Storage::disk('local')->exists($book->title_page_path)) {
            if (str_ends_with(strtolower($book->title_page_path), '.pdf')) $pages[] = Storage::disk('local')->path($book->title_page_path);
            else $pages[] = $this->htmlPage($temporary, 'Titelseite', '<img class="title-image" src="'.e(Storage::disk('local')->path($book->title_page_path)).'">', $format, 'title');
        }
        foreach ($book->entries as $entry) {
            $version = $entry->songVersion;
            if ($version->sheet && Storage::disk('local')->exists($version->sheet->storage_path)) $pages[] = Storage::disk('local')->path($version->sheet->storage_path);
            else $pages[] = $this->renderVersion($temporary, $entry->song_number, $version, $format);
        }
        if ($pages === []) $pages[] = $this->htmlPage($temporary, 'Leeres Liederbuch', '<p>Dieses Liederbuch enthält noch keine Lieder.</p>', $format, 'empty');
        $output = $temporary.'/songbook.pdf';
        try {
            (new Process(array_merge(['pdfunite'], $pages, [$output])))->mustRun();
        } catch (Throwable) {
            if (count($pages) === 1) File::copy($pages[0], $output);
            else $this->minimalPdf($output, 'Der Export benötigt pdfunite oder Chromium für die Zusammenstellung mehrerer Seiten.');
        }
        $stored = 'exports/songbooks/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($stored, File::get($output));
        File::deleteDirectory($temporary);

        return $stored;
    }

    private function renderVersion(string $directory, int $number, SongVersion $version, string $format): string
    {
        $parts = $version->parts->map(fn ($part) => '<section class="part '.($part->is_refrain ? 'refrain' : '').'">'.nl2br(e($part->content)).'</section>')->implode('');
        if ($parts === '') $parts = '<div>'.nl2br(e((string) $version->lyrics)).'</div>';
        $credits = $this->renderCredits($version);
        $images = collect($version->layout_data['images'] ?? [])->map(function (array $image) use ($version): string {
            $record = $version->images->firstWhere('id', $image['id'] ?? null);
            if (! $record || ! Storage::disk('local')->exists($record->storage_path)) return '';
            $transform = 'rotate('.((float) ($image['rotation'] ?? 0)).'deg) scale('.(($image['flipX'] ?? false) ? -1 : 1).', '.(($image['flipY'] ?? false) ? -1 : 1).')';
            return '<img class="placed-image" src="'.e(Storage::disk('local')->path($record->storage_path)).'" style="left:'.((float) ($image['x'] ?? 20)).'px;top:'.((float) ($image['y'] ?? 20)).'px;width:'.((float) ($image['width'] ?? 100)).'px;height:'.((float) ($image['height'] ?? 100)).'px;transform:'.$transform.'">';
        })->implode('');
        $imageCredits = collect($version->layout_data['images'] ?? [])->map(function (array $image) use ($version): ?string {
            $record = $version->images->firstWhere('id', $image['id'] ?? null);
            $credit = trim((string) ($image['credits'] ?? ''));
            return $record && $credit !== '' ? e($credit) : null;
        })->filter()->values();
        $imageCreditBlock = $imageCredits->isNotEmpty() ? '<div class="image-credits"><strong>'.($imageCredits->count() === 1 ? 'Bild:' : 'Bilder:').'</strong> '.$imageCredits->implode(' · ').'</div>' : '';
        return $this->htmlPage($directory, $version->song->title, '<div class="song-number">'.$number.'</div><h1>'.e($version->song->title).'</h1>'.$parts.$images.$credits.$imageCreditBlock, $format, 'song-'.$number);
    }

    private function renderCredits(SongVersion $version): string
    {
        $author = trim((string) $version->song->author);
        $composer = trim((string) $version->song->composer);
        $copyright = trim((string) $version->song->copyright_notice);
        $credit = $author !== '' && $composer !== '' && mb_strtolower($author) === mb_strtolower($composer)
            ? 'Text &amp; Musik: '.e($author)
            : collect([$author !== '' ? 'Text: '.e($author) : null, $composer !== '' ? 'Musik: '.e($composer) : null])->filter()->implode(' / ');
        if ($copyright !== '') $credit .= ($credit !== '' ? '. ' : '').e($copyright);
        return $credit !== '' ? '<div class="song-credits">'.$credit.'</div>' : '';
    }

    private function htmlPage(string $directory, string $title, string $content, string $format, string $name): string
    {
        $size = $format === 'a5' ? '148mm 210mm' : '210mm 297mm';
        $html = '<!doctype html><html lang="de"><head><meta charset="utf-8"><style>@page{size:'.$size.';margin:'.config('songs.page_margin_mm', 12).'mm}*{box-sizing:border-box}body{font-family:"'.config('songs.text_font_family', 'Atkinson Hyperlegible Next').'";font-size:'.config('songs.text_font_size', 14).'pt;font-weight:'.config('songs.text_font_weight', 'normal').';position:relative;margin:0;min-height:100%}h1{font-family:"'.config('songs.title_font_family', 'Comic Neue').'";font-size:'.config('songs.title_font_size', 24).'pt;font-weight:'.config('songs.title_font_weight', 'bold').';margin:0}.song-credits{position:absolute;right:0;bottom:0;font-family:"Atkinson Hyperlegible Next";font-size:8pt;font-weight:normal;text-align:right}.image-credits{position:absolute;right:0;bottom:8mm;font-family:"Atkinson Hyperlegible Next";font-size:8pt;font-weight:normal;text-align:right;max-width:90%;}.part{margin:0 0 7mm;white-space:normal}.refrain{font-family:"'.config('songs.refrain_font_family', 'Comic Neue').'";font-size:'.config('songs.refrain_font_size', 14).'pt;font-weight:'.config('songs.refrain_font_weight', 'normal').';border:0;padding:0}.song-number{float:right;font-size:11pt}.placed-image{position:absolute;object-fit:contain;transform-origin:center}.title-image{width:100%;height:100%;object-fit:contain}</style></head><body>'.$content.'</body></html>';
        $htmlPath = $directory.'/'.$name.'.html';
        File::put($htmlPath, $html);
        $pdfPath = $directory.'/'.$name.'.pdf';
        try {
            (new Process(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--print-to-pdf='.$pdfPath, 'file://'.$htmlPath]))->mustRun();
        } catch (Throwable) {
            $this->minimalPdf($pdfPath, str_replace(['<br>', '<br/>', '<br />'], "\n", strip_tags($content)));
        }
        return $pdfPath;
    }

    private function minimalPdf(string $path, string $text): void
    {
        $lines = preg_split('/\R/', html_entity_decode($text)) ?: [];
        $commands = "BT /F1 12 Tf 40 555 Td ";
        foreach (array_slice($lines, 0, 38) as $index => $line) {
            $line = substr(preg_replace('/[^\\x20-\\x7E\\xC0-\\xFF]/', ' ', trim($line)) ?: '', 0, 92);
            $commands .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).') Tj ';
            if ($index < 37) $commands .= '0 -14 Td ';
        }
        $commands .= 'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 420 595] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($commands).' >>\nstream\n'.$commands.'\nendstream',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) { $offsets[] = strlen($pdf); $pdf .= ($number + 1).' 0 obj\n'.$object."\nendobj\n"; }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
        File::put($path, $pdf);
    }
}
