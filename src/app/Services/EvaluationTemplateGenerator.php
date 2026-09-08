<?php

namespace App\Services;

use App\Models\EducationPlanCompetency;
use App\Models\ReportPeriod;
use App\Models\Student;
use App\Models\TeachingUnitCompetency;
use Illuminate\Support\Collection;

class EvaluationTemplateGenerator
{
    public function generate(ReportPeriod $period): Collection
    {
        $competencies = $this->competencies($period);

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

    public function draft(ReportPeriod $period, Student $student, Collection $ratings, ?string $level): string
    {
        $sentences = json_decode((string) file_get_contents(base_path('../data/bildungsplaene/Kompetenzsaetze.json')), true, 512, JSON_THROW_ON_ERROR);
        $ratingsByCompetence = $ratings
            ->filter(fn (array $rating): bool => ($rating['include_in_text'] ?? true) && ($rating['rating'] ?? null) !== null)
            ->keyBy('education_plan_competency_id');
        $ratedCompetencies = EducationPlanCompetency::query()
            ->whereKey($ratingsByCompetence->keys())
            ->with(['area.version.plan', 'variants.level'])
            ->get()
            ->keyBy('id');

        $activeSentences = $ratingsByCompetence->values()->map(function (array $ratingData) use ($sentences, $ratedCompetencies, $level): ?string {
            $competence = $ratedCompetencies->get($ratingData['education_plan_competency_id']);
            if (! $competence) {
                return null;
            }

            $plan = $competence;
            $planIdentifier = $plan->area->version->plan->external_identifier;
            $identifier = $plan->external_identifier;
            $keys = collect([$planIdentifier.'.'.$identifier.($level ? '.'.$level : ''), $identifier.($level ? '.'.$level : ''), $planIdentifier.'.'.$identifier, $identifier])->filter();
            $sentence = $keys->map(fn (string $key) => $sentences[$key] ?? null)->first(fn ($value): bool => filled($value));
            if (! $sentence) {
                return null;
            }

            $rating = (int) ($ratingData['rating'] ?? 0);
            $sentence = preg_replace_callback('/\[Qualifikator:([^:]+):([^\]]+)\]/u', function (array $match) use ($rating): string {
                $qualifiers = array_map('trim', explode(',', $match[2]));
                $index = $rating > 0 ? max(0, 5 - $rating) : 5;

                return $qualifiers[$index] ?? '';
            }, (string) $sentence);

            return trim((string) $sentence);
        })->filter()->values();

        $groups = collect($this->groupSizes($activeSentences->count()))->reduce(function (array $groups, int $size) use ($activeSentences): array {
            $groups[] = $activeSentences->splice(0, $size)->values();

            return $groups;
        }, []);

        return collect($groups)->values()->map(function (Collection $group, int $groupIndex) use ($student): string {
            $subject = $groupIndex % 2 === 0 ? $student->first_name : ucfirst($student->pronoun_set ?: 'er');
            $phrases = $group->map(function (string $sentence, int $index) use ($subject, $group): string {
                $sentence = trim(str_replace('[Vorname]', $subject, $sentence));

                $phrase = $index === 0
                    ? $sentence
                    : trim((string) preg_replace('/^'.preg_quote($subject, '/').'\s+kann\s+/u', '', $sentence));

                return $index < $group->count() - 1
                    ? trim((string) preg_replace('/[.!?]+$/u', '', $phrase))
                    : $phrase;
            });

            return match ($group->count()) {
                3 => $phrases[0].'; '.$phrases[1].' und '.$phrases[2],
                2 => $phrases[0].' und '.$phrases[1],
                default => $phrases[0],
            };
        })->implode(' ');
    }

    private function groupSizes(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        if ($count === 1) {
            return [1];
        }

        if ($count % 3 === 1) {
            return $count === 4 ? [2, 2] : [...array_fill(0, intdiv($count - 4, 3), 3), 2, 2];
        }

        return $count % 3 === 2 ? [...array_fill(0, intdiv($count - 2, 3), 3), 2] : array_fill(0, intdiv($count, 3), 3);
    }

    private function competencies(ReportPeriod $period): Collection
    {
        return TeachingUnitCompetency::query()
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
