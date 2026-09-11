<?php

namespace App\Search;

trait SearchableFields
{
    public function searchablePayload(): array
    {
        $fields = collect($this->searchableFields())
            ->mapWithKeys(fn (string $field): array => [$field => $this->getAttribute($field)])
            ->all();
        $searchText = collect($fields)
            ->flatMap(fn (mixed $value): array => $this->flattenSearchableValues($value))
            ->implode(' ');

        return ['id' => (string) $this->getKey(), 'user_id' => $this->user_id] + $fields + [
            'search_text' => $searchText,
        ];
    }

    /** @return list<string> */
    private function flattenSearchableValues(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->flatMap(fn (mixed $item): array => $this->flattenSearchableValues($item))->all();
        }

        return is_scalar($value) && filled($value) ? [(string) $value] : [];
    }

    abstract protected function searchableFields(): array;
}
