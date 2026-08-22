<?php

namespace App\Services;

use App\Documents\Document;
use App\Documents\DocumentOutputFormat;
use App\Documents\DocumentTemplateRegistry;
use PhpOffice\PhpWord\IOFactory;

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

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public function renderToFile(Document $document, DocumentOutputFormat $format, string $path): void
    {
        $phpWord = $this->templates->get($document->templateKey())->render($document);
        IOFactory::createWriter($phpWord, $format->writerName())->save($path);
    }
}
