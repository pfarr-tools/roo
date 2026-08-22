<?php

use App\Documents\AssessmentDocument;
use App\Documents\Document;
use App\Documents\DocumentOutputFormat;
use App\Documents\DocumentTemplate;
use App\Documents\DocumentTemplateRegistry;
use App\Services\PhpOfficeDocumentRenderer;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

uses(TestCase::class);

it('registriert mehrere Templates und rendert DOCX sowie ODT', function () {
    $document = new class('Lernstandserhebung') extends Document
    {
        public function templateKey(): string
        {
            return 'assessment.default';
        }
    };
    $template = new class implements DocumentTemplate
    {
        public function key(): string
        {
            return 'assessment.default';
        }

        public function render(Document $document): PhpWord
        {
            $word = new PhpWord;
            $word->addSection()->addText($document->title);

            return $word;
        }
    };
    $registry = new DocumentTemplateRegistry([$template]);
    $renderer = new PhpOfficeDocumentRenderer($registry);

    expect($renderer->render($document, DocumentOutputFormat::DOCX))->toStartWith('PK')
        ->and($renderer->render($document, DocumentOutputFormat::ODT))->toStartWith('PK');
});

it('weist auf nicht registrierte Templates hin', function () {
    $document = new class('Lernstandserhebung') extends Document
    {
        public function templateKey(): string
        {
            return 'assessment.missing';
        }
    };

    expect(fn () => (new PhpOfficeDocumentRenderer(new DocumentTemplateRegistry))->render($document, DocumentOutputFormat::DOCX))
        ->toThrow(InvalidArgumentException::class, 'assessment.missing');
});

it('rendert das Grundschultemplate mit Lineatur und Ankreuzaufgabe', function () {
    $document = new AssessmentDocument('LSE Lesen', [
        ['title' => 'Richtige Sätze ankreuzen', 'task_type' => 'checkbox', 'max_points' => 2, 'solution' => 'Nur der erste Satz.', 'content' => ['prompt' => 'Kreuze die richtigen Sätze an.', 'options' => [['text' => 'Das ist richtig.'], ['text' => 'Das ist falsch.']]]],
        ['title' => 'Schreibe einen Satz.', 'task_type' => 'free_text', 'max_points' => 3, 'content' => ['prompt' => 'Schreibe einen Satz.', 'lines' => 3, 'lineated' => true]],
    ], '2', [
        'author' => 'Christoph Muster',
        'assessment_id' => '42',
        'level' => 'M',
        'roo_version' => '0.1.0',
        'year' => 2026,
        'school' => 'Grundschule Musterstadt',
        'school_year' => '2026/27',
        'group' => '4a',
        'footer_title' => 'LSE Lesen (M)',
        'date' => '22.08.2026',
    ]);

    $contents = app(PhpOfficeDocumentRenderer::class)->render($document, DocumentOutputFormat::ODT);
    $renderer = app(PhpOfficeDocumentRenderer::class);
    $pageMarkerMethod = new ReflectionMethod($renderer, 'pageMarker');
    $taskMarkerPayloadMethod = new ReflectionMethod($renderer, 'taskMarkerPayload');
    expect($pageMarkerMethod->invoke($renderer, ['assessment_id' => '42', 'level' => 'M']))->toBe('ROO1|A=42|L=M|K=PAGE')
        ->and($taskMarkerPayloadMethod->invoke($renderer, '7', 'START'))->toBe('ROO1|T=7|K=START');
    $path = tempnam(sys_get_temp_dir(), 'roo-test-odt-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    $archive->open($path);
    $styles = $archive->getFromName('styles.xml');
    $content = $archive->getFromName('content.xml');
    $manifest = $archive->getFromName('META-INF/manifest.xml');
    $icon = $archive->getFromName('Pictures/roo-icon.png');
    $marker = $archive->getFromName('Pictures/assessment-page-marker.png');
    $taskStartMarker = $archive->getFromName('Pictures/assessment-task-1-start.png');
    $taskEndMarker = $archive->getFromName('Pictures/assessment-task-1-end.png');
    $secondTaskStartMarker = $archive->getFromName('Pictures/assessment-task-2-start.png');
    $secondTaskEndMarker = $archive->getFromName('Pictures/assessment-task-2-end.png');
    $archive->close();
    unlink($path);

    expect($contents)->toStartWith('PK')
        ->and($styles)->toContain('fo:border="0.05cm solid #000000"')
        ->and($styles)->toContain('fo:padding="0.4cm"')
        ->and($content)->not->toContain('Lösungsvorschläge')
        ->and($content)->not->toContain('Nur der erste Satz.')
        ->and($content)->not->toContain('<text:tracked-changes/>')
        ->and($content)->toMatch('/assessmentTaskMarkerEND2.*?<\/text:p>\s*<text:p[^>]*>(?:<text:span[^>]*\/>)*<\/text:p>.*?<\/text:section>/s')
        ->and($styles)->toContain('Grundschule Musterstadt')
        ->and($styles)->toContain('2026/27')
        ->and($styles)->toContain('4a')
        ->and($styles)->toContain('LSE Lesen (M)')
        ->and($styles)->toContain('text:page-number')
        ->and($styles)->toContain('assessmentFooterParagraph')
        ->and($styles)->toContain('assessmentFooterText')
        ->and($styles)->toContain('assessmentRooMark')
        ->and($styles)->toMatch('/<style:footer>.*<text:p[^>]*>.*assessmentRooMark.*<\/text:p><\/style:footer>/s')
        ->and($styles)->toContain('draw:transform="rotate (1.5707963267949) translate (1.00008333333333cm 0.252236111111111cm)"')
        ->and($styles)->toContain('assessmentRooMarkImage')
        ->and($styles)->toContain('assessmentPageMarker')
        ->and(substr_count((string) $styles, 'draw:name="assessmentPageMarker"'))->toBe(1)
        ->and($styles)->toContain('svg:x="0.5cm"')
        ->and($styles)->toContain('svg:y="1cm"')
        ->and($styles)->toContain('Pictures/assessment-page-marker.png')
        ->and($styles)->toContain('text:anchor-type="char"')
        ->and($styles)->toContain('draw:mime-type="image/png"')
        ->and($styles)->toContain('ROO 0.1.0')
        ->and($styles)->toContain('text:style-name="assessmentRooMarkText">ROO 0.1.0')
        ->and($styles)->toContain('Pictures/roo-icon.png')
        ->and($manifest)->toContain('Pictures/roo-icon.png')
        ->and($manifest)->toContain('Pictures/assessment-page-marker.png')
        ->and($manifest)->toContain('Pictures/assessment-task-1-start.png')
        ->and($manifest)->toContain('Pictures/assessment-task-1-end.png')
        ->and($manifest)->toContain('Pictures/assessment-task-2-start.png')
        ->and($manifest)->toContain('Pictures/assessment-task-2-end.png')
        ->and($icon)->not->toBeFalse()
        ->and($marker)->not->toBeFalse()
        ->and($taskStartMarker)->not->toBeFalse()
        ->and($taskEndMarker)->not->toBeFalse()
        ->and($secondTaskStartMarker)->not->toBeFalse()
        ->and($secondTaskEndMarker)->not->toBeFalse()
        ->and($content)->toContain('assessmentTaskMarkerSTART1')
        ->and($content)->toContain('assessmentTaskMarkerEND1')
        ->and($content)->toContain('<style:style style:name="assessmentTaskMarkerFrame" style:family="graphic">')
        ->and($content)->toContain('style:horizontal-pos="from-left" style:horizontal-rel="paragraph" svg:x="-1.9cm" svg:y="0cm"')
        ->and($content)->toContain('style:horizontal-pos="from-left" style:horizontal-rel="paragraph" svg:x="-1.9cm" svg:y="-0.199cm"')
        ->and($content)->toContain('assessmentTaskMarkerSTART2')
        ->and($content)->toContain('assessmentTaskMarkerEND2')
        ->and($content)->not->toContain('ROO_TASK_START_1')
        ->and($content)->not->toContain('ROO_TASK_END_1')
        ->and($styles)->toContain('fo:font-size="6pt"')
        ->and($styles)->toContain('fo:color="#808080"')
        ->and($styles)->toContain('© 2026 Christoph Muster')
        ->and($styles)->toContain('22.08.2026')
        ->and($styles)->toContain('Seite')
        ->and($styles)->toContain('style:name="FirstPage"')
        ->and($styles)->toContain('style:next-style-name="Standard1"')
        ->and($styles)->toContain('Name:')
        ->and($styles)->not->toContain('Name: ____________________')
        ->and($styles)->toContain('assessmentHeaderLine')
        ->and($styles)->toContain('svg:stroke-width="0.049cm"')
        ->and($styles)->toContain('assessmentNameCell')
        ->and($styles)->toContain('fo:padding-left="0.5cm"')
        ->and($styles)->toContain('draw:line')
        ->and($styles)->toContain('text:anchor-type="paragraph"')
        ->and($styles)->toContain('svg:x1="-0.45cm"')
        ->and($styles)->toContain('fo:margin-top="0.39375in"')
        ->and($styles)->toContain('fo:margin-bottom="0.39375in"')
        ->and($styles)->toContain('fo:margin-left="0.7875in"')
        ->and($styles)->toContain('fo:margin-right="0.39375in"')
        ->and(substr_count((string) $styles, 'Name:'))->toBe(1)
        ->and($content)->toContain('style:master-page-name="FirstPage"');
});
