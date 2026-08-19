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

        $curriculumText = $this->clean($curriculum?->text)
            ?: $this->clean($curriculum?->raw_text)
            ?: $this->displayText($curriculum?->display, $this->identifier($competency));
        $variants = collect($this->relatedMany($competency, 'variants'))
            ->pluck('text')
            ->filter()
            ->implode(' / ');

        if (! $curriculumText && $curricula->isNotEmpty()) {
            $curriculumText = $curricula
                ->map(fn ($item) => $this->clean($item->text) ?: $this->clean($item->raw_text) ?: $this->displayText($item->display, $this->identifier($competency)))
                ->filter()
                ->first();
        }

        return (string) ($competency->local_wording
            ?: $curriculumText
            ?: $plan?->text
            ?: $variants
            ?: $competency->text
            ?: $this->clean($competency->raw_text)
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

    private function clean(mixed $value): string
    {
        return trim((string) preg_replace('/^\s*(?:\d+(?:\.\d+){3}|\d+(?:\.\d+){2}\s*\(?\d+\)?)\s*[GME]?\s*/i', '', (string) ($value ?? '')));
    }

    private function displayText(mixed $value, string $identifier): string
    {
        $display = $this->clean($value);

        return $display && $display !== $identifier ? $display : '';
    }
}
