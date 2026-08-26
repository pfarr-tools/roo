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

it('rendert Bildzuordnung als dreispaltige Tabelle mit verbundener Lösungsspalte', function () {
    $image = base_path('resources/images/branding/roo-icon.png');
    $document = new AssessmentDocument('LSE Bildzuordnung', [
        [
            'title' => 'Ordne zu',
            'task_type' => 'image_matching',
            'max_points' => 3,
            'content' => [
                'prompt' => 'Ordne die Bilder den Lösungen zu.',
                'image_width_cm' => 4,
                'images' => [
                    ['path' => $image, 'answer' => 'Erste Lösung', 'copyright' => 'Ada Beispiel'],
                    ['path' => $image, 'answer' => 'Zweite Lösung', 'copyright' => 'Ben Beispiel'],
                    ['path' => $image, 'answer' => 'Dritte Lösung', 'copyright' => 'Ada Beispiel'],
                ],
            ],
        ],
    ], '2');

    $contents = app(PhpOfficeDocumentRenderer::class)->render($document, DocumentOutputFormat::ODT);
    $path = tempnam(sys_get_temp_dir(), 'roo-test-image-matching-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    $archive->open($path);
    $contentXml = $archive->getFromName('content.xml');
    $stylesXml = $archive->getFromName('styles.xml');
    $archive->close();
    unlink($path);

    expect($contents)->toStartWith('PK')
        ->and($contentXml)->toContain('Erste Lösung')
        ->and($contentXml)->toContain('Zweite Lösung')
        ->and($contentXml)->toContain('Dritte Lösung')
        ->and($contentXml)->toContain('table:number-rows-spanned="2"')
        ->and($contentXml)->toContain('Bilder: Ada Beispiel; Ben Beispiel')
        ->and($stylesXml)->toContain('style:name="imageMatchingSolution"')
        ->and($stylesXml)->toContain('fo:margin-bottom="12pt"')
        ->and($stylesXml)->toContain('fo:text-align="center"')
        ->and($contentXml)->toContain('table:style-name="assessmentImageCell"')
        ->and($contentXml)->toContain('table:style-name="assessmentImageMatchingMiddleCell"')
        ->and($contentXml)->not->toContain('table:style-name="RooRulingZoneRow10"')
        ->and($stylesXml)->toContain('fo:border="0.05cm solid #000000"')
        ->and($contentXml)->toContain('fo:border="0.06pt solid #000000"');
});

it('rendert eine Tabelle mit Teilaufgaben mit optionalen Lösungen und Lineatur', function () {
    $document = new AssessmentDocument('LSE Teilaufgaben', [[
        'title' => 'Teilaufgaben',
        'task_type' => 'subtask_table',
        'max_points' => 3,
        'content' => [
            'prompt' => 'Bearbeite die Teilaufgaben.',
            'show_solutions' => true,
            'lineated' => true,
            'subtasks' => [
                ['key' => 'a', 'label' => 'Nenne ein Beispiel.', 'solution' => 'Ein Beispiel', 'lines' => 2, 'points' => 1],
                ['key' => 'b', 'label' => 'Begründe.', 'solution' => '', 'lines' => 3, 'points' => null],
            ],
        ],
    ]], '4');

    $contents = app(PhpOfficeDocumentRenderer::class)->render($document, DocumentOutputFormat::ODT);
    $path = tempnam(sys_get_temp_dir(), 'roo-test-subtask-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    $archive->open($path);
    $contentXml = $archive->getFromName('content.xml');
    $stylesXml = $archive->getFromName('styles.xml');
    $archive->close();
    unlink($path);

    expect($contentXml)->toContain('Ein Beispiel')
        ->and($contentXml)->toContain('Nenne ein Beispiel.')
        ->and($contentXml)->toContain('Begründe.')
        ->and($contentXml)->toContain('table:style-name="assessmentSubtaskLabelCell"')
        ->and($contentXml)->toContain('table:style-name="assessmentSubtaskAnswerCell"')
        ->and($contentXml)->toContain('assessmentSubtaskLabelCell')
        ->and($contentXml)->toContain('assessmentSubtaskAnswerCell');
});

it('rendert eine Tabelle mit Bildern und Lösungsfeldern mit gewählter Bildspaltenbreite', function () {
    $image = base_path('resources/images/branding/roo-icon.png');
    $document = new AssessmentDocument('LSE Bildtabelle', [[
        'title' => 'Bildtabelle',
        'task_type' => 'image_answer_table',
        'max_points' => 3,
        'content' => [
            'prompt' => 'Bearbeite die Bildtabelle.',
            'image_width_cm' => 2.5,
            'show_solutions' => true,
            'lineated' => true,
            'images' => [['identifier' => 'pair-a', 'path' => $image, 'copyright' => 'Ada Beispiel']],
            'subtasks' => [
                ['image_identifier' => 'pair-a', 'solution' => 'Baum', 'lines' => 2, 'points' => 1],
            ],
        ],
    ]], '4');

    $contents = app(PhpOfficeDocumentRenderer::class)->render($document, DocumentOutputFormat::ODT);
    $path = tempnam(sys_get_temp_dir(), 'roo-test-image-table-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    $archive->open($path);
    $contentXml = $archive->getFromName('content.xml');
    $archive->close();
    unlink($path);

    expect($contentXml)->toContain('Baum')
        ->and($contentXml)->toContain('assessmentSubtaskLabelCell')
        ->and($contentXml)->toContain('assessmentSubtaskAnswerCell')
        ->and($contentXml)->toContain('2.50cm');
});

it('rendert eine Überschriften-Tabelle mit Kopfzeile, Zeilenkopf und Lineatur', function () {
    $document = new AssessmentDocument('LSE Überschriftentabelle', [[
        'title' => 'Überschriftentabelle',
        'task_type' => 'heading_table',
        'max_points' => 2,
        'content' => [
            'prompt' => 'Fülle die Tabelle aus.',
            'show_solutions' => true,
            'lineated' => true,
            'columns' => [
                ['heading' => 'Kategorie', 'solution' => ''],
                ['heading' => '', 'solution' => 'Antwort'],
            ],
            'rows' => [
                ['key' => 'r1', 'lines' => 2, 'header' => ['heading' => 'A', 'solution' => ''], 'cells' => [['heading' => '', 'solution' => 'Merkmal'], ['heading' => '', 'solution' => 'Antwort']]],
                ['key' => 'r2', 'lines' => 3, 'header' => ['heading' => 'B', 'solution' => ''], 'cells' => [['heading' => '', 'solution' => ''], ['heading' => '', 'solution' => '']]],
            ],
        ],
    ]], '4');

    $contents = app(PhpOfficeDocumentRenderer::class)->render($document, DocumentOutputFormat::ODT);
    $path = tempnam(sys_get_temp_dir(), 'roo-test-heading-table-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    $archive->open($path);
    $contentXml = $archive->getFromName('content.xml');
    $archive->close();
    unlink($path);

    expect($contentXml)->toContain('Kategorie')
        ->and($contentXml)->toContain('Merkmal')
        ->and($contentXml)->toContain('Antwort')
        ->and($contentXml)->toContain('table:table-column')
        ->and($contentXml)->toContain('fo:border-bottom');
});

it('rendert eine Zuordnungstabelle mit breiter Textspalte und Kategorien', function () {
    $document = new AssessmentDocument('LSE Zuordnungstabelle', [[
        'title' => 'Zuordnung',
        'task_type' => 'matching_table',
        'max_points' => 2,
        'content' => [
            'prompt' => 'Ordne die Aussagen zu.',
            'categories' => [
                ['id' => 'c1', 'text' => 'Wahr'],
                ['id' => 'c2', 'text' => 'Falsch'],
            ],
            'rows' => [
                ['id' => 'r1', 'text' => 'Die Aussage.', 'category_ids' => ['c1']],
            ],
        ],
    ]], '4');

    $contents = app(PhpOfficeDocumentRenderer::class)->render($document, DocumentOutputFormat::ODT);
    $path = tempnam(sys_get_temp_dir(), 'roo-test-matching-table-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    $archive->open($path);
    $contentXml = $archive->getFromName('content.xml');
    $archive->close();
    unlink($path);

    expect($contentXml)->toContain('Wahr')
        ->and($contentXml)->toContain('Falsch')
        ->and($contentXml)->toContain('Die Aussage.')
        ->and(substr_count($contentXml, 'table:table-column'))->toBeGreaterThanOrEqual(3);
});

it('rendert Bildbeschriftung mit Lösungstexten', function () {
    $image = base_path('resources/images/branding/roo-icon.png');
    $document = new AssessmentDocument('LSE Bildbeschriftung', [[
        'task_id' => 'label-1',
        'title' => 'Beschrifte das Bild',
        'task_type' => 'image_labeling',
        'max_points' => 2,
        'content' => [
            'prompt' => 'Beschrifte das Bild.',
            'image_label_width_cm' => 6,
            'show_solutions' => true,
            'image' => [
                'path' => $image,
                'labels' => [
                    ['x_percent' => 20, 'y_percent' => 25, 'solution' => 'Stamm', 'lines' => 1],
                    ['x_percent' => 80, 'y_percent' => 75, 'solution' => 'Zweig', 'lines' => 2],
                    ['x_percent' => 30, 'y_percent' => 35, 'solution' => 'Ast', 'lines' => 1],
                ],
            ],
        ],
    ]], '4/2');

    $contents = app(PhpOfficeDocumentRenderer::class)->render($document, DocumentOutputFormat::ODT);
    $path = tempnam(sys_get_temp_dir(), 'roo-test-image-labeling-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    $archive->open($path);
    $contentXml = $archive->getFromName('content.xml');
    $archive->close();
    unlink($path);

    $contentDom = new DOMDocument;
    $contentDom->loadXML($contentXml);
    $contentXPath = new DOMXPath($contentDom);
    $contentXPath->registerNamespace('draw', 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0');
    $contentXPath->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
    $connectorLine = $contentXPath->query('//draw:line[@draw:name="assessmentImageLabelingLine0"]')->item(0);
    $firstRulingLine = $contentXPath->query('//draw:line[@draw:name="assessmentImageLabelingRulingLine0_9"]')->item(0);
    $secondConnectorLine = $contentXPath->query('//draw:line[@draw:name="assessmentImageLabelingLine1"]')->item(0);
    $secondFirstRulingLine = $contentXPath->query('//draw:line[@draw:name="assessmentImageLabelingRulingLine1_9"]')->item(0);
    $thirdFirstRulingLine = $contentXPath->query('//draw:line[@draw:name="assessmentImageLabelingRulingLine2_6"]')->item(0);
    $imageFrame = $contentXPath->query('//draw:frame[@draw:style-name="assessmentImageLabelingImageFrame"]')->item(0);
    $canvasHeight = (float) str_replace('cm', '', $contentXPath->evaluate('string(//style:style[@style:name="assessmentImageLabelingCanvas_label_1"]/style:paragraph-properties/@fo:min-height)'));
    $imageHeight = (float) str_replace('cm', '', $imageFrame?->getAttribute('svg:height') ?? '0');
    $image = $imageFrame?->getElementsByTagNameNS('urn:oasis:names:tc:opendocument:xmlns:drawing:1.0', 'image')->item(0);

    expect($contentXml)->toContain('Lösungstexte')
        ->and($contentXml)->toContain('Stamm · Zweig · Ast')
        ->and($contentXml)->toContain('ROO_IMAGE_LABELING_label-1')
        ->and($contentXml)->toContain('assessmentImageLabelingCanvas_label_1')
        ->and($contentXml)->toContain('assessmentImageLabelingLine0')
        ->and($contentXml)->toContain('assessmentImageLabelingLine1')
        ->and($contentXml)->not->toContain('draw:text-box')
        ->and($contentXml)->toContain('assessmentImageLabelingRulingLine0_9')
        ->and($contentXml)->toContain('assessmentImageLabelingRulingLine1_9')
        ->and(substr_count($contentXml, 'assessmentImageLabelingRulingLine'))->toBe(40)
        ->and($contentXml)->toContain('assessmentImageLabelingRulingStyle0_0')
        ->and($contentXml)->toContain('svg:stroke-color="#808080"')
        ->and($contentXml)->toContain('svg:stroke-color="#000000"')
        ->and($contentXml)->toContain('svg:stroke-width="0.5pt"')
        ->and((float) str_replace('cm', '', $firstRulingLine->getAttribute('svg:x1')))->toBe(0.0)
        ->and((float) str_replace('cm', '', $firstRulingLine->getAttribute('svg:x2')))->toBe(5.4)
        ->and((float) str_replace('cm', '', $connectorLine->getAttribute('svg:y1')))->toBe((float) str_replace('cm', '', $firstRulingLine->getAttribute('svg:y1')) - 0.5)
        ->and((float) str_replace('cm', '', $connectorLine->getAttribute('svg:y2')))->toEqualWithDelta($imageHeight * 0.25, 0.000001)
        ->and((float) str_replace('cm', '', $secondFirstRulingLine->getAttribute('svg:x1')))->toBe(12.1)
        ->and((float) str_replace('cm', '', $secondFirstRulingLine->getAttribute('svg:x2')))->toBe(17.1)
        ->and((float) str_replace('cm', '', $thirdFirstRulingLine->getAttribute('svg:y1')) - (float) str_replace('cm', '', $firstRulingLine->getAttribute('svg:y2')))->toEqualWithDelta(0.5, 0.000001)
        ->and((float) str_replace('cm', '', $secondConnectorLine->getAttribute('svg:y1')))->toBe((float) str_replace('cm', '', $secondFirstRulingLine->getAttribute('svg:y1')) - 0.5)
        ->and((float) str_replace('cm', '', $secondConnectorLine->getAttribute('svg:y2')))->toEqualWithDelta($imageHeight * 0.75, 0.000001)
        ->and($canvasHeight)->toBeGreaterThanOrEqual($imageHeight + 0.5)
        ->and($imageFrame->getAttribute('svg:width'))->toBe('17.5cm')
        ->and($image->getAttribute('xlink:href'))->toContain('assessment-image-labeling-padded');
});

it('rendert einseitige Bildbeschriftungs-Anordnungen', function () {
    $image = base_path('resources/images/branding/roo-icon.png');
    $renderCoordinates = function (string $layout, float $xPercent) use ($image): array {
        $document = new AssessmentDocument('Einseitige Bildbeschriftung', [[
            'task_id' => 'label-1',
            'title' => 'Beschrifte das Bild',
            'task_type' => 'image_labeling',
            'max_points' => 1,
            'content' => [
                'prompt' => 'Beschrifte das Bild.',
                'image_label_width_cm' => 6,
                'image_label_layout' => $layout,
                'show_solutions' => true,
                'image' => [
                    'path' => $image,
                    'labels' => [['x_percent' => $xPercent, 'y_percent' => 50, 'solution' => 'Lösung', 'lines' => 1]],
                ],
            ],
        ]], '4/2');

        $contents = app(PhpOfficeDocumentRenderer::class)->render($document, DocumentOutputFormat::ODT);
        $path = tempnam(sys_get_temp_dir(), 'roo-test-image-labeling-layout-');
        file_put_contents($path, $contents);
        $archive = new ZipArchive;
        $archive->open($path);
        $contentXml = $archive->getFromName('content.xml');
        $archive->close();
        unlink($path);

        $dom = new DOMDocument;
        $dom->loadXML($contentXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('draw', 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0');
        $connector = $xpath->query('//draw:line[@draw:name="assessmentImageLabelingLine0"]')->item(0);

        return [
            'connectorX1' => (float) str_replace('cm', '', $connector->getAttribute('svg:x1')),
            'connectorX2' => (float) str_replace('cm', '', $connector->getAttribute('svg:x2')),
        ];
    };

    expect($renderCoordinates('left', 20))->toEqual([
        'connectorX1' => 6.35,
        'connectorX2' => 1.2,
    ])->and($renderCoordinates('right', 80))->toEqual([
        'connectorX1' => 11.15,
        'connectorX2' => 16.3,
    ]);
});
