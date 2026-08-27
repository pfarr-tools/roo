<?php

namespace App\Documents\Templates;

use App\Documents\AssessmentDocument;
use App\Documents\Document;
use App\Documents\DocumentTemplate;
use InvalidArgumentException;
use PfarrTools\RooRuling\PhpWord\RulingRenderer;
use PfarrTools\RooRuling\RulingDefinition;
use PfarrTools\RooRuling\RulingPreset;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Cell as CellStyle;

final class PrimarySchoolAssessmentTemplate implements DocumentTemplate
{
    private const COMIC = 'Comic Neue';

    private const ATKINSON = 'Atkinson Hyperlegible Next';

    private const CONTENT_WIDTH_MM = 175.0;

    public function key(): string
    {
        return 'assessment.primary-school-lower-secondary';
    }

    public function render(Document $document): PhpWord
    {
        if (! $document instanceof AssessmentDocument) {
            throw new InvalidArgumentException('Das Grundschul-/Unterstufen-Template benötigt ein AssessmentDocument.');
        }

        $word = new PhpWord;
        $word->setDefaultFontName(self::ATKINSON);
        $word->setDefaultFontSize(14);
        $word->addFontStyle('assessmentPageHeading', [
            'name' => self::COMIC,
            'size' => 24,
            'bold' => true,
        ]);
        $word->addParagraphStyle('imageMatchingSolution', [
            'alignment' => 'center',
            'spaceAfter' => 240,
        ]);
        $section = $word->addSection([
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 567,
            'marginRight' => 567,
            'marginBottom' => 567,
            'marginLeft' => 1134,
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
        ]);

        $this->addPageHeader($section->addHeader(Header::FIRST), $document->title, true);
        $this->addPageHeader($section->addHeader(), $document->title, false);
        $this->addFooter($section, $document->metadata);
        foreach ($document->tasks as $number => $task) {
            $this->addTask($section, $task, $number + 1, $document->gradeLevel);
        }
        $section->addText('', ['name' => self::ATKINSON, 'size' => 14], ['spaceBefore' => 0, 'spaceAfter' => 0]);

        return $word;
    }

    private function addPageHeader(Header $header, string $title, bool $includeName): void
    {
        $table = $header->addTable([
            'width' => 10000,
            'layout' => 'fixed',
            'borderSize' => 0,
            'cellMarginLeft' => 0,
            'cellMarginRight' => 0,
        ]);
        $row = $table->addRow();
        $row->addCell($includeName ? 7000 : 10000, ['borderSize' => 0])->addText($title, 'assessmentPageHeading', ['spaceAfter' => 0]);
        if ($includeName) {
            $row->addCell(3000, ['borderSize' => 0, 'cellMarginLeft' => 283])->addText('Name:', 'assessmentPageHeading', ['spaceAfter' => 0, 'alignment' => 'right']);
        }

        $header->addShape('line', [
            'points' => '0,0 10000,0',
            'width' => 10000,
            'height' => 1,
            'outline' => ['color' => '000000', 'weight' => 1],
        ]);
        $header->addTextBreak(1);
    }

    /** @param array<string, mixed> $metadata */
    private function addFooter(Section $section, array $metadata): void
    {
        $footer = $section->addFooter();
        $font = [
            'name' => self::ATKINSON,
            'size' => 6,
            'color' => '808080',
        ];
        $copyright = $footer->addTextRun([
            'alignment' => 'right',
            'spaceBefore' => 0,
            'spaceAfter' => 0,
        ]);
        $copyrightParts = array_values(array_filter([
            '©',
            (string) ($metadata['year'] ?? date('Y')),
            $metadata['author'] ?? null,
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
        $copyright->addText(implode(' ', array_map('trim', $copyrightParts)), $font);

        $details = $footer->addTextRun([
            'alignment' => 'right',
            'spaceBefore' => 0,
            'spaceAfter' => 0,
        ]);
        $elements = array_values(array_filter([
            $metadata['school'] ?? null,
            $metadata['school_year'] ?? null,
            $metadata['group'] ?? null,
            $metadata['footer_title'] ?? null,
            $metadata['date'] ?? null,
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== ''));

        foreach ($elements as $index => $element) {
            if ($index > 0) {
                $details->addText(' · ', $font);
            }
            $details->addText(trim($element), $font);
        }
        if ($elements !== []) {
            $details->addText(' · ', $font);
        }
        $details->addText('Seite ', $font);
        $details->addField('PAGE', [], ['PreserveFormat'], null, $font);
    }

    /** @param array<string, mixed> $task */
    private function addTask(Section $section, array $task, int $number, string $gradeLevel): void
    {
        $markerId = (string) ($task['task_id'] ?? $number);
        $section->addText('ROO_TASK_START_'.$markerId, ['name' => self::ATKINSON, 'size' => 1, 'color' => 'FFFFFF'], ['spaceBefore' => 0, 'spaceAfter' => 0]);
        $points = (int) ($task['max_points'] ?? 0);
        $instruction = (string) ($task['content']['prompt'] ?? $task['title'] ?? '');
        $section->addText($number.'. '.$instruction.' ('.$points.' VP)', ['name' => self::COMIC, 'size' => 14], ['spaceBefore' => 180, 'spaceAfter' => 120]);

        $content = is_array($task['content'] ?? null) ? $task['content'] : [];
        if (($task['task_type'] ?? '') === 'checkbox') {
            $this->addCheckboxTask($section, $content);
        } elseif (($task['task_type'] ?? '') === 'free_text') {
            $this->addFreeTextImages($section, $content);
            $this->addOptionalReadingText($section, $content);
            $this->addImageCredits($section, $content);
        } elseif (($task['task_type'] ?? '') === 'image_matching') {
            $this->addImageMatchingTask($section, $content);
            $this->addImageCredits($section, $content);
        } elseif (($task['task_type'] ?? '') === 'image_labeling') {
            $this->addImageLabelingSolutions($section, $content);
            $this->addImageLabelingTask($section, $content, $markerId);
            $this->addImageCredits($section, $content);
        } elseif (($task['task_type'] ?? '') === 'subtask_table') {
            $this->addSubtaskTable($section, $content);
        } elseif (($task['task_type'] ?? '') === 'image_answer_table') {
            $this->addImageAnswerTable($section, $content);
            $this->addImageCredits($section, $content);
        } elseif (($task['task_type'] ?? '') === 'heading_table') {
            $this->addHeadingTable($section, $content);
        } elseif (($task['task_type'] ?? '') === 'matching_table') {
            $this->addMatchingTable($section, $content);
        }

        if (! in_array($task['task_type'] ?? '', ['checkbox', 'image_matching', 'image_labeling', 'subtask_table', 'image_answer_table', 'heading_table', 'matching_table'], true)) {
            $this->addWritingLines($section, $content, $gradeLevel);
        }
        $section->addText('ROO_TASK_END_'.$markerId, ['name' => self::ATKINSON, 'size' => 1, 'color' => 'FFFFFF'], ['spaceBefore' => 0, 'spaceAfter' => 40]);
    }

    /** @param array<string, mixed> $content */
    private function addFreeTextImages(Section $section, array $content): void
    {
        $images = collect($content['images'] ?? [])
            ->filter(fn ($image): bool => is_array($image) && ! empty($image['path']) && is_file($image['path']))
            ->values();

        if ($images->isEmpty()) {
            return;
        }

        $widthCm = min(4.0, max(1.5, (float) ($content['image_width_cm'] ?? 3.0)));
        $widthPx = (int) round($widthCm * 37.7952756);
        $columnCount = min(3, $images->count());
        $cellWidthTwips = (int) floor(self::CONTENT_WIDTH_MM * 56.6929 / $columnCount);
        $table = $section->addTable([
            'width' => self::CONTENT_WIDTH_MM * 56.6929,
            'layout' => 'fixed',
        ]);

        foreach ($images->values()->chunk(3) as $imageRow) {
            $imageRow = $imageRow->values();
            $row = $table->addRow();
            foreach (range(0, $columnCount - 1) as $index) {
                $cell = $row->addCell($cellWidthTwips, ['borderSize' => 0, 'valign' => 'top']);
                $image = $imageRow->get($index);
                if (! is_array($image)) {
                    continue;
                }

                $style = ['width' => $widthPx, 'alignment' => 'center'];
                $dimensions = @getimagesize($image['path']);
                if (is_array($dimensions) && $dimensions[0] > 0) {
                    $style['height'] = (int) round($widthPx * $dimensions[1] / $dimensions[0]);
                }
                $cell->addImage($image['path'], $style);
            }
        }
    }

    /** @param array<string, mixed> $content */
    private function addOptionalReadingText(Section $section, array $content): void
    {
        $text = trim((string) ($content['optional_reading_text'] ?? ''));
        if ($text === '') {
            return;
        }

        $section->addText($text, ['name' => self::ATKINSON, 'size' => 14, 'bold' => false], ['spaceBefore' => 120, 'spaceAfter' => 120]);
    }

    /** @param array<string, mixed> $content */
    private function addImageMatchingTask(Section $section, array $content): void
    {
        $widthCm = min(4.0, max(1.5, (float) ($content['image_width_cm'] ?? 3.0)));
        $widthPx = (int) round($widthCm * 37.7952756);
        $imageWidthTwips = (int) round($widthCm * 1440 / 2.54);
        $images = collect($content['images'] ?? [])
            ->filter(fn ($image): bool => is_array($image) && ! empty($image['path']) && is_file($image['path']))
            ->values();

        if ($images->isEmpty()) {
            return;
        }

        $solutions = $images->pluck('answer')->filter(fn ($answer): bool => trim((string) $answer) !== '')->map(fn ($answer): string => trim((string) $answer))->values()->all();
        shuffle($solutions);
        $rowCount = (int) ceil($images->count() / 2);
        $table = $section->addTable([
            'width' => self::CONTENT_WIDTH_MM * 56.6929,
            'layout' => 'fixed',
            'borderSize' => 0,
            'cellMargin' => 80,
            'cellMarginBottom' => 113,
        ]);

        foreach (range(0, $rowCount - 1) as $rowIndex) {
            $row = $table->addRow();
            $this->addImageMatchingCell($row->addCell($imageWidthTwips, ['borderSize' => 0]), $images->get($rowIndex * 2), $widthPx, $imageWidthTwips);
            $middle = $row->addCell(self::CONTENT_WIDTH_MM * 56.6929 - ($imageWidthTwips * 2), [
                'borderSize' => 0,
                'valign' => 'center',
                'vMerge' => $rowIndex === 0 ? CellStyle::VMERGE_RESTART : CellStyle::VMERGE_CONTINUE,
            ]);
            if ($rowIndex === 0) {
                foreach ($solutions as $solution) {
                    $middle->addText($solution, ['name' => self::ATKINSON, 'size' => 14], 'imageMatchingSolution');
                }
            }
            $this->addImageMatchingCell($row->addCell($imageWidthTwips, ['borderSize' => 0]), $images->get($rowIndex * 2 + 1), $widthPx, $imageWidthTwips);
        }
        $section->addTextBreak(1);
    }

    private function addImageMatchingCell(Cell $cell, mixed $image, int $widthPx, int $widthTwips): void
    {
        if (! is_array($image)) {
            return;
        }

        $inner = $cell->addTable(['width' => $widthTwips, 'layout' => 'fixed', 'borderSize' => 4, 'borderColor' => '000000', 'cellMargin' => 40, 'cellMarginBottom' => 113]);
        $innerCell = $inner->addRow()->addCell($widthTwips, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'center', 'cellMarginBottom' => 113]);
        $imageStyle = ['width' => $widthPx, 'alignment' => 'center'];
        $dimensions = @getimagesize($image['path']);
        if (is_array($dimensions) && ($dimensions[0] ?? 0) > 0 && ($dimensions[1] ?? 0) > 0) {
            $imageStyle['height'] = (int) round($widthPx * $dimensions[1] / $dimensions[0]);
        }
        $innerCell->addImage($image['path'], $imageStyle);
    }

    /** @param array<string, mixed> $content */
    private function addImageLabelingSolutions(Section $section, array $content): void
    {
        if (empty($content['show_solutions'])) {
            return;
        }

        $solutions = collect($content['image']['labels'] ?? [])
            ->map(fn ($label): string => trim((string) ($label['solution'] ?? '')))
            ->filter()
            ->unique()
            ->values();
        if ($solutions->isEmpty()) {
            return;
        }

        $section->addText('Lösungstexte', ['name' => self::COMIC, 'size' => 14, 'bold' => true], ['spaceBefore' => 0, 'spaceAfter' => 40]);
        $section->addText(implode(' · ', $solutions->all()), ['name' => self::ATKINSON, 'size' => 14, 'bold' => false], ['spaceBefore' => 0, 'spaceAfter' => 120]);
    }

    /** @param array<string, mixed> $content */
    private function addImageLabelingTask(Section $section, array $content, string $markerId): void
    {
        $image = $content['image'] ?? null;
        if (! is_array($image) || ! is_file((string) ($image['path'] ?? ''))) {
            return;
        }

        $widthCm = min(8.0, max(4.0, (float) ($content['image_label_width_cm'] ?? 6.0)));
        $widthPx = (int) round($widthCm * 37.7952756);
        $run = $section->addTextRun(['spaceBefore' => 0, 'spaceAfter' => 0]);
        $run->addText('ROO_IMAGE_LABELING_'.$markerId, ['name' => self::ATKINSON, 'size' => 1, 'color' => 'FFFFFF']);
        $run->addImage($image['path'], ['width' => $widthPx, 'alignment' => 'center']);
        $section->addTextBreak(1);
    }

    /** @param array<string, mixed> $content */
    private function addImageCredits(Section $section, array $content): void
    {
        $imageEntries = $content['images'] ?? [];
        if (isset($content['image']) && is_array($content['image'])) {
            $imageEntries[] = $content['image'];
        }
        $usedCredits = collect($imageEntries)
            ->filter(fn ($image): bool => is_array($image) && trim((string) ($image['copyright'] ?? '')) !== '')
            ->map(fn ($image): string => trim((string) $image['copyright']));
        $credits = $usedCredits
            ->unique()
            ->values();

        if ($credits->isEmpty()) {
            return;
        }

        $label = $usedCredits->count() === 1 ? 'Bild: ' : 'Bilder: ';
        $section->addText($label.implode('; ', $credits->all()), [
            'name' => self::ATKINSON,
            'size' => 6,
            'bold' => false,
            'color' => '808080',
        ], ['spaceBefore' => 0, 'spaceAfter' => 120]);
    }

    /** @param array<string, mixed> $content */
    private function addCheckboxTask(Section $section, array $content): void
    {
        foreach (array_values($content['options'] ?? []) as $option) {
            $text = is_array($option) ? ($option['text'] ?? '') : (string) $option;
            $section->addText('☐ '.$text, ['name' => self::ATKINSON, 'size' => 14], ['spaceAfter' => 80]);
        }
        $section->addTextBreak(1);
    }

    /** @param array<string, mixed> $content */
    private function addSubtaskTable(Section $section, array $content): void
    {
        $subtasks = collect($content['subtasks'] ?? [])->filter(fn ($subtask): bool => is_array($subtask))->values();
        if ($subtasks->isEmpty()) {
            return;
        }

        if (! empty($content['show_solutions'])) {
            $solutions = $subtasks->pluck('solution')->map(fn ($solution): string => trim((string) $solution))->filter()->values();
            if ($solutions->isNotEmpty()) {
                $section->addText('Lösungsvorschläge', ['name' => self::COMIC, 'size' => 14, 'bold' => true], ['spaceBefore' => 0, 'spaceAfter' => 40]);
                $section->addText(implode(' · ', $solutions->all()), ['name' => self::ATKINSON, 'size' => 14], ['spaceBefore' => 0, 'spaceAfter' => 120]);
            }
        }

        $table = $section->addTable([
            'width' => self::CONTENT_WIDTH_MM * 56.6929,
            'layout' => 'fixed',
            'borderSize' => 4,
            'borderColor' => '000000',
            'cellMargin' => 80,
        ]);

        foreach ($subtasks as $subtask) {
            $row = $table->addRow();
            $row->addCell(self::CONTENT_WIDTH_MM * 56.6929 * 0.30, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'top'])
                ->addText((string) ($subtask['label'] ?? ''), ['name' => self::ATKINSON, 'size' => 14], ['spaceAfter' => 0]);
            $answerCell = $row->addCell(self::CONTENT_WIDTH_MM * 56.6929 * 0.70, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'top']);
            $lines = max(1, (int) ($subtask['lines'] ?? 3));
            $answerTable = $answerCell->addTable(['width' => self::CONTENT_WIDTH_MM * 56.6929 * 0.70, 'layout' => 'fixed', 'borderSize' => 0, 'cellMargin' => 0]);
            for ($line = 0; $line < $lines; $line++) {
                $answerTable->addRow(360)->addCell(null, [
                    'borderSize' => 0,
                    'borderBottomSize' => ! empty($content['lineated']) ? 4 : 0,
                    'borderBottomColor' => '000000',
                    'borderBottomStyle' => 'single',
                ])->addText('', ['name' => self::ATKINSON, 'size' => 14], ['spaceAfter' => 0]);
            }
        }
        $section->addTextBreak(1);
    }

    /** @param array<string, mixed> $content */
    private function addImageAnswerTable(Section $section, array $content): void
    {
        $subtasks = collect($content['subtasks'] ?? [])->filter(fn ($subtask): bool => is_array($subtask))->values();
        $images = collect($content['images'] ?? [])->filter(fn ($image): bool => is_array($image) && ! empty($image['path']) && is_file($image['path']))->keyBy(fn (array $image): string => (string) ($image['identifier'] ?? ''));
        if ($subtasks->isEmpty() || $images->isEmpty()) {
            return;
        }

        if (! empty($content['show_solutions'])) {
            $solutions = $subtasks->pluck('solution')->map(fn ($solution): string => trim((string) $solution))->filter()->values();
            if ($solutions->isNotEmpty()) {
                $section->addText('Lösungsvorschläge', ['name' => self::COMIC, 'size' => 14, 'bold' => true], ['spaceBefore' => 0, 'spaceAfter' => 40]);
                $section->addText(implode(' · ', $solutions->all()), ['name' => self::ATKINSON, 'size' => 14], ['spaceBefore' => 0, 'spaceAfter' => 120]);
            }
        }

        $widthCm = min(4.0, max(1.5, (float) ($content['image_width_cm'] ?? 3.0)));
        $imageWidthTwips = (int) round($widthCm * 1440 / 2.54);
        $tableWidth = self::CONTENT_WIDTH_MM * 56.6929;
        $answerWidthTwips = max(1, (int) round($tableWidth - $imageWidthTwips));
        $table = $section->addTable(['width' => $tableWidth, 'layout' => 'fixed', 'borderSize' => 4, 'borderColor' => '000000', 'cellMargin' => 80]);

        foreach ($subtasks as $subtask) {
            $image = $images->get((string) ($subtask['image_identifier'] ?? ''));
            $row = $table->addRow();
            $imageCell = $row->addCell($imageWidthTwips, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'top']);
            if (is_array($image)) {
                $widthPx = (int) round($widthCm * 37.7952756);
                $style = ['width' => $widthPx, 'alignment' => 'center'];
                $dimensions = @getimagesize($image['path']);
                if (is_array($dimensions) && ($dimensions[0] ?? 0) > 0 && ($dimensions[1] ?? 0) > 0) {
                    $style['height'] = (int) round($widthPx * $dimensions[1] / $dimensions[0]);
                }
                $imageCell->addImage($image['path'], $style);
            }
            $answerCell = $row->addCell($answerWidthTwips, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'top']);
            $lines = max(1, (int) ($subtask['lines'] ?? 3));
            $answerTable = $answerCell->addTable(['width' => $answerWidthTwips, 'layout' => 'fixed', 'borderSize' => 0, 'cellMargin' => 0]);
            for ($line = 0; $line < $lines; $line++) {
                $answerTable->addRow(360)->addCell(null, ['borderSize' => 0, 'borderBottomSize' => ! empty($content['lineated']) ? 4 : 0, 'borderBottomColor' => '000000', 'borderBottomStyle' => 'single'])->addText('', ['name' => self::ATKINSON, 'size' => 14], ['spaceAfter' => 0]);
            }
        }
        $section->addTextBreak(1);
    }

    /** @param array<string, mixed> $content */
    private function addHeadingTable(Section $section, array $content): void
    {
        $columns = collect($content['columns'] ?? [])->filter(fn ($cell): bool => is_array($cell))->values();
        $rows = collect($content['rows'] ?? [])->filter(fn ($row): bool => is_array($row))->values();
        if ($columns->isEmpty() || $rows->isEmpty()) {
            return;
        }

        if (! empty($content['show_solutions'])) {
            $solutions = $columns
                ->merge($rows->flatMap(fn (array $row): array => array_merge(count($rows) > 1 ? [$row['header'] ?? []] : [], $row['cells'] ?? [])))
                ->pluck('solution')
                ->map(fn ($solution): string => trim((string) $solution))
                ->filter()
                ->values();
            if ($solutions->isNotEmpty()) {
                $section->addText('Lösungsvorschläge', ['name' => self::COMIC, 'size' => 14, 'bold' => true], ['spaceBefore' => 0, 'spaceAfter' => 40]);
                $section->addText(implode(' · ', $solutions->all()), ['name' => self::ATKINSON, 'size' => 14], ['spaceBefore' => 0, 'spaceAfter' => 120]);
            }
        }

        $hasRowHeaders = $rows->count() > 1;
        $columnCount = $columns->count() + ($hasRowHeaders ? 1 : 0);
        $tableWidth = self::CONTENT_WIDTH_MM * 56.6929;
        $cellWidth = $tableWidth / $columnCount;
        $table = $section->addTable(['width' => $tableWidth, 'layout' => 'fixed', 'borderSize' => 4, 'borderColor' => '000000', 'cellMargin' => 80]);
        $headerRow = $table->addRow();
        if ($hasRowHeaders) {
            $this->addHeadingTableCell($headerRow->addCell($cellWidth, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'top']), ['heading' => ''], 1, true, true);
        }
        foreach ($columns as $column) {
            $this->addHeadingTableCell($headerRow->addCell($cellWidth, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'top']), $column, 1, true, true);
        }

        foreach ($rows as $row) {
            $tableRow = $table->addRow();
            if ($hasRowHeaders) {
                $this->addHeadingTableCell($tableRow->addCell($cellWidth, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'top']), $row['header'] ?? [], 1, false, true);
            }
            foreach (array_values($row['cells'] ?? []) as $cell) {
                $this->addHeadingTableCell($tableRow->addCell($cellWidth, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'top']), $cell, max(1, (int) ($row['lines'] ?? 3)), false, ! empty($content['lineated']));
            }
        }
        $section->addTextBreak(1);
    }

    /** @param array<string, mixed> $content */
    private function addMatchingTable(Section $section, array $content): void
    {
        $categories = collect($content['categories'] ?? [])->filter(fn ($category): bool => is_array($category))->values();
        $rows = collect($content['rows'] ?? [])->filter(fn ($row): bool => is_array($row))->values();
        if ($categories->isEmpty() || $rows->isEmpty()) {
            return;
        }

        $tableWidth = self::CONTENT_WIDTH_MM * 56.6929;
        $categoryWidth = min(1050, max(650, (int) round($tableWidth / max(4, $categories->count() + 1))));
        $textWidth = max(1, (int) round($tableWidth - $categoryWidth * $categories->count()));
        $table = $section->addTable(['width' => $tableWidth, 'layout' => 'fixed', 'borderSize' => 4, 'borderColor' => '000000', 'cellMargin' => 80]);

        $header = $table->addRow();
        $header->addCell($textWidth, ['borderSize' => 4, 'borderColor' => '000000'])->addText('', ['name' => self::ATKINSON, 'size' => 14]);
        foreach ($categories as $category) {
            $header->addCell($categoryWidth, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'center'])->addText((string) ($category['text'] ?? ''), ['name' => self::ATKINSON, 'size' => 14], ['align' => 'center']);
        }

        foreach ($rows as $row) {
            $tableRow = $table->addRow();
            $tableRow->addCell($textWidth, ['borderSize' => 4, 'borderColor' => '000000', 'valign' => 'top'])->addText((string) ($row['text'] ?? ''), ['name' => self::ATKINSON, 'size' => 14]);
            foreach ($categories as $category) {
                $tableRow->addCell($categoryWidth, ['borderSize' => 4, 'borderColor' => '000000'])->addText('', ['name' => self::ATKINSON, 'size' => 14]);
            }
        }
    }

    private function addHeadingTableCell(Cell $cell, array $definition, int $lines, bool $header, bool $lineated): void
    {
        $heading = trim((string) ($definition['heading'] ?? ''));
        if ($heading !== '') {
            $cell->addText($heading, ['name' => self::ATKINSON, 'size' => 14], ['spaceAfter' => 0]);

            return;
        }

        $answerTable = $cell->addTable(['layout' => 'fixed', 'borderSize' => 0, 'cellMargin' => 0]);
        for ($line = 0; $line < ($header ? 1 : $lines); $line++) {
            $answerTable->addRow(360)->addCell(null, [
                'borderSize' => 0,
                'borderBottomSize' => $header || $lineated ? 4 : 0,
                'borderBottomColor' => '000000',
                'borderBottomStyle' => 'single',
            ])->addText('', ['name' => self::ATKINSON, 'size' => 14], ['spaceAfter' => 0]);
        }
    }

    /** @param array<string, mixed> $content */
    private function addWritingLines(Section $section, array $content, string $gradeLevel): void
    {
        $count = max(1, (int) ($content['lines'] ?? 5));
        $ruling = ! empty($content['lineated']) ? $this->rulingForGrade($gradeLevel) : RulingPreset::Grade4Plus;

        $table = (new RulingRenderer)->render(
            section: $section,
            ruling: $this->visibleRuling($ruling->definition()),
            count: $count,
            widthMm: self::CONTENT_WIDTH_MM,
            fontStyle: ['name' => self::ATKINSON, 'size' => 14],
        );
        $this->makeRulingBordersPrintable($table);
        $section->addTextBreak(1);
    }

    private function visibleRuling(RulingDefinition $ruling): RulingDefinition
    {
        return new RulingDefinition(
            zonesMm: $ruling->zonesMm,
            gapMm: $ruling->gapMm,
            leftBorder: $ruling->leftBorder,
            rightBorder: $ruling->rightBorder,
            topBorder: $ruling->topBorder,
            lineColor: '000000',
            lineSize: 8,
            textZoneIndex: $ruling->textZoneIndex,
            lineIndexes: $ruling->lineIndexes,
            sideBorderZoneIndexes: $ruling->sideBorderZoneIndexes,
        );
    }

    private function makeRulingBordersPrintable(Table $table): void
    {
        foreach ($table->getRows() as $row) {
            foreach ($row->getCells() as $cell) {
                $style = $cell->getStyle();
                if ($style->getBorderBottomSize() > 0) {
                    $style->setBorderBottomStyle('single');
                    $style->setBorderBottomColor('000000');
                }
                if ($style->getBorderTopSize() > 0) {
                    $style->setBorderTopStyle('single');
                    $style->setBorderTopColor('000000');
                }
                if ($style->getBorderLeftSize() > 0) {
                    $style->setBorderLeftStyle('single');
                    $style->setBorderLeftColor('000000');
                }
                if ($style->getBorderRightSize() > 0) {
                    $style->setBorderRightStyle('single');
                    $style->setBorderRightColor('000000');
                }
            }
        }
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
