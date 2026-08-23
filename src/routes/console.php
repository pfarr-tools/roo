<?php

use App\Services\AssessmentScan\AssessmentScanSessionStore;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('assessments:prune-scan-sessions', function (AssessmentScanSessionStore $sessions): int {
    $count = $sessions->pruneExpired();
    $this->info($count === 1
        ? '1 abgelaufene Scan-Session wurde gelöscht.'
        : "{$count} abgelaufene Scan-Sessions wurden gelöscht.");

    return self::SUCCESS;
})->purpose('Löscht abgelaufene temporäre Assessment-Scan-Sessions.');

Schedule::command('assessments:prune-scan-sessions')->hourly();
