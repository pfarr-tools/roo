<?php

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
