<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves the canonical presentation of a competency relation.
 *
 * TeachingUnitCompetency is the assignment record. Its wording comes from an
 * optional local wording or the official education plan, with level variants
 * as an additional source. Consumers should use the returned presentation
 * instead of guessing which relation contains the text.
 */
class CompetencyResolver
{
    public function present(Model $competency): array
    {
        $text = $this->text($competency);
        $identifier = $this->identifier($competency);

        return [
            'id' => $competency->getKey(),
            'kind' => $this->kind($competency),
            'identifier' => $identifier,
            'text' => $text,
            'label' => $identifier && $text ? $identifier.' – '.$text : ($text ?: $identifier),
        ];
    }

    public function number(Model $competency): string
    {
        return $this->identifier($competency);
    }

    public function numberAndText(Model $competency): string
    {
        return collect([$this->number($competency), $this->text($competency)])
            ->filter()
            ->implode(' – ');
    }

    public function textOnly(Model $competency): string
    {
        return $this->text($competency);
    }

    public function textForLevel(Model $competency, ?string $level): string
    {
        $plan = $this->related($competency, 'educationPlanCompetency') ?? $competency;
        $variants = collect($this->relatedMany($plan, 'variants'));
        $variant = $variants->first(fn ($variant): bool => strtoupper((string) $variant->level?->external_identifier) === strtoupper((string) $level));

        if (! $variant && $level && $variants->count() === 3) {
            $variant = $variants->values()->get(array_search(strtoupper($level), ['G', 'M', 'E'], true));
        }

        return $variant?->text ? $this->clean($variant->text, $this->identifier($competency)) : $this->text($competency);
    }

    public function duKannst(Model $competency, bool $numberInParentheses = true, bool $removeParentheses = true): string
    {
        $text = $this->text($competency);
        if ($removeParentheses) {
            do {
                $withoutParentheses = preg_replace('/\s*\([^()]*\)/u', '', $text, -1, $count);
                $text = trim((string) $withoutParentheses);
            } while ($count > 0);
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $number = $this->number($competency);

        return trim('Du kannst '.$text.($numberInParentheses && $number ? ' ('.$number.')' : ''));
    }

    public function kind(Model $competency): string
    {
        $plan = $this->related($competency, 'educationPlanCompetency');

        return $plan?->area?->kind ?? 'content';
    }

    public function text(Model $competency): string
    {
        $plan = $this->related($competency, 'educationPlanCompetency') ?? $competency;
        $identifier = $this->identifier($competency);
        $variants = collect($this->relatedMany($competency, 'variants'));
        if ($variants->isEmpty()) {
            $variants = collect($this->relatedMany($plan, 'variants'));
        }
        $variants = $variants
            ->pluck('text')
            ->filter()
            ->implode(' / ');

        return (string) ($this->clean($competency->local_wording, $identifier)
            ?: $this->clean($plan?->text, $identifier)
            ?: $variants
            ?: '');
    }

    public function identifier(Model $competency): string
    {
        $plan = $this->related($competency, 'educationPlanCompetency');
        $raw = $competency->external_identifier
            ?: $competency->number
            ?: $plan?->external_identifier
            ?: $plan?->number;

        return $this->formatIdentifier($raw);
    }

    private function related(Model $model, string $relation): ?Model
    {
        $value = $model->getRelationValue($relation);

        return $value instanceof Model ? $value : null;
    }

    private function relatedMany(Model $model, string $relation)
    {
        $value = $model->getRelationValue($relation);

        return $value instanceof Collection ? $value : collect();
    }

    private function formatIdentifier(mixed $value): string
    {
        $identifier = trim((string) ($value ?? ''));

        return preg_replace('/^(\d+(?:\.\d+){2})\.(\d+)$/', '$1 ($2)', $identifier) ?: $identifier;
    }

    private function clean(mixed $value, string $identifier = ''): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        $identifierPattern = $identifier !== '' ? preg_quote($identifier, '/').'|'.preg_quote((string) preg_replace('/\s*\(\d+\)$/', '', $identifier), '/').'|'.preg_quote((string) preg_replace('/^.*(\(\d+\))$/', '$1', $identifier), '/') : '(?:\d+(?:\.\d+){2,4}(?:\s*\(\d+\))?)';

        return trim((string) preg_replace('/^\s*'.$identifierPattern.'\s*(?:[-–:]\s*)?[GME]?\s*/iu', '', $text));
    }
}
