<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case NOT_SHARED = 'not_shared';
    case SHARED_IMMEDIATELY = 'shared_immediately';
    case SHARED_WITH_LESSON = 'shared_with_lesson';

    public function label(): string
    {
        return match ($this) {
            self::NOT_SHARED => 'Nicht freigegeben',
            self::SHARED_IMMEDIATELY => 'Sofort freigegeben',
            self::SHARED_WITH_LESSON => 'Mit der Stunde freigegeben',
        };
    }

    public function allowsPublicAccess(bool $lessonStarted): bool
    {
        return match ($this) {
            self::NOT_SHARED => false,
            self::SHARED_IMMEDIATELY => true,
            self::SHARED_WITH_LESSON => $lessonStarted,
        };
    }
}
