<?php

namespace App\Students\PronounSets;

class PronounSets
{
    /**
     * @return array<int, AbstractPronounSet>
     */
    public static function all(): array
    {
        return [new ErPronounSet, new SiePronounSet];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function toArray(): array
    {
        return array_map(fn (AbstractPronounSet $set): array => [
            'key' => $set->getKey(),
            'label' => $set->getLabel(),
        ], self::all());
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_map(fn (AbstractPronounSet $set): string => $set->getKey(), self::all());
    }
}
