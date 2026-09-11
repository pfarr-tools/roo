<?php

namespace App\Documents;

enum DocumentLayout: string
{
    case PRIMARY_SCHOOL_LOWER_SECONDARY = 'primary-school-lower-secondary';
    case SECONDARY = 'secondary';

    public function label(): string
    {
        return match ($this) {
            self::PRIMARY_SCHOOL_LOWER_SECONDARY => 'Grundschule, Unterstufe',
            self::SECONDARY => 'Sekundarstufe',
        };
    }
}
