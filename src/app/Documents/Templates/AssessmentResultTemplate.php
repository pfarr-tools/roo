<?php

namespace App\Documents\Templates;

use App\Documents\AssessmentResultDocument;
use App\Documents\Document;
use App\Documents\DocumentTemplate;
use InvalidArgumentException;
use PhpOffice\PhpWord\PhpWord;

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
        $word->setDefaultFontName('Atkinson Hyperlegible Next');
        $word->setDefaultFontSize(11);
        $word->addFontStyle('resultHeading', ['name' => 'Comic Neue', 'size' => 14, 'bold' => true]);
        $word->addFontStyle('resultBody', ['name' => 'Atkinson Hyperlegible Next', 'size' => 11]);
        $word->addFontStyle('resultSmall', ['name' => 'Atkinson Hyperlegible Next', 'size' => 9, 'color' => '666666']);
        $word->addTableStyle('resultCompetencies', ['borderSize' => 0, 'cellMargin' => 40]);

        foreach ($document->reports as $index => $report) {
            $section = $word->addSection(['marginTop' => 900, 'marginRight' => 900, 'marginBottom' => 900, 'marginLeft' => 900]);
            $section->addText($report['title'].($report['level'] !== '' ? ' ('.$report['level'].')' : ''), 'resultHeading');
            $section->addText($report['student_name'], 'resultHeading', ['alignment' => 'right', 'spaceAfter' => 240]);

            foreach ($report['tasks'] as $number => $task) {
                $section->addText(($number + 1).'. '.$task['title'], 'resultHeading', ['spaceBefore' => $number === 0 ? 0 : 120, 'spaceAfter' => 40]);
                foreach ($task['expectations'] as $expectation) {
                    $mark = $expectation['points'] !== null && (float) $expectation['points'] >= (float) $expectation['max_points'] ? '✓' : '✗';
                    $points = $expectation['points'] === null ? '–' : $expectation['points'];
                    $section->addText($mark.' '.$expectation['text'].' ('.$points.' / '.$expectation['max_points'].' VP)', 'resultBody', ['spaceAfter' => 40]);
                }
            }

            $section->addText('Bei dieser LSE wurden folgende Kompetenzen getestet:', 'resultBody', ['spaceBefore' => 260, 'spaceAfter' => 120]);
            $table = $section->addTable('resultCompetencies');
            foreach ($report['competencies'] as $competency) {
                $table->addRow();
                $table->addCell(6900)->addText($competency['title'], 'resultBody');
                $table->addCell(900)->addText($competency['percentage'].' %', 'resultBody', ['alignment' => 'right']);
                $table->addCell(3000)->addText(str_repeat('█', max(1, (int) ceil($competency['percentage'] / 10))), 'resultBody', ['color' => '5B9BD5']);
            }

            $section->addText('Insgesamt hast du '.$report['percentage'].'% der möglichen Leistung erreicht.', 'resultBody', ['spaceBefore' => 260]);
            $section->addText('Für diese LSE erhältst du die Note '.($report['grade'] ?: '–').'.', 'resultBody', ['spaceBefore' => 180]);
            $section->addText($report['place'].', '.$report['date'].'    '.$report['author'], 'resultSmall', ['alignment' => 'right', 'spaceBefore' => 420]);
            if ($index < count($document->reports) - 1) {
                $section->addPageBreak();
            }
        }

        return $word;
    }
}
