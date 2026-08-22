<?php

namespace App\Documents;

use InvalidArgumentException;

class DocumentTemplateRegistry
{
    /** @var array<string, DocumentTemplate> */
    private array $templates = [];

    /** @param iterable<DocumentTemplate> $templates */
    public function __construct(iterable $templates = [])
    {
        foreach ($templates as $template) {
            $this->register($template);
        }
    }

    public function register(DocumentTemplate $template): self
    {
        $key = trim($template->key());
        if ($key === '') {
            throw new InvalidArgumentException('Ein Dokumenttemplate benötigt einen Schlüssel.');
        }

        $this->templates[$key] = $template;

        return $this;
    }

    public function get(string $key): DocumentTemplate
    {
        return $this->templates[$key]
            ?? throw new InvalidArgumentException("Dokumenttemplate [{$key}] ist nicht registriert.");
    }
}
