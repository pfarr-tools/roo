<?php

namespace App\Services;

use App\Documents\Document;
use App\Documents\DocumentOutputFormat;
use App\Documents\DocumentTemplateRegistry;
use App\Documents\AssessmentDocument;
use Com\Tecnick\Barcode\Barcode;
use PhpOffice\PhpWord\IOFactory;
use PfarrTools\RooRuling\PhpWord\OdtRulingPatcher;

class PhpOfficeDocumentRenderer
{
    public function __construct(private readonly DocumentTemplateRegistry $templates) {}

    public function render(Document $document, DocumentOutputFormat $format): string
    {
        $phpWord = $this->templates->get($document->templateKey())->render($document);
        $writer = IOFactory::createWriter($phpWord, $format->writerName());

        ob_start();
        try {
            $writer->save('php://output');

            $contents = (string) ob_get_contents();

            return $format === DocumentOutputFormat::ODT
                ? $this->addOdtPageFrame($this->patchOdtVerticalMerges($this->patchOdtRulings($contents, $document), $document), $document)
                : $contents;
        } finally {
            ob_end_clean();
        }
    }

    public function renderToFile(Document $document, DocumentOutputFormat $format, string $path): void
    {
        $phpWord = $this->templates->get($document->templateKey())->render($document);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-document-');
        if ($temporaryPath === false) {
            IOFactory::createWriter($phpWord, $format->writerName())->save($path);

            return;
        }

        try {
            IOFactory::createWriter($phpWord, $format->writerName())->save($temporaryPath);
            $contents = (string) file_get_contents($temporaryPath);
            file_put_contents($path, $format === DocumentOutputFormat::ODT ? $this->addOdtPageFrame($this->patchOdtVerticalMerges($this->patchOdtRulings($contents, $document), $document), $document) : $contents);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function patchOdtRulings(string $contents, Document $document): string
    {
        if (! method_exists($document, 'odtRulings')) {
            return $contents;
        }

        $plan = $document->odtRulings();
        if ($plan['rulings'] === []) {
            return $contents;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-ruling-');
        if ($temporaryPath === false) {
            return $contents;
        }

        try {
            file_put_contents($temporaryPath, $contents);
            OdtRulingPatcher::patchTables($temporaryPath, $plan['rulings'], $plan['counts']);

            return (string) file_get_contents($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function patchOdtVerticalMerges(string $contents, Document $document): string
    {
        if (! $document instanceof AssessmentDocument) {
            return $contents;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-odt-merge-');
        if ($temporaryPath === false) {
            return $contents;
        }

        try {
            file_put_contents($temporaryPath, $contents);
            $archive = new \ZipArchive;
            if ($archive->open($temporaryPath) !== true) {
                return $contents;
            }
            $content = $archive->getFromName('content.xml');
            if (! is_string($content)) {
                $archive->close();

                return $contents;
            }

            $content = str_replace('draw:style-name="fr1"', 'draw:style-name="assessmentImageFrame"', $content);
            $content = str_replace(
                '</office:automatic-styles>',
                '<style:style style:name="assessmentImageFrame" style:family="graphic"><style:graphic-properties fo:border="0.06pt solid #000000"/></style:style><style:style style:name="assessmentImageCell" style:family="table-cell"><style:table-cell-properties fo:padding-bottom="0.2cm"/></style:style><style:style style:name="assessmentImageMatchingMiddleCell" style:family="table-cell"><style:table-cell-properties style:vertical-align="middle"/></style:style></office:automatic-styles>',
                $content,
            );

            $dom = new \DOMDocument;
            $dom->preserveWhiteSpace = true;
            if (! $dom->loadXML($content)) {
                $archive->close();

                return $contents;
            }
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
            $xpath->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
            $xpath->registerNamespace('fo', 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0');
            foreach ($xpath->query('//style:style[@style:name="fr1"]/style:graphic-properties') as $graphicProperties) {
                $graphicProperties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:border', '0.06pt solid #000000');
            }
            foreach ($xpath->query('//table:table') as $table) {
                $rows = $this->directChildren($table, 'table-row');
                $columns = $this->directChildren($table, 'table-column');
                if (count($columns) !== 3 || count($rows) < 2) {
                    continue;
                }
                $firstCells = $this->directChildren($rows[0], 'table-cell');
                if (count($firstCells) !== 3 || count($this->directChildren($firstCells[0], 'table')) === 0 || count($this->directChildren($firstCells[2], 'table')) === 0 || trim($firstCells[1]->textContent) === '') {
                    continue;
                }

                foreach ($rows as $row) {
                    $row->removeAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'style-name');
                    $cells = $this->directChildren($row, 'table-cell');
                    foreach ($cells as $cell) {
                        $cell->removeAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'style-name');
                    }
                    $cells[0]->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'table:style-name', 'assessmentImageCell');
                    $cells[2]->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'table:style-name', 'assessmentImageCell');
                }

                $firstCells[1]->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'table:style-name', 'assessmentImageMatchingMiddleCell');
                $firstCells[1]->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'table:number-rows-spanned', (string) count($rows));
                for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
                    $cells = $this->directChildren($rows[$rowIndex], 'table-cell');
                    if (count($cells) !== 3) {
                        continue;
                    }
                    $covered = $dom->createElementNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'table:covered-table-cell');
                    $cells[1]->parentNode->replaceChild($covered, $cells[1]);
                }
            }

            $archive->addFromString('content.xml', $dom->saveXML());
            $archive->close();

            return (string) file_get_contents($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    /** @return list<\DOMElement> */
    private function directChildren(\DOMNode $node, string $localName): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function addOdtPageFrame(string $contents, Document $document): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-odt-');
        if ($temporaryPath === false) {
            return $contents;
        }

        try {
            file_put_contents($temporaryPath, $contents);
            $archive = new \ZipArchive;
            if ($archive->open($temporaryPath) !== true) {
                return $contents;
            }

            $styles = $archive->getFromName('styles.xml');
            if (! is_string($styles)) {
                $archive->close();

                return $contents;
            }

            $metadata = $document->metadata;
            $pageMarkerPng = $this->pageMarkerPng($this->pageMarker($metadata));
            $taskMarkers = $this->taskMarkers($document);

            $styles = preg_replace(
                '/(<style:page-layout-properties\b)([^>]*)(>)/',
                '$1$2 fo:border="0.05cm solid #000000" fo:padding="0.4cm"$3',
                $styles,
                1,
            ) ?: $styles;
            $styles = preg_replace_callback(
                '/(<style:page-layout-properties\b[^>]*?) fo:margin-left="([^"]+)" fo:margin-right="([^"]+)"/',
                static fn (array $matches): string => $matches[1].' fo:margin-left="'.$matches[3].'" fo:margin-right="'.$matches[2].'"',
                $styles,
                1,
            ) ?: $styles;
            $styles = preg_replace_callback(
                '/(<style:page-layout-properties\b[^>]*>)/',
                static function (array $matches): string {
                    $properties = preg_replace('/fo:margin-top="[^"]+"/', 'fo:margin-top="0.39375in"', $matches[1]) ?: $matches[1];
                    $properties = preg_replace('/fo:margin-bottom="[^"]+"/', 'fo:margin-bottom="0.39375in"', $properties) ?: $properties;

                    return $properties;
                },
                $styles,
                1,
            ) ?: $styles;
            $styles = str_replace(
                '</office:automatic-styles>',
                '<style:style style:name="assessmentFooterParagraph" style:family="paragraph"><style:paragraph-properties fo:text-align="end"/></style:style><style:style style:name="assessmentFooterText" style:family="text"><style:text-properties style:font-name="Atkinson Hyperlegible Next" fo:font-size="6pt" fo:color="#808080"/></style:style><style:style style:name="assessmentRooMarkParagraph" style:family="paragraph"><style:paragraph-properties style:writing-mode="lr-tb"/></style:style><style:style style:name="assessmentRooMarkText" style:family="text"><style:text-properties style:font-name="Atkinson Hyperlegible Next" fo:font-size="6pt" fo:color="#808080" fo:font-weight="normal"/></style:style><style:style style:name="assessmentRooMarkFrame" style:family="graphic"><style:graphic-properties draw:stroke="none" draw:fill="none" style:run-through="background" style:wrap="run-through" style:vertical-pos="bottom" style:vertical-rel="paragraph-content" style:horizontal-pos="from-left" style:horizontal-rel="page"/></style:style><style:style style:name="assessmentRooMarkImage" style:family="graphic"><style:graphic-properties draw:stroke="none" draw:fill="none"/></style:style><style:style style:name="assessmentPageMarkerFrame" style:family="graphic"><style:graphic-properties draw:stroke="none" draw:fill="none" style:run-through="foreground" style:wrap="run-through" style:vertical-pos="from-top" style:vertical-rel="page" style:horizontal-pos="from-left" style:horizontal-rel="page"/></style:style><style:style style:name="assessmentTaskMarkerFrame" style:family="graphic"><style:graphic-properties draw:stroke="none" draw:fill="none" style:run-through="foreground" style:wrap="run-through" style:vertical-pos="from-top" style:vertical-rel="paragraph" style:horizontal-pos="from-left" style:horizontal-rel="paragraph"/></style:style><style:style style:name="assessmentHeaderLine" style:family="graphic"><style:graphic-properties svg:stroke-width="0.049cm" svg:stroke-color="#000000" draw:fill-color="#000000" style:run-through="foreground" style:wrap="run-through" style:vertical-pos="from-top" style:vertical-rel="paragraph" style:horizontal-pos="from-left" style:horizontal-rel="paragraph"/></style:style><style:style style:name="assessmentNameCell" style:family="table-cell"><style:table-cell-properties fo:padding-left="0.5cm"/></style:style></office:automatic-styles>',
                $styles,
            );
            $styles = preg_replace_callback(
                '/<style:footer>(.*?)<\/style:footer>/s',
                static function (array $matches) use ($metadata): string {
                    $footer = str_replace('text:style-name="Normal"', 'text:style-name="assessmentFooterParagraph"', $matches[1]);
                    $footer = preg_replace('/<text:span(?![^>]*text:style-name)/', '<text:span text:style-name="assessmentFooterText"', $footer) ?: $footer;
                    $version = htmlspecialchars((string) ($metadata['roo_version'] ?? config('app.version', '0.1.0')), ENT_XML1);
                    $frame = '<draw:frame text:anchor-type="paragraph" draw:z-index="1" draw:name="assessmentRooMark" draw:style-name="assessmentRooMarkFrame" draw:text-style-name="assessmentRooMarkParagraph" svg:width="4cm" svg:height="0.6cm" draw:transform="rotate (1.5707963267949) translate (1.00008333333333cm 0.252236111111111cm)"><draw:text-box><text:p><text:span text:style-name="assessmentRooMarkText">ROO '.$version.'</text:span></text:p></draw:text-box></draw:frame><draw:frame text:anchor-type="char" draw:style-name="assessmentRooMarkImage" draw:name="assessmentRooIcon" svg:x="-1.466cm" svg:y="0.333cm" svg:width="0.31cm" svg:height="0.265cm" draw:z-index="2" draw:transform="translate (1.311cm -0.4655cm) rotate (1.5707963267949) translate (-1.311cm 0.4655cm)"><draw:image xlink:href="Pictures/roo-icon.png" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad" draw:mime-type="image/png"/></draw:frame>';
                    $lastParagraphEnd = strrpos($footer, '</text:p>');
                    if ($lastParagraphEnd !== false) {
                        $footer = substr_replace($footer, $frame, $lastParagraphEnd, 0);
                    }

                    return '<style:footer>'.$footer.'</style:footer>';
                },
                $styles,
            ) ?: $styles;
            $styles = preg_replace_callback(
                '/<style:header>(.*?)<\/style:header>/s',
                static function (array $matches): string {
                    $line = '<draw:line text:anchor-type="paragraph" draw:z-index="0" draw:name="assessmentHeaderLineObject" draw:style-name="assessmentHeaderLine" svg:x1="-0.45cm" svg:y1="1.222cm" svg:x2="17.55cm" svg:y2="1.222cm"><text:p/></draw:line>';
                    $header = preg_replace('/(<table:table-row>\s*<table:table-cell[^>]*><text:p[^>]*>)/s', '$1'.$line, $matches[1], 1) ?: $matches[1];
                    $header = preg_replace('/(<table:table-row>.*?<table:table-cell[^>]*>.*?<\/table:table-cell>\s*)(<table:table-cell)/s', '$1$2 table:style-name="assessmentNameCell"', $header, 1) ?: $header;

                    return '<style:header>'.$header.'</style:header>';
                },
                $styles,
            ) ?: $styles;
            $styles = $this->addOdtFirstPageHeader($styles, $pageMarkerPng);
            $archive->addFromString('styles.xml', $styles);
            $iconPath = base_path('resources/images/branding/roo-icon.png');
            if (is_file($iconPath)) {
                $archive->addFromString('Pictures/roo-icon.png', (string) file_get_contents($iconPath));
            }
            if ($pageMarkerPng !== null) {
                $archive->addFromString('Pictures/assessment-page-marker.png', $pageMarkerPng);
            }
            foreach ($taskMarkers as $marker) {
                $archive->addFromString($marker['path'], $marker['png']);
            }
            $this->addManifestEntries($archive, array_values(array_filter([
                is_file($iconPath) ? ['path' => 'Pictures/roo-icon.png', 'mediaType' => 'image/png'] : null,
                $pageMarkerPng !== null ? ['path' => 'Pictures/assessment-page-marker.png', 'mediaType' => 'image/png'] : null,
                ...array_map(static fn (array $marker): array => ['path' => $marker['path'], 'mediaType' => 'image/png'], $taskMarkers),
            ])));
            $content = $archive->getFromName('content.xml');
            if (is_string($content)) {
                $content = preg_replace(
                    '/(style:name="SB1"[^>]*style:master-page-name=")Standard1/',
                    '$1FirstPage',
                    $content,
                    1,
                ) ?: $content;
                $content = str_replace('<text:tracked-changes/>', '', $content);
                $content = $this->addOdtTaskMarkerStyle($content);
                $content = $this->injectTaskMarkers($content, $taskMarkers);
                $archive->addFromString('content.xml', $content);
            }
            $archive->close();

            return (string) file_get_contents($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function pageMarker(array $metadata): string
    {
        $payload = implode('|', [
            'ROO1',
            'A='.(string) ($metadata['assessment_id'] ?? 'unknown'),
            'L='.(string) ($metadata['level'] ?? 'standard'),
            'K=PAGE',
        ]);

        return $payload;
    }

    private function pageMarkerPng(string $payload): ?string
    {
        try {
            return (new Barcode)->getBarcodeObj(
                type: 'DATAMATRIX',
                code: $payload,
                width: -4,
                height: -4,
                color: 'black',
                padding: [2, 2, 2, 2],
            )->getPngData(false);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, array{path: string, png: string}> */
    private function taskMarkers(Document $document): array
    {
        if (! $document instanceof AssessmentDocument) {
            return [];
        }

        $markers = [];
        foreach ($document->tasks as $index => $task) {
            $taskId = (string) ($task['task_id'] ?? $index + 1);
            $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $taskId) ?: (string) ($index + 1);

            foreach (['START', 'END'] as $kind) {
                $token = 'ROO_TASK_'.$kind.'_'.$taskId;
                $payload = $this->taskMarkerPayload($taskId, $kind);
                $png = $this->pageMarkerPng($payload);
                if ($png !== null) {
                    $markers[$token] = [
                        'path' => 'Pictures/assessment-task-'.$safeId.'-'.strtolower($kind).'.png',
                        'png' => $png,
                    ];
                }
            }
        }

        return $markers;
    }

    private function taskMarkerPayload(string $taskId, string $kind): string
    {
        $payload = implode('|', ['ROO1', 'T='.$taskId, 'K='.$kind]);

        return $payload;
    }

    private function addOdtTaskMarkerStyle(string $content): string
    {
        $style = '<style:style style:name="assessmentTaskMarkerFrame" style:family="graphic"><style:graphic-properties draw:stroke="none" draw:fill="none" style:run-through="foreground" style:wrap="run-through" style:number-wrapped-paragraphs="no-limit" style:vertical-pos="from-top" style:vertical-rel="paragraph" style:horizontal-pos="from-left" style:horizontal-rel="paragraph" draw:wrap-influence-on-position="once-concurrent" style:flow-with-text="false"/></style:style>';

        return str_replace('</office:automatic-styles>', $style.'</office:automatic-styles>', $content);
    }

    /** @param array<string, array{path: string, png: string}> $taskMarkers */
    private function injectTaskMarkers(string $content, array $taskMarkers): string
    {
        foreach ($taskMarkers as $token => $marker) {
            [$kind, $taskId] = array_pad(explode('_', substr($token, strlen('ROO_TASK_')), 2), 2, '');
            $y = $kind === 'END' ? '-0.199cm' : '0cm';
            $frame = '<draw:frame text:anchor-type="paragraph" draw:z-index="4" draw:name="assessmentTaskMarker'.htmlspecialchars($kind.$taskId, ENT_XML1).'" draw:style-name="assessmentTaskMarkerFrame" style:horizontal-pos="from-left" style:horizontal-rel="paragraph" svg:x="-1.9cm" svg:y="'.$y.'" svg:width="0.8cm" svg:height="0.8cm"><draw:image xlink:href="'.htmlspecialchars($marker['path'], ENT_XML1).'" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad" draw:mime-type="image/png"/></draw:frame>';
            $pattern = '/<text:p(?![^>]*\/>)([^>]*)>(?:(?!<\/text:p>)[\s\S])*?'.preg_quote($token, '/').'(?:(?!<\/text:p>)[\s\S])*?<\/text:p>/';
            $content = preg_replace($pattern, '<text:p$1>'.$frame.'</text:p>', $content, 1) ?: $content;
        }

        return $content;
    }

    /** @param list<array{path: string, mediaType: string}> $entries */
    private function addManifestEntries(\ZipArchive $archive, array $entries): void
    {
        $manifest = $archive->getFromName('META-INF/manifest.xml');
        if (! is_string($manifest)) {
            return;
        }

        foreach ($entries as $entry) {
            if (! str_contains($manifest, 'manifest:full-path="'.$entry['path'].'"')) {
                $manifest = str_replace(
                    '</manifest:manifest>',
                    '<manifest:file-entry manifest:full-path="'.$entry['path'].'" manifest:media-type="'.$entry['mediaType'].'"/></manifest:manifest>',
                    $manifest,
                );
            }
        }
        $archive->addFromString('META-INF/manifest.xml', $manifest);
    }

    private function addOdtFirstPageHeader(string $styles, ?string $pageMarkerPng): string
    {
        if (preg_match('/<style:master-page style:name="Standard1"[^>]*>.*?<\/style:master-page>/s', $styles, $masterMatches) !== 1) {
            return $styles;
        }

        $master = $masterMatches[0];
        if (preg_match('/<style:header>.*?<\/style:header>/s', $master, $headerMatches) !== 1) {
            return $styles;
        }

        $header = $headerMatches[0];
        $defaultHeader = str_replace('Name:', '', $header);
        $standardMaster = str_replace($header, $defaultHeader, $master);
        $firstHeader = $pageMarkerPng !== null
            ? preg_replace(
                '/(<table:table-row>\s*<table:table-cell[^>]*><text:p[^>]*>)/s',
                '$1<draw:frame text:anchor-type="paragraph" draw:z-index="3" draw:name="assessmentPageMarker" draw:style-name="assessmentPageMarkerFrame" svg:x="0.5cm" svg:y="1cm" svg:width="1.2cm" svg:height="1.2cm"><draw:image xlink:href="Pictures/assessment-page-marker.png" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad" draw:mime-type="image/png"/></draw:frame>',
                $header,
                1,
            ) ?: $header
            : $header;
        $firstMaster = str_replace(
            'style:name="Standard1"',
            'style:name="FirstPage" style:next-style-name="Standard1"',
            str_replace($header, $firstHeader, $master),
        );

        $styles = str_replace($master, $standardMaster, $styles);
        $styles = str_replace('</office:master-styles>', $firstMaster.'</office:master-styles>', $styles);
        $styles = preg_replace(
            '/(style:name="Section1"[^>]*style:master-page-name=")Standard1("?)/',
            '$1FirstPage$2',
            $styles,
            1,
        ) ?: $styles;

        return $styles;
    }
}
