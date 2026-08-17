<?php

namespace App\Console\Commands;

use App\Actions\Curricula\ImportCurriculum;
use Illuminate\Console\Command;

class ImportCurriculumCommand extends Command
{
    protected $signature = 'curricula:import {path : JSON-Datei oder Verzeichnis}';

    protected $description = 'Importiert ein oder mehrere Roo-Curricula.';

    public function handle(ImportCurriculum $import): int
    {
        $path = $this->argument('path');
        $files = is_dir($path) ? glob(rtrim($path, '/').'/*.json') : [$path];
        foreach ($files as $file) {
            try {
                $result = $import->execute($file);
                $this->info($result['curriculum']->title.' importiert.');
            } catch (\Throwable $e) {
                $this->error($file.': '.$e->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
