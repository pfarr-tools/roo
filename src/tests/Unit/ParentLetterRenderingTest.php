<?php

use App\Documents\DocumentOutputFormat;
use App\Documents\ParentLetterDocument;
use App\Services\PhpOfficeDocumentRenderer;
use App\Services\QrCodeRenderer;
use Tests\TestCase;

uses(TestCase::class);

function parentLetterArchiveText(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'roo-parent-letter-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    $archive->open($path);
    $text = '';
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $name = (string) $archive->getNameIndex($index);
        if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
            $text .= (string) $archive->getFromIndex($index);
        }
    }
    $archive->close();
    unlink($path);

    return $text;
}

it('renders the parent letter in both formats with the agreed fonts and content', function () {
    $document = new ParentLetterDocument(
        title: 'Elternbrief: Wasser des Lebens',
        group: '4a',
        school: 'Öffentliche Schule',
        creator: 'Lehrkraft Beispiel',
        introduction: "Erster Absatz.\n\nZweiter Absatz.",
        contentCompetencies: ['Die Kinder beschreiben Wasser als Lebensgrundlage.'],
        processCompetencies: ['Die Kinder tauschen sich über ihre Beobachtungen aus.'],
        scheduledLessons: [['date' => '10.09.2026', 'time' => '08:00', 'title' => 'Wasserbilder']],
        publicUrl: 'https://example.test/oeffentlich/1?signature=abc',
        qrPng: app(QrCodeRenderer::class)->png('https://example.test/oeffentlich/1?signature=abc'),
    );
    $renderer = app(PhpOfficeDocumentRenderer::class);

    foreach ([DocumentOutputFormat::DOCX, DocumentOutputFormat::ODT] as $format) {
        $contents = $renderer->render($document, $format);
        $archiveText = parentLetterArchiveText($contents);

        $expectation = expect($contents)->toStartWith('PK')
            ->and($archiveText)->toContain('Wasser des Lebens')
            ->and($archiveText)->not->toContain('Einführung')
            ->and($archiveText)->toContain('Erster Absatz.')
            ->and($archiveText)->toContain('Zweiter Absatz.')
            ->and($archiveText)->toContain('In dieser Unterrichtseinheit arbeiten wir an folgenden Kompetenzen aus dem Lehrplan.')
            ->and($archiveText)->toContain('Dabei üben wir folgende praktischen Kompetenzen ein.')
            ->and($archiveText)->toContain($format === DocumentOutputFormat::DOCX ? 'w:left="720"' : 'fo:margin-left="0.5in"')
            ->and($archiveText)->toContain('Die Kinder beschreiben Wasser als Lebensgrundlage.')
            ->and($archiveText)->toContain('Die Kinder tauschen sich über ihre Beobachtungen aus.')
            ->and($archiveText)->toContain('Datum')
            ->and($archiveText)->toContain('Zeit')
            ->and($archiveText)->toContain('Thema')
            ->and($archiveText)->toContain('Auf der Online-Seite zur Unterrichtseinheit')
            ->and($archiveText)->toContain('Gerne dürfen Sie sich bei Fragen an mich wenden.')
            ->and($archiveText)->toContain('Herzliche Grüße,')
            ->and($archiveText)->toContain('Lehrkraft Beispiel')
            ->and($archiveText)->toContain('Comic Neue')
            ->and($archiveText)->toContain('Atkinson Hyperlegible Next');

        if ($format === DocumentOutputFormat::DOCX) {
            $expectation->and($archiveText)->toContain('w:sz w:val="28"')
                ->and($archiveText)->toContain('w:sz w:val="22"');
        } else {
            $expectation->and($archiveText)->toContain('fo:font-size="14pt"')
                ->and($archiveText)->toContain('fo:font-size="11pt"')
                ->and($archiveText)->toContain('<text:list')
                ->and($archiveText)->toContain('parentLetterCompetencyList');
        }
    }
});

it('generates a QR PNG for the permanent public URL', function () {
    $png = app(QrCodeRenderer::class)->png('https://example.test/oeffentlich/1?signature=abc');

    expect($png)->toStartWith("\x89PNG\r\n\x1a\n");
});
