<?php

namespace App\Documents\Templates;

use App\Documents\Document;
use App\Documents\DocumentTemplate;
use App\Documents\ParentLetterDocument;
use InvalidArgumentException;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Tab;

final class ParentLetterTemplate implements DocumentTemplate
{
    private const COMIC = 'Comic Neue';

    private const ATKINSON = 'Atkinson Hyperlegible Next';

    private const QR_SIZE_PX = 151;

    public function key(): string
    {
        return 'parent-letter.default';
    }

    public function render(Document $document): PhpWord
    {
        if (! $document instanceof ParentLetterDocument) {
            throw new InvalidArgumentException('Das Elternbrief-Template benötigt ein ParentLetterDocument.');
        }

        $word = new PhpWord;
        $profile = $document->layoutProfile();
        $word->setDefaultFontName($profile->fontFamily);
        $word->setDefaultFontSize($profile->bodyFontSize);
        $word->addFontStyle('parentLetterHeading', ['name' => $profile->headingFontFamily, 'size' => $profile->headingFontSize, 'bold' => true]);
        $word->addFontStyle('parentLetterBody', ['name' => $profile->fontFamily, 'size' => $profile->headerBand ? $profile->bodyFontSize : 11]);
        $word->addParagraphStyle('parentLetterHeaderBand', [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'shading' => ['fill' => 'D9D9D9'],
            'tabs' => [new Tab(Tab::TAB_STOP_LEFT, 0), new Tab(Tab::TAB_STOP_RIGHT, 10000)],
        ]);
        $word->addParagraphStyle('parentLetterSection', ['spaceBefore' => 240, 'spaceAfter' => 80]);
        $word->addTableStyle('parentLetterSchedule', ['borderSize' => 6, 'borderColor' => 'B7B7B7', 'cellMargin' => 80]);
        $section = $word->addSection(['marginTop' => 900, 'marginRight' => 900, 'marginBottom' => 900, 'marginLeft' => 900]);
        if ($profile->headerBand) {
            $this->addPageHeader($section->addHeader(Header::FIRST), $document->title);
            $this->addPageHeader($section->addHeader(), $document->title);
        }
        $footer = $section->addFooter();
        $footerFont = ['name' => self::ATKINSON, 'size' => 8, 'color' => '808080'];
        $footerRun = $footer->addTextRun([
            'tabs' => [new Tab(Tab::TAB_STOP_LEFT, 0), new Tab(Tab::TAB_STOP_RIGHT, 10000)],
            'spaceBefore' => 0,
            'spaceAfter' => 0,
        ]);
        $footerRun->addText('Elternbrief vom '.$document->letterDate, $footerFont);
        $footerRun->addText("\tSeite ", $footerFont);
        $footerRun->addField('PAGE', [], ['PreserveFormat'], null, $footerFont);

        if (! $profile->headerBand) {
            $section->addText($document->title, 'parentLetterHeading', ['spaceAfter' => 120]);
        }
        $section->addText($document->school.' · '.$document->group, 'parentLetterBody', ['spaceAfter' => 280]);
        if ($document->place !== null && $document->letterDate !== null) {
            $section->addText($document->place.', '.$document->letterDate, 'parentLetterBody', ['alignment' => 'right', 'spaceAfter' => 280]);
        }
        $this->addParagraphs($section, $document->introduction, 280);

        $section->addText('Kompetenzen', 'parentLetterHeading', ['spaceBefore' => 240, 'spaceAfter' => 80]);
        $section->addText('In dieser Unterrichtseinheit arbeiten wir an folgenden Kompetenzen aus dem Lehrplan. Die Schüler:innen können:', 'parentLetterBody', ['spaceAfter' => 80]);
        foreach ($document->contentCompetencies as $competency) {
            $section->addText('• '.$competency, 'parentLetterBody', ['indentation' => ['left' => 720, 'hanging' => 360], 'spaceAfter' => 0]);
        }
        $section->addText('Dabei üben wir folgende praktischen Kompetenzen ein. Die Schüler:innen können:', 'parentLetterBody', ['spaceBefore' => 160, 'spaceAfter' => 80]);
        foreach ($document->processCompetencies as $competency) {
            $section->addText('• '.$competency, 'parentLetterBody', ['indentation' => ['left' => 720, 'hanging' => 360], 'spaceAfter' => 0]);
        }

        $section->addText('Geplante Termine und Themen', 'parentLetterHeading', ['spaceBefore' => 240, 'spaceAfter' => 80]);
        $table = $section->addTable('parentLetterSchedule');
        $table->addRow();
        $table->addCell(2400)->addText('Datum', 'parentLetterBody');
        $table->addCell(1800)->addText('Zeit', 'parentLetterBody');
        $table->addCell(7000)->addText('Thema', 'parentLetterBody');
        foreach ($document->scheduledLessons as $scheduledLesson) {
            $table->addRow();
            $table->addCell(2400)->addText($scheduledLesson['date'], 'parentLetterBody');
            $table->addCell(1800)->addText($scheduledLesson['time'].' Uhr', 'parentLetterBody');
            $table->addCell(7000)->addText($scheduledLesson['title'], 'parentLetterBody');
        }

        if ($document->publicUrl !== null && $document->qrPng !== null) {
            $section->addText('Materialien online', 'parentLetterHeading', ['spaceBefore' => 240, 'spaceAfter' => 80]);
            $section->addText('Auf der Online-Seite zur Unterrichtseinheit finden Sie ergänzende Materialien und Links. Sie können die Seite über den QR-Code oder direkt über folgende Adresse aufrufen:', 'parentLetterBody');
            $section->addText($document->publicUrl, 'parentLetterBody', ['spaceAfter' => 80]);
            $section->addImage($document->qrPng, ['width' => self::QR_SIZE_PX, 'height' => self::QR_SIZE_PX, 'alignment' => 'left']);
        }

        $section->addText('Gerne dürfen Sie sich bei Fragen an mich wenden.', 'parentLetterBody', ['spaceBefore' => 240]);
        $section->addText('Herzliche Grüße,', 'parentLetterBody', ['spaceBefore' => 240, 'spaceAfter' => 0]);
        if ($document->creator !== '') {
            $section->addText($document->creator, 'parentLetterBody');
        }
        if ($document->contacts !== []) {
            $section->addText('Kontaktmöglichkeiten', 'parentLetterHeading', ['spaceBefore' => 240, 'spaceAfter' => 80]);
            foreach ($document->contacts as $contact) {
                $section->addText($contact['label'].': '.$contact['value'], 'parentLetterBody', ['spaceAfter' => 0]);
            }
        }

        return $word;
    }

    private function addParagraphs($section, string $text, int $spaceAfter): void
    {
        foreach (preg_split('/(?:\r?\n){2,}/', trim($text)) ?: [] as $paragraph) {
            $section->addText($paragraph, 'parentLetterBody', ['spaceAfter' => $spaceAfter]);
        }
    }

    private function addPageHeader(Header $header, string $title): void
    {
        $header->addText($title, 'parentLetterHeading', 'parentLetterHeaderBand');
    }
}
