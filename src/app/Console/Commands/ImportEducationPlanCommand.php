<?php

namespace App\Console\Commands;

use App\Actions\EducationPlans\ImportEducationPlan;
use Illuminate\Console\Command;

class ImportEducationPlanCommand extends Command
{
    protected $signature = 'education-plans:import {path : Pfad zur JSON-Datei oder zu einem Verzeichnis} {--organization= : Organisations-ID für einen organisationsbezogenen Import}';

    protected $description = 'Importiert einen strukturierten Bildungsplan aus JSON.';

    public function handle(ImportEducationPlan $import): int
    {
        $path = $this->argument('path');
        $files = is_dir($path) ? glob(rtrim($path, '/').'/*.json') : [$path];
        foreach ($files as $file) {
            $result = $import->execute($file, $this->option('organization') ? (int) $this->option('organization') : null);
            $this->info('Bildungsplan importiert: '.$result['plan']->title.' ('.$result['version']->external_identifier.')');
        }

        return self::SUCCESS;
    }
}
