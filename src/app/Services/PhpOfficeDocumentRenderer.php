<?php

namespace App\Services;

use App\Documents\AssessmentDocument;
use App\Documents\AssessmentResultDocument;
use App\Documents\Document;
use App\Documents\DocumentOutputFormat;
use App\Documents\DocumentTemplateRegistry;
use App\Documents\ParentLetterDocument;
use Com\Tecnick\Barcode\Barcode;
use PfarrTools\RooRuling\PhpWord\DrawingRulingRenderer;
use PfarrTools\RooRuling\RulingDefinition;
use PfarrTools\RooRuling\RulingPreset;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class PhpOfficeDocumentRenderer
{
    private const IMAGE_LABELING_FLOW_CLEARANCE_CM = 0.5;

    private const IMAGE_LABELING_FRAME_LEFT_CM = 0.0;

    private const IMAGE_LABELING_FRAME_RIGHT_CM = 17.1;

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
                ? $this->addOdtPageFrame($this->patchOdtSolutionStyles($this->patchOdtParentLetterLists($this->patchOdtClozeLineHeights($this->patchOdtSubtaskTables($this->patchOdtImageLabeling($this->patchOdtVerticalMerges($contents, $document), $document), $document), $document), $document), $document), $document)
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
            file_put_contents($path, $format === DocumentOutputFormat::ODT ? $this->addOdtPageFrame($this->patchOdtSolutionStyles($this->patchOdtParentLetterLists($this->patchOdtClozeLineHeights($this->patchOdtSubtaskTables($this->patchOdtImageLabeling($this->patchOdtVerticalMerges($contents, $document), $document), $document), $document), $document), $document), $document) : $contents);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function patchOdtParentLetterLists(string $contents, Document $document): string
    {
        if (! $document instanceof ParentLetterDocument) {
            return $contents;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-odt-parent-letter-');
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

            $dom = new \DOMDocument;
            $dom->preserveWhiteSpace = true;
            if (! $dom->loadXML($content)) {
                $archive->close();

                return $contents;
            }

            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
            $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
            $textNamespace = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
            $styleNamespace = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
            $foNamespace = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';

            $automaticStyles = $xpath->query('//office:automatic-styles')->item(0);
            if (! $automaticStyles instanceof \DOMElement) {
                $archive->close();

                return $contents;
            }

            $listStyle = $dom->createElementNS($styleNamespace, 'style:list-style');
            $listStyle->setAttributeNS($styleNamespace, 'style:name', 'parentLetterCompetencyList');
            $bulletStyle = $dom->createElementNS($textNamespace, 'text:list-level-style-bullet');
            $bulletStyle->setAttributeNS($textNamespace, 'text:level', '1');
            $bulletStyle->setAttributeNS($textNamespace, 'text:bullet-char', '•');
            $listProperties = $dom->createElementNS($styleNamespace, 'style:list-level-properties');
            $listProperties->setAttributeNS($textNamespace, 'text:space-before', '0.5in');
            $listProperties->setAttributeNS($textNamespace, 'text:min-label-width', '0.25in');
            $bulletStyle->appendChild($listProperties);
            $listStyle->appendChild($bulletStyle);

            $paragraphStyle = $dom->createElementNS($styleNamespace, 'style:style');
            $paragraphStyle->setAttributeNS($styleNamespace, 'style:name', 'parentLetterListParagraph');
            $paragraphStyle->setAttributeNS($styleNamespace, 'style:family', 'paragraph');
            $paragraphStyle->setAttributeNS($styleNamespace, 'style:parent-style-name', 'Normal');
            $paragraphProperties = $dom->createElementNS($styleNamespace, 'style:paragraph-properties');
            $paragraphProperties->setAttributeNS($foNamespace, 'fo:margin-bottom', '0pt');
            $paragraphStyle->appendChild($paragraphProperties);
            $automaticStyles->appendChild($listStyle);
            $automaticStyles->appendChild($paragraphStyle);

            $bulletParagraphs = $xpath->query('//text:p[starts-with(normalize-space(string(.)), "• ")]');
            foreach ($bulletParagraphs ?: [] as $paragraph) {
                if (! $paragraph instanceof \DOMElement || $paragraph->parentNode instanceof \DOMElement && $paragraph->parentNode->localName === 'list-item') {
                    continue;
                }

                $parent = $paragraph->parentNode;
                if (! $parent instanceof \DOMNode) {
                    continue;
                }

                $list = $dom->createElementNS($textNamespace, 'text:list');
                $list->setAttributeNS($textNamespace, 'text:style-name', 'parentLetterCompetencyList');
                $parent->replaceChild($list, $paragraph);
                $current = $paragraph;

                while ($current instanceof \DOMElement && str_starts_with(trim($current->textContent), '• ')) {
                    $next = $current->nextSibling;
                    while ($next instanceof \DOMText && trim($next->textContent) === '') {
                        $next = $next->nextSibling;
                    }

                    $current->setAttributeNS($textNamespace, 'text:style-name', 'parentLetterListParagraph');
                    $this->removeOdtBulletPrefix($current);
                    $item = $dom->createElementNS($textNamespace, 'text:list-item');
                    $item->appendChild($current);
                    $list->appendChild($item);
                    $current = $next;
                }
            }

            $archive->addFromString('content.xml', $dom->saveXML());
            $archive->close();

            return (string) file_get_contents($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function removeOdtBulletPrefix(\DOMElement $paragraph): void
    {
        $removePrefix = function (\DOMNode $node) use (&$removePrefix): bool {
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMText) {
                    $child->nodeValue = preg_replace('/^\s*•\s/u', '', $child->nodeValue ?? '', 1) ?? $child->nodeValue;

                    return true;
                }
                if ($removePrefix($child)) {
                    return true;
                }
            }

            return false;
        };

        $removePrefix($paragraph);
    }

    private function patchOdtVerticalMerges(string $contents, Document $document): string
    {
        if (! $document instanceof AssessmentDocument) {
            return $contents;
        }

        $hasImageMatching = collect($document->tasks)->where('task_type', 'image_matching')->isNotEmpty();

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
            $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
            $xpath->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
            $xpath->registerNamespace('fo', 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0');
            foreach ($hasImageMatching ? $xpath->query('//style:style[@style:name="fr1"]/style:graphic-properties') : [] as $graphicProperties) {
                $graphicProperties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:border', '0.06pt solid #000000');
            }
            foreach ($hasImageMatching ? $xpath->query('//table:table') : [] as $table) {
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

    private function patchOdtImageLabeling(string $contents, Document $document): string
    {
        if (! $document instanceof AssessmentDocument) {
            return $contents;
        }

        $tasks = collect($document->tasks)->filter(fn ($task): bool => ($task['task_type'] ?? '') === 'image_labeling')->keyBy(fn ($task): string => (string) ($task['task_id'] ?? ''));
        if ($tasks->isEmpty()) {
            return $contents;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-odt-label-');
        if ($temporaryPath === false) {
            return $contents;
        }

        try {
            file_put_contents($temporaryPath, $contents);
            $archive = new \ZipArchive;
            if ($archive->open($temporaryPath) !== true) {
                return $contents;
            }
            $xml = $archive->getFromName('content.xml');
            if (! is_string($xml)) {
                $archive->close();

                return $contents;
            }
            $dom = new \DOMDocument;
            $dom->preserveWhiteSpace = true;
            if (! $dom->loadXML($xml)) {
                $archive->close();

                return $contents;
            }
            $xpath = new \DOMXPath($dom);
            foreach ([
                'text' => 'urn:oasis:names:tc:opendocument:xmlns:text:1.0',
                'office' => 'urn:oasis:names:tc:opendocument:xmlns:office:1.0',
                'draw' => 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0',
                'style' => 'urn:oasis:names:tc:opendocument:xmlns:style:1.0',
                'fo' => 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0',
                'svg' => 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0',
                'table' => 'urn:oasis:names:tc:opendocument:xmlns:table:1.0',
            ] as $prefix => $uri) {
                $xpath->registerNamespace($prefix, $uri);
            }
            $styles = $xpath->query('/office:document-content/office:automatic-styles')->item(0);
            if (! $styles instanceof \DOMElement) {
                $archive->close();

                return $contents;
            }
            $this->appendImageLabelingStyles($dom, $styles);
            $ruling = $this->rulingForGrade($document->gradeLevel)->definition();

            foreach ($xpath->query('//text:p[contains(., "ROO_IMAGE_LABELING_")]') as $paragraph) {
                if (! $paragraph instanceof \DOMElement || ! preg_match('/ROO_IMAGE_LABELING_(.+)$/', trim($paragraph->textContent), $matches)) {
                    continue;
                }
                $task = $tasks->get($matches[1]);
                if (! is_array($task)) {
                    continue;
                }
                $image = $task['content']['image'] ?? null;
                if (! is_array($image) || ! is_file((string) ($image['path'] ?? ''))) {
                    continue;
                }
                $dimensions = @getimagesize($image['path']);
                if (! is_array($dimensions) || ($dimensions[0] ?? 0) <= 0 || ($dimensions[1] ?? 0) <= 0) {
                    continue;
                }
                $imageWidth = min(8.0, max(4.0, (float) ($task['content']['image_label_width_cm'] ?? 6.0)));
                $imageHeight = $imageWidth * $dimensions[1] / $dimensions[0];
                $contentWidth = 17.5;
                $innerFrameLeft = self::IMAGE_LABELING_FRAME_LEFT_CM;
                $innerFrameRight = self::IMAGE_LABELING_FRAME_RIGHT_CM;
                $requestedLayout = $task['content']['image_label_layout'] ?? 'center';
                $layout = in_array($requestedLayout, ['center', 'left', 'right'], true)
                    ? $requestedLayout
                    : 'center';
                $imageLeft = match ($layout) {
                    'left' => 0.0,
                    'right' => $contentWidth - $imageWidth,
                    default => ($contentWidth - $imageWidth) / 2,
                };
                $rulingZoneHeightCm = $ruling->bandHeightMm() / 10;
                $paddedImage = $this->createImageLabelingPaddedImage((string) $image['path'], (int) $dimensions[0], (int) $dimensions[1], $imageWidth, $contentWidth, $layout);
                $imageFrame = $this->directChildren($paragraph, 'frame')[0] ?? null;
                if (! $imageFrame instanceof \DOMElement) {
                    continue;
                }
                $imageFrame->setAttribute('text:anchor-type', 'paragraph');
                $imageFrame->setAttribute('draw:style-name', 'assessmentImageLabelingImageFrame');
                $imageFrame->setAttribute('style:horizontal-pos', 'from-left');
                $imageFrame->setAttribute('style:horizontal-rel', 'paragraph');
                $imageFrame->setAttribute('style:vertical-pos', 'from-top');
                $imageFrame->setAttribute('style:vertical-rel', 'paragraph');
                $imageFrame->setAttribute('svg:x', '0cm');
                $imageFrame->setAttribute('svg:y', '0cm');
                $imageFrame->setAttribute('svg:width', $contentWidth.'cm');
                $imageFrame->setAttribute('svg:height', $imageHeight.'cm');
                if ($paddedImage !== null) {
                    $imageNode = $imageFrame->getElementsByTagNameNS('urn:oasis:names:tc:opendocument:xmlns:drawing:1.0', 'image')->item(0);
                    if ($imageNode instanceof \DOMElement) {
                        $imageNode->setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', $paddedImage['path']);
                        $archive->addFromString($paddedImage['path'], $paddedImage['data']);
                        $this->addManifestEntries($archive, [['path' => $paddedImage['path'], 'mediaType' => 'image/png']]);
                    }
                }

                $labels = collect($image['labels'] ?? [])->values()->map(fn ($label, $index): array => [
                    'index' => $index,
                    'x' => $imageLeft + $imageWidth * ((float) ($label['x_percent'] ?? 0) / 100),
                    'y' => $imageHeight * ((float) ($label['y_percent'] ?? 0) / 100),
                    'lines' => max(1, min(9, (int) ($label['lines'] ?? 1))),
                ]);
                $canvasHeight = $imageHeight;
                $sides = match ($layout) {
                    'left' => ['right'],
                    'right' => ['left'],
                    default => ['left', 'right'],
                };
                foreach ($sides as $side) {
                    $sideLabels = ($layout === 'center'
                        ? $labels->filter(fn (array $label): bool => ($label['x'] <= $imageLeft + $imageWidth / 2) === ($side === 'left'))
                        : $labels)->sortBy('y')->values();
                    $lastBottom = 0.0;
                    $itemGap = ($ruling->gapMm / 10) + 0.3;
                    foreach ($sideLabels as $label) {
                        $boxWidth = 3.5;
                        $lineHeight = $rulingZoneHeightCm;
                        $boxHeight = $label['lines'] * $lineHeight;
                        $boxX = $side === 'left' ? $imageLeft - 0.35 - $boxWidth : $imageLeft + $imageWidth + 0.35;
                        $boxY = max($label['y'] - $boxHeight / 2, $lastBottom + $itemGap);
                        $lastBottom = $boxY + $boxHeight;
                        $canvasHeight = max($canvasHeight, $lastBottom);
                        $edgeX = $side === 'left' ? $boxX + $boxWidth : $boxX;
                        $firstRulingLineY = $boxY + $rulingZoneHeightCm;
                        $imageReferenceLineY = $firstRulingLineY - ($rulingZoneHeightCm / 2);
                        $this->appendImageLabelingLine($dom, $paragraph, $edgeX, $imageReferenceLineY, $label['x'], $label['y'], $label['index']);
                        $rulingX = $side === 'left' ? $innerFrameLeft : $edgeX;
                        $rulingWidth = $side === 'left' ? $edgeX - $innerFrameLeft : $innerFrameRight - $edgeX;
                        $this->appendImageLabelingBox($dom, $styles, $paragraph, $rulingX, $boxY, $rulingWidth, $label['lines'], $label['index'], $ruling);
                    }
                }
                $canvasHeight += self::IMAGE_LABELING_FLOW_CLEARANCE_CM;
                $canvasStyle = 'assessmentImageLabelingCanvas_'.preg_replace('/[^A-Za-z0-9_]/', '_', $matches[1]);
                $this->appendImageLabelingCanvasStyle($dom, $styles, $canvasStyle, $canvasHeight);
                $paragraph->setAttribute('text:style-name', $canvasStyle);
            }

            $archive->addFromString('content.xml', $dom->saveXML());
            $archive->close();

            return (string) file_get_contents($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function patchOdtSubtaskTables(string $contents, Document $document): string
    {
        if (! $document instanceof AssessmentDocument || collect($document->tasks)->whereIn('task_type', ['subtask_table', 'image_answer_table'])->isEmpty()) {
            return $contents;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-odt-subtask-');
        if ($temporaryPath === false) {
            return $contents;
        }

        try {
            file_put_contents($temporaryPath, $contents);
            $archive = new \ZipArchive;
            if ($archive->open($temporaryPath) !== true) {
                return $contents;
            }
            $xml = $archive->getFromName('content.xml');
            if (! is_string($xml)) {
                $archive->close();

                return $contents;
            }
            $dom = new \DOMDocument;
            $dom->preserveWhiteSpace = true;
            $dom->loadXML($xml);
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
            foreach ($xpath->query('//table:table') as $table) {
                $rows = $this->directChildren($table, 'table-row');
                $cells = $rows === [] ? [] : $this->directChildren($rows[0], 'table-cell');
                if (count($cells) !== 2 || count($rows) < 1) {
                    continue;
                }
                foreach ($rows as $row) {
                    $rowCells = $this->directChildren($row, 'table-cell');
                    if (count($rowCells) !== 2) {
                        continue;
                    }
                    $rowCells[0]->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'table:style-name', 'assessmentSubtaskLabelCell');
                    $rowCells[1]->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0', 'table:style-name', 'assessmentSubtaskAnswerCell');
                }
            }
            $automaticStyles = $xpath->query('//office:automatic-styles')->item(0);
            if ($automaticStyles !== null) {
                foreach (['assessmentSubtaskLabelCell', 'assessmentSubtaskAnswerCell'] as $styleName) {
                    $style = $dom->createElementNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'style:style');
                    $style->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'style:name', $styleName);
                    $style->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'style:family', 'table-cell');
                    $properties = $dom->createElementNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'style:table-cell-properties');
                    $properties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:border', '0.05cm solid #000000');
                    $properties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:padding', '0.15cm');
                    $style->appendChild($properties);
                    $automaticStyles->appendChild($style);
                }
            }
            $archive->addFromString('content.xml', $dom->saveXML());
            $archive->close();

            return (string) file_get_contents($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function patchOdtClozeLineHeights(string $contents, Document $document): string
    {
        if (! $document instanceof AssessmentDocument || collect($document->tasks)->where('task_type', 'cloze')->isEmpty()) {
            return $contents;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-odt-cloze-height-');
        if ($temporaryPath === false) {
            return $contents;
        }

        try {
            file_put_contents($temporaryPath, $contents);
            $archive = new \ZipArchive;
            if ($archive->open($temporaryPath) !== true) {
                return $contents;
            }
            $xml = $archive->getFromName('content.xml');
            if (! is_string($xml)) {
                $archive->close();

                return $contents;
            }

            $dom = new \DOMDocument;
            $dom->preserveWhiteSpace = true;
            if (! $dom->loadXML($xml)) {
                $archive->close();

                return $contents;
            }

            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('draw', 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0');
            $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
            $xpath->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
            $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

            $lineHeightCm = number_format(($this->rulingForGrade($document->gradeLevel)->definition()->bandHeightMm() + 2) / 10, 2, '.', '');
            foreach ($xpath->query('//office:automatic-styles/style:style[@style:family="paragraph"]') as $style) {
                $styleName = $style->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0', 'name');
                $properties = $xpath->query('./style:paragraph-properties', $style)->item(0);
                if ($properties === null || $xpath->query('//text:p[@text:style-name="'.$styleName.'"]//draw:frame[draw:image]', $dom)->length === 0) {
                    continue;
                }
                $properties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:line-height', $lineHeightCm.'cm');
            }

            $archive->addFromString('content.xml', $dom->saveXML());
            $archive->close();

            return (string) file_get_contents($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function patchOdtSolutionStyles(string $contents, Document $document): string
    {
        if (! $document instanceof AssessmentDocument) {
            return $contents;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-odt-solution-style-');
        if ($temporaryPath === false) {
            return $contents;
        }

        try {
            file_put_contents($temporaryPath, $contents);
            $archive = new \ZipArchive;
            if ($archive->open($temporaryPath) !== true) {
                return $contents;
            }

            $xml = $archive->getFromName('styles.xml');
            if (! is_string($xml)) {
                $archive->close();

                return $contents;
            }

            $dom = new \DOMDocument;
            $dom->preserveWhiteSpace = true;
            if (! $dom->loadXML($xml)) {
                $archive->close();

                return $contents;
            }

            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
            $xpath->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
            $xpath->registerNamespace('fo', 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0');
            foreach ($xpath->query('//office:styles/style:style[@style:family="text" and @style:name="assessmentSolution"]/style:text-properties | //office:automatic-styles/style:style[@style:family="text" and @style:name="assessmentSolution"]/style:text-properties') as $properties) {
                $properties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:border', '0.05cm solid #000000');
                $properties->setAttributeNS('urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0', 'fo:padding', '0.2cm');
            }

            $archive->addFromString('styles.xml', $dom->saveXML());
            $archive->close();

            return (string) file_get_contents($temporaryPath);
        } finally {
            unlink($temporaryPath);
        }
    }

    private function appendImageLabelingStyles(\DOMDocument $dom, \DOMElement $styles): void
    {
        $styleUri = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
        $foUri = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';
        $drawUri = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
        $svgUri = 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0';
        $tableUri = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
        foreach ([
            ['assessmentImageLabelingCanvas', 'paragraph', ['fo:min-height' => '1cm']],
            ['assessmentImageLabelingLine', 'paragraph', ['fo:margin-top' => '0cm', 'fo:margin-bottom' => '0cm', 'fo:line-height' => '0.65cm']],
            ['assessmentImageLabelingText', 'text', ['style:font-name' => 'Atkinson Hyperlegible Next', 'fo:font-size' => '14pt']],
            ['assessmentImageLabelingImageFrame', 'graphic', ['draw:stroke' => 'none', 'draw:fill' => 'none', 'style:wrap' => 'none']],
            ['assessmentImageLabelingTextFrame', 'graphic', ['draw:stroke' => 'none', 'draw:fill' => 'none', 'style:wrap' => 'none']],
            ['assessmentImageLabelingLineFrame', 'graphic', ['draw:stroke' => 'solid', 'svg:stroke-color' => '#000000', 'svg:stroke-width' => '0.5pt']],
        ] as [$name, $family, $properties]) {
            $style = $dom->createElementNS($styleUri, 'style:style');
            $style->setAttributeNS($styleUri, 'style:name', $name);
            $style->setAttributeNS($styleUri, 'style:family', $family);
            $propertyElement = match ($family) {
                'graphic' => 'style:graphic-properties',
                'paragraph' => 'style:paragraph-properties',
                'table' => 'style:table-properties',
                'table-column' => 'style:table-column-properties',
                'table-row' => 'style:table-row-properties',
                'table-cell' => 'style:table-cell-properties',
                default => 'style:text-properties',
            };
            $propertiesElement = $dom->createElementNS($styleUri, $propertyElement);
            foreach ($properties as $attribute => $value) {
                [$prefix, $local] = explode(':', $attribute, 2);
                $uri = match ($prefix) {
                    'draw' => $drawUri,
                    'svg' => $svgUri,
                    'fo' => $foUri,
                    'table' => $tableUri,
                    default => $styleUri,
                };
                $propertiesElement->setAttributeNS($uri, $attribute, $value);
            }
            $style->appendChild($propertiesElement);
            $styles->appendChild($style);
        }
    }

    /** @return array{path: string, data: string}|null */
    private function createImageLabelingPaddedImage(string $sourcePath, int $sourceWidth, int $sourceHeight, float $imageWidthCm, float $contentWidthCm, string $layout): ?array
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $sourceData = @file_get_contents($sourcePath);
        $source = is_string($sourceData) ? @imagecreatefromstring($sourceData) : false;
        if ($source === false) {
            return null;
        }

        $canvasWidth = max($sourceWidth, (int) round($sourceWidth * $contentWidthCm / $imageWidthCm));
        $canvas = imagecreatetruecolor($canvasWidth, $sourceHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        $imageOffset = match ($layout) {
            'left' => 0,
            'right' => $canvasWidth - $sourceWidth,
            default => (int) floor(($canvasWidth - $sourceWidth) / 2),
        };
        imagecopy($canvas, $source, $imageOffset, 0, 0, 0, $sourceWidth, $sourceHeight);
        ob_start();
        imagepng($canvas);
        $data = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);
        if (! is_string($data) || $data === '') {
            return null;
        }

        return [
            'path' => 'Pictures/assessment-image-labeling-padded-'.sha1($sourcePath.$imageWidthCm.$contentWidthCm.$layout).'.png',
            'data' => $data,
        ];
    }

    private function appendImageLabelingCanvasStyle(\DOMDocument $dom, \DOMElement $styles, string $name, float $height): void
    {
        $styleUri = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
        $foUri = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';
        $style = $dom->createElementNS($styleUri, 'style:style');
        $style->setAttributeNS($styleUri, 'style:name', $name);
        $style->setAttributeNS($styleUri, 'style:family', 'paragraph');
        $properties = $dom->createElementNS($styleUri, 'style:paragraph-properties');
        $properties->setAttributeNS($foUri, 'fo:min-height', max(1.0, $height).'cm');
        $style->appendChild($properties);
        $styles->appendChild($style);
    }

    private function appendImageLabelingBox(\DOMDocument $dom, \DOMElement $styles, \DOMElement $paragraph, float $x, float $y, float $width, int $lines, int $index, RulingDefinition $ruling): void
    {
        $this->appendImageLabelingDrawingRuling($dom, $styles, $paragraph, $x, $y, $width, $lines, $index, $ruling);
    }

    private function appendImageLabelingDrawingRuling(\DOMDocument $dom, \DOMElement $styles, \DOMElement $paragraph, float $x, float $y, float $width, int $count, int $index, RulingDefinition $ruling): void
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'roo-drawing-ruling-');
        if ($temporaryPath === false) {
            return;
        }

        try {
            $phpWord = new PhpWord;
            $section = $phpWord->addSection();
            (new DrawingRulingRenderer)->render(
                section: $section,
                ruling: $ruling,
                leftMm: 0,
                topMm: 0,
                widthMm: $width * 10,
                count: $count,
            );
            IOFactory::createWriter($phpWord, 'ODText')->save($temporaryPath);

            $archive = new \ZipArchive;
            if ($archive->open($temporaryPath) !== true) {
                return;
            }
            $fragment = $archive->getFromName('content.xml');
            $archive->close();
            if (! is_string($fragment)) {
                return;
            }

            $fragmentDom = new \DOMDocument;
            $fragmentDom->preserveWhiteSpace = false;
            if (! $fragmentDom->loadXML($fragment)) {
                return;
            }
            $fragmentXPath = new \DOMXPath($fragmentDom);
            $fragmentXPath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
            $fragmentXPath->registerNamespace('draw', 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0');
            $fragmentXPath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
            $fragmentXPath->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
            $automaticStyles = $fragmentXPath->query('/office:document-content/office:automatic-styles')->item(0);
            $lineNodes = $fragmentXPath->query('//draw:line');
            if (! $automaticStyles instanceof \DOMElement || $lineNodes === false) {
                return;
            }

            $styleMap = [];
            foreach ($lineNodes as $lineIndex => $lineNode) {
                if (! $lineNode instanceof \DOMElement) {
                    continue;
                }
                $sourceStyle = $lineNode->getAttribute('draw:style-name');
                if (! isset($styleMap[$sourceStyle])) {
                    $sourceStyleNode = $fragmentXPath->query('./style:style[@style:name="'.htmlspecialchars($sourceStyle, ENT_XML1).'" ]', $automaticStyles)->item(0);
                    if ($sourceStyleNode instanceof \DOMElement) {
                        $targetStyle = 'assessmentImageLabelingRulingStyle'.$index.'_'.count($styleMap);
                        $clonedStyle = $dom->importNode($sourceStyleNode, true);
                        $clonedStyle->setAttribute('style:name', $targetStyle);
                        $styles->appendChild($clonedStyle);
                        $styleMap[$sourceStyle] = $targetStyle;
                    }
                }
                $line = $dom->importNode($lineNode, true);
                $line->setAttribute('draw:name', 'assessmentImageLabelingRulingLine'.$index.'_'.$lineIndex);
                $line->setAttribute('draw:style-name', $styleMap[$sourceStyle] ?? 'assessmentImageLabelingLineFrame');
                foreach (['x1', 'x2'] as $coordinate) {
                    $line->setAttribute('svg:'.$coordinate, ($x + (float) preg_replace('/cm$/', '', $line->getAttribute('svg:'.$coordinate))).'cm');
                }
                foreach (['y1', 'y2'] as $coordinate) {
                    $line->setAttribute('svg:'.$coordinate, ($y + (float) preg_replace('/cm$/', '', $line->getAttribute('svg:'.$coordinate))).'cm');
                }
                $paragraph->appendChild($line);
            }
        } finally {
            unlink($temporaryPath);
        }
    }

    private function appendImageLabelingLine(\DOMDocument $dom, \DOMElement $paragraph, float $x1, float $y1, float $x2, float $y2, int $index): void
    {
        $drawUri = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
        $line = $dom->createElementNS($drawUri, 'draw:line');
        $line->setAttribute('text:anchor-type', 'paragraph');
        $line->setAttribute('draw:z-index', '2');
        $line->setAttribute('draw:name', 'assessmentImageLabelingLine'.$index);
        $line->setAttribute('draw:style-name', 'assessmentImageLabelingLineFrame');
        $line->setAttribute('svg:stroke-color', '#000000');
        $line->setAttribute('svg:stroke-width', '0.5pt');
        $line->setAttribute('svg:x1', $x1.'cm');
        $line->setAttribute('svg:y1', $y1.'cm');
        $line->setAttribute('svg:x2', $x2.'cm');
        $line->setAttribute('svg:y2', $y2.'cm');
        $paragraph->appendChild($line);
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
            $isParentLetter = $document instanceof ParentLetterDocument;
            $pageMarkerPng = $this->pageMarkerPng($this->pageMarker($metadata));
            $taskMarkers = $this->taskMarkers($document);

            if ($document instanceof AssessmentResultDocument) {
                $styles = $this->normalizeAssessmentResultTableStyleDefinitions($styles);
            }

            $styles = preg_replace_callback(
                '/(<style:page-layout-properties\b)([^>]*)(>)/',
                static function (array $matches): string {
                    $attributes = $matches[2];
                    $attributes = preg_replace('/\s+fo:border="[^"]*"/', '', $attributes) ?: $attributes;
                    $attributes = preg_replace('/\s+fo:padding="[^"]*"/', '', $attributes) ?: $attributes;

                    return $matches[1].$attributes.' fo:border="0.05cm solid #000000" fo:padding="0.4cm"'.$matches[3];
                },
                $styles,
            ) ?: $styles;
            $styles = preg_replace_callback(
                '/(<style:page-layout-properties\b[^>]*?) fo:margin-left="([^"]+)" fo:margin-right="([^"]+)"/',
                static fn (array $matches): string => $matches[1].' fo:margin-left="'.$matches[3].'" fo:margin-right="'.$matches[2].'"',
                $styles,
            ) ?: $styles;
            $styles = preg_replace_callback(
                '/(<style:page-layout-properties\b[^>]*>)/',
                static function (array $matches): string {
                    $properties = preg_replace('/fo:margin-top="[^"]+"/', 'fo:margin-top="0.39375in"', $matches[1]) ?: $matches[1];
                    $properties = preg_replace('/fo:margin-bottom="[^"]+"/', 'fo:margin-bottom="0.39375in"', $properties) ?: $properties;

                    return $properties;
                },
                $styles,
            ) ?: $styles;
            if (! $isParentLetter) {
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
            } else {
                $styles = str_replace('style:master-page-name="FirstPage"', 'style:master-page-name="Standard1"', $styles);
                $styles = str_replace(
                    '</office:automatic-styles>',
                    '<style:style style:name="parentLetterFooterText" style:family="text"><style:text-properties style:font-name="Atkinson Hyperlegible Next" fo:font-size="8pt" fo:color="#808080" fo:font-weight="normal"/></style:style></office:automatic-styles>',
                    $styles,
                );
                $styles = preg_replace_callback(
                    '/<style:footer>(.*?)<\/style:footer>/s',
                    static function (array $matches): string {
                        $footer = preg_replace('/<text:span(?![^>]*text:style-name)/', '<text:span text:style-name="parentLetterFooterText"', $matches[1]) ?: $matches[1];

                        return '<style:footer>'.$footer.'</style:footer>';
                    },
                    $styles,
                ) ?: $styles;
            }
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
                if ($document instanceof AssessmentResultDocument) {
                    $content = $this->normalizeAssessmentResultTableStyles($content, $styles);
                }
                if (! $isParentLetter) {
                    $content = preg_replace(
                        '/(style:name="SB1"[^>]*style:master-page-name=")Standard1/',
                        '$1FirstPage',
                        $content,
                        1,
                    ) ?: $content;
                }
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

    private function normalizeAssessmentResultTableStyles(string $content, string $styles): string
    {
        if (! preg_match('/<style:style\s+style:name="([^"]+)"\s+style:family="table"[^>]*><style:table-properties[^>]*style:width="17\.1cm"/', $styles, $matches)) {
            return $content;
        }

        $tableStyle = $matches[1];

        return preg_replace_callback(
            '/<table:table\s[^>]*>\s*(?:<table:table-column\s[^>]*>\s*){3}/s',
            static function (array $tableMatches) use ($tableStyle): string {
                $table = preg_replace(
                    '/(table:style-name=")[^"]+(")/',
                    '$1'.$tableStyle.'$2',
                    $tableMatches[0],
                    1,
                ) ?: $tableMatches[0];
                $table = preg_replace(
                    '/(table:table-column\s+table:style-name=")[^"]+(\.[0-2]")/',
                    '$1'.$tableStyle.'$2',
                    $table,
                ) ?: $table;

                return $table;
            },
            $content,
        ) ?: $content;
    }

    private function normalizeAssessmentResultTableStyleDefinitions(string $styles): string
    {
        $tableStyles = [];
        preg_match_all(
            '/<style:style\s+style:name="([^"]+)"\s+style:family="table"><style:table-properties\b[^>]*\/><\/style:style>/',
            $styles,
            $tableMatches,
        );

        foreach ($tableMatches[1] as $tableStyle) {
            preg_match_all(
                '/<style:style\s+style:name="'.preg_quote($tableStyle, '/').'\.\d+"\s+style:family="table-column"[^>]*>.*?style:column-width="([^"]+)".*?<\/style:style>/s',
                $styles,
                $columnMatches,
            );
            $widths = $columnMatches[1] ?? [];

            if ($widths === ['10.29cm', '1.69cm', '5.12cm']) {
                $tableStyles[$tableStyle] = '17.1cm';
            } elseif ($widths === ['2.56cm', '2.56cm']) {
                $tableStyles[$tableStyle] = '5.12cm';
            }
        }

        foreach ($tableStyles as $tableStyle => $width) {
            $styles = preg_replace_callback(
                '/(<style:style\s+style:name="'.preg_quote($tableStyle, '/').'"\s+style:family="table"><style:table-properties\b)([^>]*)(\/>)<\/style:style>/',
                static function (array $matches) use ($width): string {
                    $attributes = preg_replace('/\s+style:rel-width="[^"]*"/', '', $matches[2]) ?: $matches[2];
                    $attributes = preg_replace('/\s+style:width="[^"]*"/', '', $attributes) ?: $attributes;

                    return $matches[1].$attributes.' style:width="'.$width.'"'.$matches[3].'</style:style>';
                },
                $styles,
            ) ?: $styles;
        }

        return $styles;
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

    private function rulingForGrade(string $gradeLevel): RulingPreset
    {
        preg_match_all('/\d+/', $gradeLevel, $matches);
        $grade = $matches[0] === [] ? 4 : min(array_map('intval', $matches[0]));

        return match (true) {
            $grade <= 1 => RulingPreset::Grade1,
            $grade === 2 => RulingPreset::Grade2,
            $grade === 3 => RulingPreset::Grade3,
            default => RulingPreset::Grade4Plus,
        };
    }
}
