<?php

namespace App\Console\Commands;

use App\Models\Song;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ImportSongsFromPdfCommand extends Command
{
    protected $signature = 'songs:import-pdf {directory : Verzeichnis mit Lied-PDFs} {--user=1 : Eigentümer der importierten Lieder}';

    protected $description = 'Importiert Liedtexte und Liedblätter aus PDF-Dateien.';

    /** @var array<string, array{author?: string, composer?: string, copyright?: string}> */
    private const METADATA = [
        'Ein Schiff, das sich Gemeinde nennt' => ['author' => 'Martin Gotthard Schneider (1960)', 'composer' => 'Martin Gotthard Schneider (1960)', 'copyright' => '© Martin Gotthard Schneider'],
        'Bewahre uns, Gott, behüte uns, Gott' => ['author' => 'Eugen Eckert (1985)', 'copyright' => '© Eugen Eckert'],
        'Gib uns Frieden jeden Tag!' => ['author' => 'Rüdeger Lüders (1963)', 'composer' => 'Kurt Rommel (1963)', 'copyright' => '© Rüdeger Lüders / Kurt Rommel'],
        'Gott, dein guter Segen' => ['author' => 'Reinhard Bäcker (1987)', 'copyright' => '© Reinhard Bäcker'],
        'Herr, gib mir Mut zum Brückenbauen' => ['author' => 'Kurt Rommel (1963)', 'copyright' => '© Kurt Rommel'],
        'Herr, wir bitten: Komm und segne uns' => ['author' => 'Peter Strauch (1977)', 'copyright' => '© Peter Strauch'],
        'Komm, Herr, segne uns' => ['author' => 'Dieter Trautwein (1978)', 'copyright' => '© Dieter Trautwein'],
        'Stern über Bethlehem' => ['author' => 'Alfred Hans Zoller (1963)', 'copyright' => '© Alfred Hans Zoller'],
        'Weißt du, wo der Himmel ist' => ['author' => 'Wilhelm Willms (1976)', 'copyright' => '© Wilhelm Willms'],
        'Wo Menschen sich vergessen' => ['author' => 'Thomas Laubach (1989)', 'copyright' => '© Thomas Laubach'],
        'Wir sagen euch an den lieben Advent' => ['author' => 'Maria Ferschl (1954)', 'copyright' => '© Maria Ferschl'],
        'Gott ist stark' => ['copyright' => 'Urheberangabe in der Vorlage nicht eindeutig ermittelbar'],
        'Hey' => ['copyright' => 'Urheberangabe in der Vorlage nicht eindeutig ermittelbar'],
        'Lass mein Volk doch zieh’n' => ['author' => 'Spiritual; deutsche Fassung unbekannt', 'copyright' => 'Traditioneller Spiritual; Rechte der deutschen Fassung prüfen'],
        'Let my people go' => ['author' => 'Traditional Spiritual', 'copyright' => 'Traditional Spiritual'],
        'O du fröhliche' => ['author' => 'Johannes Daniel Falk (1816); Heinrich Holzschuher (1829)', 'copyright' => 'Gemeinfrei'],
        'Ausgang und Eingang' => ['author' => 'Joachim Schwarz (1962)', 'copyright' => '© Joachim Schwarz'],
    ];

    private const SKIP = [
        'Gebet zum Advent.pdf', 'Gebete Klasse 1.pdf', 'Kerzengebet Klasse 1.pdf',
        'Psalm 23 Klasse 1.pdf', 'Titelseite.pdf',
    ];

    public function handle(): int
    {
        $directory = rtrim((string) $this->argument('directory'), '/');
        $userId = (int) $this->option('user');
        $files = glob($directory.'/*.pdf') ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, self::SKIP, true)) {
                $this->line('Übersprungen: '.$filename);
                $skipped++;

                continue;
            }
            $entries = $this->entriesFor($file);
            if ($entries === []) {
                $this->warn('Kein maschinenlesbarer Liedtext: '.$filename);
                $skipped++;

                continue;
            }
            foreach ($entries as $entry) {
                $this->importEntry($entry, $file, $userId);
                $imported++;
            }
        }

        $this->info("{$imported} Liedfassungen importiert; {$skipped} Dateien übersprungen.");

        return self::SUCCESS;
    }

    /** @return list<array{title: string, language: string, text: string}> */
    private function entriesFor(string $file): array
    {
        $filename = basename($file);
        $text = $this->pdfText($file);
        if (trim($text) === '') {
            return [];
        }
        if ($filename === 'Gott ist stark + Hey.pdf') {
            return [['title' => 'Gott ist stark', 'language' => 'de', 'text' => $this->section($text, 'Gott ist stark', 'Hey')], ['title' => 'Hey', 'language' => 'de', 'text' => $this->section($text, 'Hey', null)]];
        }
        if ($filename === 'Ausgang und Eingang + Herr, wir bitten komm und segne uns.pdf') {
            return [['title' => 'Ausgang und Eingang', 'language' => 'de', 'text' => $this->section($text, 'Ausgang und Eingang', 'Herr, wir bitten')], ['title' => 'Herr, wir bitten: Komm und segne uns', 'language' => 'de', 'text' => $this->section($text, 'Herr, wir bitten', null)]];
        }
        if ($filename === 'Tragt zu den Menschen + O du fröhliche.pdf') {
            return [['title' => 'Tragt zu den Menschen ein Licht', 'language' => 'de', 'text' => $this->section($text, 'Tragt zu den Menschen', 'O du fröhliche')], ['title' => 'O du fröhliche', 'language' => 'de', 'text' => $this->section($text, 'O du fröhliche', null)]];
        }
        if ($filename === 'Let my people go (de + en).pdf') {
            return [['title' => 'Lass mein Volk doch zieh’n', 'language' => 'de', 'text' => $this->pageSection($text, 0)], ['title' => 'Let my people go', 'language' => 'en', 'text' => $this->pageSection($text, 1)]];
        }

        $title = $this->titleFrom($filename, $text);
        $text = $this->firstCopy($text, $title);

        return [['title' => $title, 'language' => 'de', 'text' => $text]];
    }

    private function pdfText(string $file): string
    {
        $process = new Process(['pdftotext', '-raw', $file, '-']);
        $process->mustRun();

        return $process->getOutput();
    }

    private function titleFrom(string $filename, string $text): string
    {
        $known = [
            'Ein Schiff, das sich Gemeinde nennt.pdf' => 'Ein Schiff, das sich Gemeinde nennt',
            'Bewahre uns Gott.pdf' => 'Bewahre uns, Gott, behüte uns, Gott',
            'Christ ist erstanden.pdf' => 'Christ ist erstanden',
            'Du bist du.pdf' => 'Vergiss es nie (Du bist du)',
            'Feiert Jesus.pdf' => 'Feiert Jesus!',
            'Geh, Abraham, geh.pdf' => 'Geh, Abraham, geh',
            'Gelobt sei Gott im höchsten Thron.pdf' => 'Gelobt sei Gott im höchsten Thron',
            'Gib uns Frieden jeden Tag.pdf' => 'Gib uns Frieden jeden Tag!',
            'Großer Gott, wir loben dich.pdf' => 'Großer Gott, wir loben dich',
            'Gute-Laune-Song.pdf' => 'Der Gute-Laune-Song',
            'Hallo, Ciao ciao.pdf' => 'Hallo, Ciao, Ciao',
            'Herr, gib mir Mut zum Brückenbauen.pdf' => 'Herr, gib mir Mut zum Brückenbauen',
            'Ich bin getauft auf deinen Namen.pdf' => 'Ich bin getauft auf deinen Namen',
            'Komm, Herr, segne uns.pdf' => 'Komm, Herr, segne uns',
            'Seht, die gute Zeit ist da.pdf' => 'Seht, die gute Zeit ist nah',
            'Weißt du, wieviel Sternlein stehen.pdf' => 'Weißt du, wie viel Sternlein stehen',
            'Wie ein Fest (So ist Versöhnung).pdf' => 'Wie ein Fest (So ist Versöhnung)',
            'Wir sagen euch an.pdf' => 'Wir sagen euch an den lieben Advent',
            'Wo Menschen sich vergessen.pdf' => 'Da berühren sich Himmel und Erde',
        ];
        if (isset($known[$filename])) {
            return $known[$filename];
        }
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && ! str_contains($line, '')) {
                return preg_replace('/\s+/u', ' ', $line) ?: pathinfo($filename, PATHINFO_FILENAME);
            }
        }

        return pathinfo($filename, PATHINFO_FILENAME);
    }

    private function firstCopy(string $text, string $title): string
    {
        $text = str_replace("\r", '', $text);
        $needle = preg_quote($title, '/');
        preg_match_all('/^'.$needle.'\s*$/mu', $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] ?? [] as [$heading, $offset]) {
            if ($offset > 120) {
                $text = substr($text, 0, $offset);
                break;
            }
        }
        $text = preg_replace('/^'.$needle.'\s*$/mu', '', $text, 1) ?? $text;

        return $text;
    }

    private function section(string $text, string $start, ?string $end): string
    {
        $text = $this->firstCopy($text, $start);
        $startPosition = mb_stripos($text, $start);
        if ($startPosition !== false) {
            $text = mb_substr($text, $startPosition + mb_strlen($start));
        }
        if ($end !== null) {
            $endPosition = mb_stripos($text, $end);
            if ($endPosition !== false) {
                $text = mb_substr($text, 0, $endPosition);
            }
        }

        return $text;
    }

    private function pageSection(string $text, int $page): string
    {
        $pages = preg_split('/\f/u', $text) ?: [];

        return $this->firstCopy($pages[$page] ?? '', $page === 0 ? 'Lass mein Volk doch zieh’n' : 'Let my people go');
    }

    private function importEntry(array $entry, string $source, int $userId): void
    {
        $title = trim($entry['title']);
        $parts = $this->parts($entry['text']);
        if ($parts === []) {
            $this->warn('Kein Liedtext erkannt: '.$title);

            return;
        }
        $metadata = self::METADATA[$title] ?? [];
        $song = Song::firstOrCreate(['user_id' => $userId, 'title' => $title], [
            'author' => $metadata['author'] ?? null,
            'composer' => $metadata['composer'] ?? null,
            'copyright_notice' => $metadata['copyright'] ?? 'Urheberangaben noch nicht eindeutig recherchiert',
            'notes' => 'Import aus '.basename($source).'; Rechteangaben bitte vor Veröffentlichung prüfen.',
        ]);
        $version = $song->versions()->firstOrCreate(['name' => 'Standardfassung', 'language' => $entry['language']], ['lyrics' => collect($parts)->pluck('content')->implode("\n\n")]);
        if ($version->wasRecentlyCreated) {
            foreach ($parts as $position => $part) {
                $version->parts()->create($part + ['position' => $position + 1]);
            }
            $this->storeSheet($version, $source);
            $this->extractIllustration($version, $source);
        }
        $this->line('Importiert: '.$title);
    }

    /** @return list<array{content: string, is_refrain: bool, is_numbered: bool, number: ?int, is_repeated: bool, repeat_count: ?int}> */
    private function parts(string $text): array
    {
        $text = preg_replace('/\s*\f\s*/u', "\n", str_replace("\r", '', $text)) ?? $text;
        $lines = array_values(array_filter(array_map(fn (string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? $line), preg_split('/\R/u', $text) ?: []), fn (string $line): bool => $line !== ''));
        $lines = array_values(array_filter($lines, fn (string $line): bool => ! preg_match('/^(?:Abraham, Abraham|Bewahre uns Gott|Christ ist erstanden|Du bist du|[A-ZÄÖÜ][^.!?]{0,80})$/u', $line) || str_ends_with($line, ':')));
        $parts = [];
        $current = null;
        foreach ($lines as $line) {
            if (preg_match('/^(\d+)\.?\s*(.*)$/u', $line, $match)) {
                if ($current !== null) {
                    $parts[] = $current;
                }
                $current = ['content' => trim($match[2]), 'is_refrain' => false, 'is_numbered' => true, 'number' => (int) $match[1], 'is_repeated' => false, 'repeat_count' => null];

                continue;
            }
            if ($current === null) {
                $current = ['content' => $line, 'is_refrain' => false, 'is_numbered' => false, 'number' => null, 'is_repeated' => false, 'repeat_count' => null];
            } else {
                $current['content'] .= "\n".$line;
            }
        }
        if ($current !== null) {
            $parts[] = $current;
        }

        return array_values(array_filter($parts, fn (array $part): bool => mb_strlen(trim($part['content'])) > 2));
    }

    private function storeSheet($version, string $source): void
    {
        $path = 'songs/sheets/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, file_get_contents($source));
        $version->sheet()->create(['original_name' => basename($source), 'storage_path' => $path, 'mime_type' => 'application/pdf', 'size' => Storage::disk('local')->size($path)]);
    }

    private function extractIllustration($version, string $source): void
    {
        $temporary = storage_path('app/temporary/song-import-'.Str::uuid());
        File::ensureDirectoryExists($temporary);
        try {
            $prefix = $temporary.'/image';
            (new Process(['pdfimages', '-png', '-f', '1', '-l', '1', $source, $prefix]))->mustRun();
            foreach (glob($prefix.'-*.png') ?: [] as $input) {
                $dimensions = @getimagesize($input);
                if (! $dimensions || $dimensions[0] > 1800 || $dimensions[1] > 2600 || $dimensions[0] < 80 || $dimensions[1] < 80) {
                    continue;
                }
                $output = $temporary.'/transparent-'.basename($input);
                (new Process(['magick', $input, '-alpha', 'on', '-colorspace', 'Gray', '-threshold', '82%', '-transparent', 'white', $output]))->mustRun();
                $path = 'songs/images/'.Str::uuid().'.png';
                Storage::disk('local')->put($path, file_get_contents($output));
                $image = $version->images()->create([
                    'original_name' => basename($source, '.pdf').'.png',
                    'copyrights' => 'Aus dem PDF extrahierte Grafik; Quelle: '.basename($source),
                    'storage_path' => $path,
                    'mime_type' => 'image/png',
                    'size' => Storage::disk('local')->size($path),
                ]);
                $layout = $version->layout_data ?? [];
                $layout['images'] = array_merge($layout['images'] ?? [], [[
                    'id' => $image->id, 'x' => 320, 'y' => 40, 'width' => 80, 'height' => 80,
                    'rotation' => 0, 'flipX' => false, 'flipY' => false,
                    'credits' => 'Aus dem PDF extrahierte Grafik',
                ]]);
                $version->update(['layout_data' => $layout]);
                break;
            }
        } catch (\Throwable) {
            // Image extraction is best effort; the original PDF remains available.
        } finally {
            File::deleteDirectory($temporary);
        }
    }
}
