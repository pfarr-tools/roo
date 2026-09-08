<?php

namespace App\Search;

trait SearchableFields
{
    public function searchablePayload(): array
    {
        $fields = collect($this->searchableFields())
            ->mapWithKeys(fn (string $field): array => [$field => $this->getAttribute($field)])
            ->all();

        return ['id' => (string) $this->getKey(), 'organization_id' => $this->organization_id] + $fields + [
            'search_text' => collect($fields)->flatten()->filter(fn ($value): bool => is_scalar($value) && filled($value))->implode(' '),
        ];
    }

    abstract protected function searchableFields(): array;
}
