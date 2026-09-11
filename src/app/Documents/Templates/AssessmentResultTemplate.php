<?php

namespace App\Documents\Templates;

use App\Documents\AssessmentResultDocument;
use App\Documents\Document;
use App\Documents\DocumentLayoutProfile;
use App\Documents\DocumentTemplate;
use InvalidArgumentException;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Tab;

final class AssessmentResultTemplate implements DocumentTemplate
{
    public function key(): string
    {
        return 'assessment-result.default';
    }

    public function render(Document $document): PhpWord
    {
        if (! $document instanceof AssessmentResultDocument) {
            throw new InvalidArgumentException('Das Ergebnis-Template benötigt ein AssessmentResultDocument.');
        }

        $word = new PhpWord;
        $profile = $document->layoutProfile();
        $word->setDefaultFontName($profile->fontFamily);
        $word->setDefaultFontSize($profile->bodyFontSize);
        $word->addFontStyle('resultHeading', ['name' => $profile->headingFontFamily, 'size' => $profile->headingFontSize, 'bold' => true]);
        $word->addFontStyle('resultHeader', ['name' => $profile->headingFontFamily, 'size' => $profile->headerFontSize, 'bold' => true]);
        $word->addFontStyle('resultHeaderMeta', ['name' => $profile->headingFontFamily, 'size' => $profile->headingFontSize]);
        $word->addParagraphStyle('resultHeaderBand', [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'shading' => ['fill' => 'D9D9D9'],
            'tabs' => [new Tab(Tab::TAB_STOP_LEFT, 0), new Tab(Tab::TAB_STOP_RIGHT, 10000)],
        ]);
        $word->addFontStyle('resultBody', ['name' => $profile->fontFamily, 'size' => $profile->bodyFontSize]);
        $word->addFontStyle('resultSmall', ['name' => 'Atkinson Hyperlegible Next', 'size' => $profile->smallFontSize, 'color' => '666666']);
        $word->addTableStyle('resultCompetencies', ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 40]);
        $word->addTableStyle('resultPercentageBar', ['borderSize' => 0, 'cellMargin' => 0, 'width' => 2903, 'layout' => 'fixed']);

        foreach ($document->reports as $index => $report) {
            $sectionSettings = [
                'pageSizeW' => 11906,
                'pageSizeH' => 16838,
                'marginTop' => 567,
                'marginRight' => 567,
                'marginBottom' => 567,
                'marginLeft' => 1134,
                'pageNumberingStart' => 1,
                'borderTopSize' => 6,
                'borderTopColor' => '000000',
                'borderTopStyle' => 'single',
                'borderRightSize' => 6,
                'borderRightColor' => '000000',
                'borderRightStyle' => 'single',
                'borderBottomSize' => 6,
                'borderBottomColor' => '000000',
                'borderBottomStyle' => 'single',
                'borderLeftSize' => 6,
                'borderLeftColor' => '000000',
                'borderLeftStyle' => 'single',
            ];
            if (! $profile->pageFrame) {
                foreach (['borderTop', 'borderRight', 'borderBottom', 'borderLeft'] as $border) {
                    unset($sectionSettings[$border.'Size'], $sectionSettings[$border.'Color'], $sectionSettings[$border.'Style']);
                }
            }
            $section = $word->addSection($sectionSettings);
            $title = $report['title'].($report['level'] !== '' ? ' ('.$report['level'].')' : '');
            $this->addPageHeader($section->addHeader(Header::FIRST), $title, $report['student_name'], $profile, $report['date'] ?? null);
            $this->addPageHeader($section->addHeader(), $title, $report['student_name'], $profile, $report['date'] ?? null);
            $this->addFooter($section, $report);
            foreach ($report['tasks'] as $number => $task) {
                $section->addText(($number + 1).'. '.$task['title'], 'resultHeading', ['spaceBefore' => $number === 0 ? 0 : 120, 'spaceAfter' => 40]);
                foreach ($task['expectations'] as $expectation) {
                    $mark = $expectation['points'] !== null && (float) $expectation['points'] >= (float) $expectation['max_points'] ? '✓' : '✗';
                    $points = $expectation['points'] === null ? '–' : $expectation['points'];
                    $section->addText($mark.' '.$expectation['text'].' ('.$points.' / '.$expectation['max_points'].' VP)', 'resultBody', ['spaceAfter' => 40]);
                    foreach ($expectation['notes'] ?? [] as $note) {
                        $section->addText('Anmerkung: '.$note, 'resultSmall', ['leftIndent' => 360, 'spaceAfter' => 40]);
                    }
                }
                if (filled($task['explanation'] ?? null)) {
                    $section->addText($task['explanation'], 'resultBody', ['spaceBefore' => 80, 'spaceAfter' => 40]);
                }
                if (filled($task['extra_note'] ?? null)) {
                    $section->addText('Anmerkung: '.$task['extra_note'], 'resultSmall', ['spaceAfter' => 40]);
                }
                $section->addText('Erreicht: '.$task['points_label'].' VP.', 'resultBody', ['spaceBefore' => 40, 'spaceAfter' => 40]);
                $section->addText('Diese Aufgabe wird innerhalb dieses Kompetenzbereichs mit '.$task['weight_percentage'].' % gewichtet.', 'resultSmall', ['spaceAfter' => 80]);
            }

            $section->addText('Bei dieser LSE wurden folgende Kompetenzen getestet:', 'resultBody', ['spaceBefore' => 260, 'spaceAfter' => 120]);
            $table = $section->addTable('resultCompetencies');
            foreach ($report['competencies'] as $competency) {
                $table->addRow();
                $table->addCell(5834)->addText($competency['title'], 'resultBody');
                $table->addCell(958)->addText($competency['percentage'].' %', 'resultBody', ['alignment' => 'right']);
                $this->addPercentageBar($table->addCell(2903), (int) $competency['percentage']);
            }

            if ($report['receives_grades']) {
                $section->addText('Insgesamt hast du '.$report['percentage'].'% der möglichen Leistung erreicht.', 'resultBody', ['spaceBefore' => 260]);
                $section->addText('Für diese LSE erhältst du die Note '.($report['grade'] ?: '–').'.', 'resultBody', ['spaceBefore' => 180]);
            }
            $signatureFont = ['name' => 'Atkinson Hyperlegible Next', 'size' => $profile->bodyFontSize, 'color' => '000000'];
            $section->addText($report['place'].', '.$report['date'], $signatureFont, ['alignment' => 'right', 'spaceBefore' => 420, 'spaceAfter' => 0]);
            $section->addText($report['author'], $signatureFont, ['alignment' => 'right', 'spaceBefore' => 0, 'spaceAfter' => 0]);
            if ($index < count($document->reports) - 1) {
                $section->addPageBreak();
            }
        }

        return $word;
    }

    private function addPercentageBar(Cell $cell, int $percentage): void
    {
        $percentage = max(0, min(100, $percentage));
        $bar = $cell->addTable('resultPercentageBar');
        $row = $bar->addRow(300);
        $filledWidth = (int) round(2903 * $percentage / 100);
        $emptyWidth = 2903 - $filledWidth;

        if ($filledWidth > 0) {
            $row->addCell($filledWidth, ['bgColor' => '5B9BD5', 'borderSize' => 0]);
        }
        if ($emptyWidth > 0) {
            $row->addCell($emptyWidth, ['bgColor' => 'E7E6E6', 'borderSize' => 0]);
        }
    }

    private function addPageHeader(Header $header, string $title, string $studentName, DocumentLayoutProfile $profile, ?string $date = null): void
    {
        if ($profile->headerBand) {
            $header->addText($title, 'resultHeader', 'resultHeaderBand');
            $meta = $header->addTextRun('resultHeaderBand');
            $meta->addText($date ?? '', 'resultHeaderMeta');
            $meta->addText("\t".$studentName, 'resultHeaderMeta');
            $header->addTextBreak(1);

            return;
        }
        $table = $header->addTable(['width' => 10000, 'layout' => 'fixed', 'borderSize' => 0, 'cellMarginLeft' => 0, 'cellMarginRight' => 0]);
        $row = $table->addRow();
        $cellStyle = ['borderSize' => 0];
        $row->addCell(7000, $cellStyle)->addText($title, 'resultHeader', ['spaceAfter' => 0]);
        $nameStyle = ['borderSize' => 0, 'cellMarginLeft' => 283];
        $row->addCell(3000, $nameStyle)->addText($studentName, 'resultHeader', ['spaceAfter' => 0, 'alignment' => 'right']);
        if (! $profile->headerBand) {
            $header->addShape('line', ['points' => '0,0 10000,0', 'width' => 10000, 'height' => 1, 'outline' => ['color' => '000000', 'weight' => 1]]);
        }
        $header->addTextBreak(1);
    }

    /** @param array<string, mixed> $report */
    private function addFooter(Section $section, array $report): void
    {
        $footer = $section->addFooter();
        $font = ['name' => 'Atkinson Hyperlegible Next', 'size' => 6, 'color' => '808080'];
        $copyright = $footer->addTextRun(['alignment' => 'right', 'spaceBefore' => 0, 'spaceAfter' => 0]);
        $copyright->addText('© '.date('Y').' '.trim((string) ($report['author'] ?? '')), $font);
        $details = $footer->addTextRun(['alignment' => 'right', 'spaceBefore' => 0, 'spaceAfter' => 0]);
        $elements = array_values(array_filter([$report['student_name'] ?? null, $report['school'] ?? null, $report['school_year'] ?? null, $report['group'] ?? null, $report['footer_title'] ?? null, $report['date'] ?? null], fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
        foreach ($elements as $index => $element) {
            if ($index > 0) {
                $details->addText(' · ', $font);
            }
            $details->addText(trim($element), $font);
        }
        if ($elements !== []) {
            $details->addText(' · ', $font);
        }
        $details->addText('Resultate', $font);
        $details->addText(' · Seite ', $font);
        $details->addField('PAGE', [], ['PreserveFormat'], null, $font);
    }
}
