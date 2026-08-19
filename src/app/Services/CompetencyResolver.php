<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the canonical presentation of a competency relation.
 *
 * TeachingUnitCompetency is the assignment record. Its wording can come from
 * the local wording, the curriculum snapshot, the education plan, or a level
 * variant. Consumers should use the returned presentation instead of guessing
 * which relation contains the text.
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

    public function kind(Model $competency): string
    {
        $plan = $this->related($competency, 'educationPlanCompetency');
        $curriculum = $this->related($competency, 'curriculumCompetency');

        return $plan?->area?->kind ?? $curriculum?->competency_kind ?? 'content';
    }

    public function text(Model $competency): string
    {
        $plan = $this->related($competency, 'educationPlanCompetency') ?? $competency;
        $curriculum = $this->related($competency, 'curriculumCompetency');
        $curricula = $this->relatedMany($competency, 'curriculumCompetencies');
        $identifier = $this->identifier($competency);

        $curriculumText = $this->clean($curriculum?->text, $identifier)
            ?: $this->clean($curriculum?->raw_text, $identifier)
            ?: $this->displayText($curriculum?->display, $identifier);
        $variants = collect($this->relatedMany($competency, 'variants'))
            ->pluck('text')
            ->filter()
            ->implode(' / ');

        if (! $curriculumText && $curricula->isNotEmpty()) {
            $curriculumText = $curricula
                ->map(fn ($item) => $this->clean($item->text, $identifier) ?: $this->clean($item->raw_text, $identifier) ?: $this->displayText($item->display, $identifier))
                ->filter()
                ->first();
        }

        return (string) ($this->clean($competency->local_wording, $identifier)
            ?: $curriculumText
            ?: $this->clean($plan?->text, $identifier)
            ?: $variants
            ?: $this->clean($competency->text, $identifier)
            ?: $this->clean($competency->raw_text, $identifier)
            ?: $this->displayText($competency->display, $this->identifier($competency))
            ?: '');
    }

    public function identifier(Model $competency): string
    {
        $plan = $this->related($competency, 'educationPlanCompetency');
        $curriculum = $this->related($competency, 'curriculumCompetency');
        $raw = $competency->external_identifier
            ?: $competency->number
            ?: $plan?->external_identifier
            ?: $plan?->number
            ?: $curriculum?->external_identifier;

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

        return $value instanceof \Illuminate\Support\Collection ? $value : collect();
    }

    private function formatIdentifier(mixed $value): string
    {
        $identifier = trim((string) ($value ?? ''));

        return preg_replace('/^(\d+\.\d+\.\d+)\.(\d+)$/', '$1 ($2)', $identifier) ?: $identifier;
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

    private function displayText(mixed $value, string $identifier): string
    {
        $display = $this->clean($value);

        return $display && $display !== $identifier ? $display : '';
    }
}
