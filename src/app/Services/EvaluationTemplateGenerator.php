<?php

namespace App\Services;

use App\Models\ReportPeriod;
use App\Models\TeachingUnitCompetency;
use Illuminate\Support\Collection;

class EvaluationTemplateGenerator
{
    public function generate(ReportPeriod $period): Collection
    {
        $competencies = TeachingUnitCompetency::query()
            ->whereHas('unit', fn ($query) => $query->where('teaching_group_id', $period->teaching_group_id))
            ->whereHas('educationPlanCompetency.area', fn ($query) => $query->where('kind', 'content'))
            ->whereHas('lessons.scheduledLessons.slot', fn ($query) => $query->whereBetween('date', [$period->starts_on, $period->ends_on]))
            ->with(['unit:id,position', 'educationPlanCompetency.area.version.plan', 'educationPlanCompetency.variants.level', 'lessons.scheduledLessons.slot'])
            ->get()
            ->sortBy(fn (TeachingUnitCompetency $competency): array => [
                $competency->lessons->flatMap->scheduledLessons->map(fn ($scheduled) => (string) $scheduled->slot->date)->sort()->first() ?? '9999-12-31',
                $competency->unit->position,
                $competency->educationPlanCompetency->position,
            ])
            ->unique('education_plan_competency_id')
            ->values();

        $sentences = json_decode((string) file_get_contents(base_path('../data/bildungsplaene/Kompetenzsaetze.json')), true, 512, JSON_THROW_ON_ERROR);
        $levels = $competencies->flatMap(function (TeachingUnitCompetency $competency): Collection {
            $variants = $competency->educationPlanCompetency->variants;
            $explicitLevels = $variants->pluck('level.external_identifier')->filter(fn ($level): bool => in_array($level, ['G', 'M', 'E'], true));

            return $explicitLevels->isNotEmpty() ? $explicitLevels : ($variants->count() === 3 ? collect(['G', 'M', 'E']) : collect());
        })->unique()->sortBy(fn (string $level): int => array_search($level, ['G', 'M', 'E'], true))->values();

        return ($levels->isNotEmpty() ? $levels->mapWithKeys(fn (string $level): array => [$level => $this->textFor($competencies, $sentences, $level)]) : collect([null => $this->textFor($competencies, $sentences)]))
            ->filter(fn ($text): bool => filled($text))
            ->map(fn (string $text, ?string $level): array => ['level' => $level, 'original_text' => $text, 'text' => $text])
            ->values();
    }

    private function textFor(Collection $competencies, array $sentences, ?string $level = null): string
    {
        return $competencies->map(function (TeachingUnitCompetency $competency) use ($sentences, $level): ?string {
            $educationPlanCompetency = $competency->educationPlanCompetency;
            $planIdentifier = $educationPlanCompetency->area->version->plan->external_identifier;
            $identifier = $educationPlanCompetency->external_identifier;
            $keys = collect([$planIdentifier.'.'.$identifier.($level ? '.'.$level : ''), $identifier.($level ? '.'.$level : ''), $planIdentifier.'.'.$identifier, $identifier])->filter();
            $sentence = $keys->map(fn (string $key) => $sentences[$key] ?? null)->first(fn ($value): bool => filled($value));

            return $sentence ? trim((string) $sentence) : null;
        })->filter()->values()->map(function (string $sentence, int $index): string {
            return $index === 0 ? $sentence : str_replace('[Vorname]', '[Pronomen]', $sentence);
        })->implode(' ');
    }
}
