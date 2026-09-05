<?php

namespace App\Services;

use App\Models\School;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class TeachingUnitPublicView
{
    public function __construct(
        public readonly TeachingUnit $unit,
        public readonly ?User $creator,
        public readonly TeachingGroup $group,
        public readonly School $school,
        public readonly Collection $competencies,
        public readonly Collection $scheduledLessons,
        public readonly Collection $visiblePhaseResources,
        public readonly Collection $visiblePhaseLinks,
        public readonly Collection $galleries,
        public readonly ?CarbonImmutable $nextVisibilityAt,
    ) {}
}
