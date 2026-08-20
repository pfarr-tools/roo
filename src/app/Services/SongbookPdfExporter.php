<?php

namespace App\Services;

use App\Models\GroupSongbook;
use App\Models\SongVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class SongbookPdfExporter
{
    public function __construct(private readonly SongbookContentsResolver $contentsResolver) {}

    public function generateSongVersion(SongVersion $version, ?string $author = null): string
    {
        return $this->generateSongVersionFormat($version, 'a5', $author);
    }

    public function generateSongVersionA4(SongVersion $version, ?string $author = null): string
    {
        return $this->generateSongVersionFormat($version, 'a4', $author);
    }

    public function generateTitlePageA4(string $sourcePath): string
    {
        $temporary = storage_path('app/temporary/title-page-a4-'.Str::uuid());
        File::ensureDirectoryExists($temporary);
        $source = Storage::disk('local')->path($sourcePath);
        $image = $source;

        try {
            if (str_ends_with(strtolower($sourcePath), '.pdf')) {
                (new Process(['pdftoppm', '-f', '1', '-singlefile', '-png', $source, $temporary.'/title']))->mustRun();
                $image = $temporary.'/title.png';
            }
            $pdf = $this->htmlPage($temporary, 'Titelseite', '<img class="title-image" src="'.e($image).'">', 'a4', 'title-page-a4');
        } catch (Throwable) {
            $pdf = $source;
        }

        $stored = 'songs/generated/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($stored, File::get($pdf));
        File::deleteDirectory($temporary);

        return $stored;
    }

    private function generateSongVersionFormat(SongVersion $version, string $format, ?string $author): string
    {
        $temporary = storage_path('app/temporary/song-'.$format.'-'.Str::uuid());
        File::ensureDirectoryExists($temporary);
        $version->load(['song', 'parts', 'images']);
        $pdf = $this->renderVersion($temporary, 0, $version, $format);
        $this->writePdfMetadata($pdf, $version->song->title, $author, $this->creditsText($version));
        $stored = 'songs/generated/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($stored, File::get($pdf));
        File::deleteDirectory($temporary);
        return $stored;
    }

    public function export(GroupSongbook $book, string $format = 'a5', ?string $throughDate = null, ?string $afterDate = null): string
    {
        $entries = $this->contentsResolver->resolve($book, $throughDate, $afterDate);
        $imprint = $this->imprintText($book);
        $temporary = storage_path('app/temporary/songbook-'.Str::uuid());
        File::ensureDirectoryExists($temporary);
        $pages = [];
        if ($afterDate === null && $book->title_page_path && Storage::disk('local')->exists($book->title_page_path)) {
            $titlePagePath = $format === 'a4' && $book->title_page_a4_path && Storage::disk('local')->exists($book->title_page_a4_path)
                ? $book->title_page_a4_path
                : $book->title_page_path;
            if (str_ends_with(strtolower($titlePagePath), '.pdf')) $pages[] = Storage::disk('local')->path($titlePagePath);
            else $pages[] = $this->htmlPage($temporary, 'Titelseite', '<img class="title-image" src="'.e(Storage::disk('local')->path($titlePagePath)).'">', $format, 'title');
        }
        foreach ($entries as $entry) {
            $version = $entry->songVersion;
            $sourcePath = $format === 'a4' && $version->generated_sheet_a4_path && Storage::disk('local')->exists($version->generated_sheet_a4_path)
                ? $version->generated_sheet_a4_path
                : ($format !== 'a4' && $version->generated_sheet_path && Storage::disk('local')->exists($version->generated_sheet_path)
                    ? $version->generated_sheet_path
                    : ($version->sheet && Storage::disk('local')->exists($version->sheet->storage_path) ? $version->sheet->storage_path : null));
            $pages[] = $sourcePath
                ? $this->overlaySongNumber($temporary, Storage::disk('local')->path($sourcePath), $entry->song_number, $format, 'song-'.$entry->song_number, $imprint)
                : $this->renderVersion($temporary, $entry->song_number, $version, $format, $imprint);
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

    public function exportSongs(Collection $versions, string $format = 'a5', ?GroupSongbook $book = null): string
    {
        $temporary = storage_path('app/temporary/hour-songs-'.Str::uuid());
        File::ensureDirectoryExists($temporary);
        $numberByVersion = $book
            ? $this->contentsResolver->resolve($book)->keyBy('song_version_id')
            : collect();
        $imprint = $book ? $this->imprintText($book) : null;
        $pages = $versions->values()->map(function (SongVersion $version) use ($temporary, $format, $numberByVersion, $imprint): string {
            $entry = $numberByVersion->get($version->id);
            $number = $entry?->song_number ?? 0;
            $sourcePath = $format === 'a4' && $version->generated_sheet_a4_path && Storage::disk('local')->exists($version->generated_sheet_a4_path)
                ? $version->generated_sheet_a4_path
                : ($format !== 'a4' && $version->generated_sheet_path && Storage::disk('local')->exists($version->generated_sheet_path)
                    ? $version->generated_sheet_path
                    : ($version->sheet && Storage::disk('local')->exists($version->sheet->storage_path) ? $version->sheet->storage_path : null));

            return $sourcePath
                ? $this->overlaySongNumber($temporary, Storage::disk('local')->path($sourcePath), $number, $format, 'song-'.$version->id, $imprint)
                : $this->renderVersion($temporary, $number, $version, $format, $imprint);
        })->all();
        if ($pages === []) $pages[] = $this->htmlPage($temporary, 'Keine neuen Lieder', '<p>Für diese Stunde sind keine neuen Lieder zugeordnet.</p>', $format, 'empty');
        $output = $temporary.'/songs.pdf';
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

    private function renderVersion(string $directory, int $number, SongVersion $version, string $format, ?string $imprint = null): string
    {
        $previousNumber = 0;
        $parts = $version->parts->map(function ($part) use (&$previousNumber): string {
            $number = null;
            if ($part->is_numbered) {
                $number = $part->number ?? $previousNumber + 1;
                $previousNumber = $number;
            }
            $prefix = $number === null ? '' : $number.'. ';
            return '<section class="part '.($part->is_refrain ? 'refrain' : '').'">'.e($prefix.$part->content).'</section>';
        })->implode('');
        if ($parts === '') $parts = '<div class="part">'.e((string) $version->lyrics).'</div>';
        $images = collect($version->layout_data['images'] ?? [])->map(function (array $image) use ($version): string {
            $record = $version->images->firstWhere('id', $image['id'] ?? null);
            if (! $record || ! Storage::disk('local')->exists($record->storage_path)) return '';
            $transform = 'rotate('.((float) ($image['rotation'] ?? 0)).'deg) scale('.(($image['flipX'] ?? false) ? -1 : 1).', '.(($image['flipY'] ?? false) ? -1 : 1).')';
            $x = ((float) ($image['x'] ?? 20)) * 148 / 420;
            $y = ((float) ($image['y'] ?? 20)) * 210 / 595.28;
            $width = ((float) ($image['width'] ?? 100)) * 148 / 420;
            $height = ((float) ($image['height'] ?? 100)) * 210 / 595.28;
            return '<img class="placed-image" src="'.e(Storage::disk('local')->path($record->storage_path)).'" style="left:'.$x.'mm;top:'.$y.'mm;width:'.$width.'mm;height:'.$height.'mm;transform:'.$transform.'">';
        })->implode('');
        $imageCredits = collect($version->layout_data['images'] ?? [])->map(function (array $image) use ($version): ?string {
            $record = $version->images->firstWhere('id', $image['id'] ?? null);
            $credit = trim((string) ($image['credits'] ?? ''));
            return $record && $credit !== '' ? $credit : null;
        })->filter()->values();
        $credits = $this->renderCredits($version, $imageCredits->all());
        $heading = '<div class="song-heading"><h1>'.e($version->song->title).'</h1><span class="song-number"'.($number === 0 ? ' style="display:none"' : '').'>'.$number.'</span></div>';
        $page = $heading.$parts.$images.$credits;
        if ($imprint !== null) $page .= '<div class="song-imprint">'.e($imprint).'</div>';
        $content = $format === 'a4' ? '<div class="a4-copy a4-copy-left">'.$page.'</div><div class="a4-copy a4-copy-right">'.$page.'</div>' : $page;
        return $this->htmlPage($directory, $version->song->title, $content, $format, 'song-'.$number);
    }

    private function imprintText(GroupSongbook $book): string
    {
        $book->loadMissing('group.school', 'group.schoolYear');
        $group = $book->group;

        return collect([$group?->school?->name, $group?->schoolYear?->name, $group?->name])
            ->map(fn (?string $value): string => trim((string) $value))
            ->filter()
            ->implode(' ');
    }

    /** Add print-only markings to an existing PDF without rasterizing it. */
    private function overlaySongNumber(string $directory, string $sourcePath, int $number, string $format, string $name, ?string $imprint = null): string
    {
        $pageFormat = $format === 'a4' && str_contains($sourcePath, 'generated') ? 'a4' : 'a5';
        $pageCount = $this->pdfPageCount($sourcePath);
        $stampedPages = [];

        for ($page = 1; $page <= $pageCount; $page++) {
            $sourcePage = $directory.'/'.$name.'-source-'.$page.'.pdf';
            $overlay = $directory.'/'.$name.'-overlay-'.$page.'.pdf';
            $stampedPage = $directory.'/'.$name.'-stamped-'.$page.'.pdf';
            (new Process(['pdftk', $sourcePath, 'cat', (string) $page, 'output', $sourcePage]))->mustRun();
            $this->renderPrintOverlay($directory, $overlay, $page === 1 ? $number : 0, $pageFormat, $imprint, $name.'-'.$page);
            (new Process(['pdftk', $sourcePage, 'stamp', $overlay, 'output', $stampedPage]))->mustRun();
            $stampedPages[] = $stampedPage;
        }

        $output = $directory.'/'.$name.'-stamped.pdf';
        (new Process(array_merge(['pdfunite'], $stampedPages, [$output])))->mustRun();
        return $output;
    }

    private function pdfPageCount(string $path): int
    {
        $result = (new Process(['pdfinfo', $path]))->mustRun()->getOutput();
        preg_match('/^Pages:\s+(\d+)$/m', $result, $matches);
        return max(1, (int) ($matches[1] ?? 1));
    }

    private function renderPrintOverlay(string $directory, string $pdfPath, int $number, string $pageFormat, ?string $imprint, string $name): void
    {
        $pageWidth = $pageFormat === 'a4' ? '297mm' : '148mm';
        $top = config('songs.page_margin_top_mm', 17);
        $right = config('songs.page_margin_right_mm', 17);
        $left = config('songs.page_margin_left_mm', 20);
        $bottom = config('songs.page_margin_bottom_mm', 17);
        $font = config('songs.title_font_family', 'Comic Neue');
        $size = config('songs.title_font_size', 24);
        $weight = config('songs.title_font_weight', 'bold');
        $numberMarkup = $number > 0
            ? ($pageFormat === 'a4'
                ? '<span class="number number-left">'.$number.'</span><span class="number number-right">'.$number.'</span>'
                : '<span class="number number-a5">'.$number.'</span>')
            : '';
        $imprintMarkup = $imprint !== null
            ? ($pageFormat === 'a4'
                ? '<span class="imprint imprint-left">'.e($imprint).'</span><span class="imprint imprint-right">'.e($imprint).'</span>'
                : '<span class="imprint imprint-a5">'.e($imprint).'</span>')
            : '';
        $html = '<!doctype html><html lang="de"><head><meta charset="utf-8"><style>'.$this->fontFaceCss().'@page{size:'.$pageWidth.' 210mm;margin:0}*{box-sizing:border-box}html,body{width:'.$pageWidth.';height:210mm;margin:0;background:transparent;overflow:hidden}.number{position:absolute;top:'.$top.'mm;font-family:"'.$font.'";font-size:'.$size.'pt;font-weight:'.$weight.';line-height:1;text-align:right}.number-a5{right:'.$right.'mm;width:20mm}.number-left,.number-right{width:20mm}.number-left{left:'.(148 - $right - 20).'mm}.number-right{right:'.$right.'mm}.imprint{position:absolute;bottom:'.$bottom.'mm;font-family:"Atkinson Hyperlegible Next";font-size:6pt;font-weight:normal;color:#6c757d;line-height:1;white-space:nowrap;transform:rotate(-90deg);transform-origin:left bottom}.imprint-a5,.imprint-left{left:'.$left.'mm}.imprint-right{left:'.(149 + $left).'mm}</style></head><body>'.$numberMarkup.$imprintMarkup.'</body></html>';
        $htmlPath = $directory.'/'.$name.'-overlay.html';
        File::put($htmlPath, $html);
        (new Process(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--no-pdf-header-footer', '--run-all-compositor-stages-before-draw', '--user-data-dir='.$directory.'/chromium-overlay-profile-'.$name, '--print-to-pdf='.$pdfPath, 'file://'.$htmlPath]))->mustRun();
        if (! File::exists($pdfPath) || File::size($pdfPath) < 100) throw new \RuntimeException('Die PDF-Druckmarkierung konnte nicht erzeugt werden.');
    }

    private function renderCredits(SongVersion $version, array $imageCredits = []): string
    {
        $text = $this->creditsText($version, $imageCredits);
        return $text !== '' ? '<div class="song-credits">'.nl2br(e($text)).'</div>' : '';
    }

    private function creditsText(SongVersion $version, array $imageCredits = []): string
    {
        $author = trim((string) $version->song->author);
        $composer = trim((string) $version->song->composer);
        $copyright = trim((string) $version->song->copyright_notice);
        $credit = $author !== '' && $composer !== '' && mb_strtolower($author) === mb_strtolower($composer)
            ? 'Text & Musik: '.$author
            : collect([$author !== '' ? 'Text: '.$author : null, $composer !== '' ? 'Musik: '.$composer : null])->filter()->implode(' / ');
        if ($copyright !== '') $credit .= ($credit !== '' ? '. ' : '').$copyright;
        $lines = array_filter([$credit, $imageCredits !== [] ? ($this->imageCreditLabel($imageCredits).' '.implode(' · ', $imageCredits)) : null]);
        return implode("\n", $lines);
    }

    private function imageCreditLabel(array $credits): string
    {
        return count($credits) === 1 ? 'Bild:' : 'Bilder:';
    }

    private function writePdfMetadata(string $pdfPath, string $title, ?string $author, string $subject): void
    {
        $pdf = File::get($pdfPath);
        preg_match_all('/(\d+)\s+0\s+obj\b/', $pdf, $objects);
        $objectNumber = ((int) (max($objects[1] ?? [0]))) + 1;
        $trailerPosition = strrpos($pdf, 'trailer');
        $startxrefPosition = strrpos($pdf, 'startxref');
        if ($trailerPosition === false || $startxrefPosition === false) return;
        $trailer = substr($pdf, $trailerPosition, $startxrefPosition - $trailerPosition);
        preg_match('/\/Root\s+(\d+)\s+0\s+R/', $trailer, $root);
        preg_match('/startxref\s+(\d+)/', substr($pdf, $startxrefPosition), $previous);
        if (! isset($root[1], $previous[1])) return;

        $object = $objectNumber." 0 obj\n<< /Title ".$this->pdfMetadataString($title)." /Author ".$this->pdfMetadataString((string) $author)." /Subject ".$this->pdfMetadataString($subject)." /Creator ".$this->pdfMetadataString('Roo')." /Producer ".$this->pdfMetadataString('Roo')." >>\nendobj\n";
        $objectOffset = strlen($pdf);
        $pdf .= $object;
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n".$objectNumber." 1\n".sprintf('%010d 00000 n ', $objectOffset)."\ntrailer\n<< /Size ".($objectNumber + 1).' /Root '.$root[1].' 0 R /Info '.$objectNumber.' 0 R /Prev '.$previous[1]." >>\nstartxref\n".$xrefOffset."\n%%EOF\n";
        File::put($pdfPath, $pdf);
    }

    private function pdfMetadataString(string $value): string
    {
        $encoded = mb_convert_encoding($value, 'UTF-16BE', 'UTF-8');
        return '<'.strtoupper(bin2hex("\xFE\xFF".$encoded)).'>';
    }

    private function htmlPage(string $directory, string $title, string $content, string $format, string $name): string
    {
        $pageWidth = $format === 'a5' ? '148mm' : '297mm';
        $pageHeight = $format === 'a5' ? '210mm' : '210mm';
        $size = $pageWidth.' '.$pageHeight;
        $top = config('songs.page_margin_top_mm', 17);
        $right = config('songs.page_margin_right_mm', 17);
        $bottom = config('songs.page_margin_bottom_mm', 17);
        $left = config('songs.page_margin_left_mm', 20);
        $canvasPadding = $format === 'a4' ? 'padding:0' : 'padding:'.$top.'mm '.$right.'mm '.$bottom.'mm '.$left.'mm';
        $copyCss = $format === 'a4' ? '.a4-copy{position:absolute;top:0;width:148mm;height:210mm;padding:'.$top.'mm '.$right.'mm '.$bottom.'mm '.$left.'mm;overflow:hidden}.a4-copy-left{left:0}.a4-copy-right{left:149mm}' : '';
        $titleFont = config('songs.title_font_family', 'Comic Neue');
        $titleSize = config('songs.title_font_size', 24);
        $titleWeight = config('songs.title_font_weight', 'bold');
        $html = '<!doctype html><html lang="de"><head><meta charset="utf-8"><style>'.$this->fontFaceCss().'@page{size:'.$size.';margin:0}*{box-sizing:border-box}body{font-family:"'.config('songs.text_font_family', 'Atkinson Hyperlegible Next').'";font-size:'.config('songs.text_font_size', 14).'pt;font-weight:'.config('songs.text_font_weight', 'normal').';width:'.$pageWidth.';height:'.$pageHeight.';position:relative;margin:0;overflow:hidden}.song-export-canvas{position:relative;width:'.$pageWidth.';height:'.$pageHeight.';'.$canvasPadding.';overflow:hidden}'.$copyCss.'.song-heading{display:flex;align-items:baseline;justify-content:space-between;gap:1rem;margin:0 0 2rem}.song-heading h1{font-family:"'.$titleFont.'";font-size:'.$titleSize.'pt;font-weight:'.$titleWeight.';margin:0;min-width:0}.song-number{flex:0 0 auto;font-family:"'.$titleFont.'";font-size:'.$titleSize.'pt;font-weight:'.$titleWeight.';line-height:1}.song-imprint{position:absolute;left:'.$left.'mm;bottom:'.$bottom.'mm;font-family:"Atkinson Hyperlegible Next";font-size:6pt;font-weight:normal;color:#6c757d;line-height:1;white-space:nowrap;transform:rotate(-90deg);transform-origin:left bottom}.song-credits{position:absolute;right:'.$right.'mm;bottom:'.$bottom.'mm;font-family:"Atkinson Hyperlegible Next";font-size:8pt;font-weight:normal;text-align:right;max-width:85%}.part{margin:0 0 1.25rem;white-space:pre-line}.refrain{font-family:"'.config('songs.refrain_font_family', 'Comic Neue').'";font-size:'.config('songs.refrain_font_size', 14).'pt;font-weight:'.config('songs.refrain_font_weight', 'normal').';border:0;padding:0}.placed-image{position:absolute;object-fit:contain;transform-origin:center}.title-image{width:100%;height:100%;object-fit:contain}</style></head><body><div class="song-export-canvas">'.$content.'</div></body></html>';
        $htmlPath = $directory.'/'.$name.'.html';
        File::put($htmlPath, $html);
        $pdfPath = $directory.'/'.$name.'.pdf';
        try {
            (new Process(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--no-pdf-header-footer', '--run-all-compositor-stages-before-draw', '--user-data-dir='.$directory.'/chromium-profile', '--print-to-pdf='.$pdfPath, 'file://'.$htmlPath]))->mustRun();
            if (! File::exists($pdfPath) || File::size($pdfPath) < 100 || substr((string) File::get($pdfPath), 0, 5) !== '%PDF-') throw new \RuntimeException('Chromium erzeugte keine gültige PDF-Datei.');
        } catch (Throwable) {
            $this->minimalPdf($pdfPath, str_replace(['<br>', '<br/>', '<br />'], "\n", strip_tags($content)));
        }
        return $pdfPath;
    }

    private function fontFaceCss(): string
    {
        $fonts = [
            ['Comic Neue', 400, 'ComicNeue-Regular.ttf', 'truetype'],
            ['Comic Neue', 700, 'ComicNeue-Bold.ttf', 'truetype'],
            ['Atkinson Hyperlegible Next', 400, 'AtkinsonHyperlegibleNext-Regular.otf', 'opentype'],
            ['Atkinson Hyperlegible Next', 700, 'AtkinsonHyperlegibleNext-Bold.otf', 'opentype'],
        ];
        return collect($fonts)->map(function (array $font): string {
            $path = resource_path('fonts/'.$font[2]);
            return is_file($path) ? '@font-face{font-family:"'.$font[0].'";font-style:normal;font-weight:'.$font[1].';src:url("data:font/'.$font[3].';base64,'.base64_encode((string) file_get_contents($path)).'") format("'.$font[3].'");}' : '';
        })->implode('');
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
            "<< /Length ".strlen($commands)." >>\nstream\n".$commands."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) { $offsets[] = strlen($pdf); $pdf .= ($number + 1)." 0 obj\n".$object."\nendobj\n"; }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
        File::put($path, $pdf);
    }
}
