<?php

namespace App\Console\Commands;

use App\Actions\EducationPlans\ImportEducationPlan;
use Illuminate\Console\Command;

class ImportEducationPlanCommand extends Command
{
    protected $signature = 'education-plans:import {path : Pfad zur JSON-Datei} {--organization= : Organisations-ID für einen organisationsbezogenen Import}';

    protected $description = 'Importiert einen strukturierten Bildungsplan aus JSON.';

    public function handle(ImportEducationPlan $import): int
    {
        $result = $import->execute($this->argument('path'), $this->option('organization') ? (int) $this->option('organization') : null);
        $this->info('Bildungsplan importiert: '.$result['plan']->title.' ('.$result['version']->external_identifier.')');

        return self::SUCCESS;
    }
}
