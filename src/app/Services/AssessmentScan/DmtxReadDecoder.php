<?php

namespace App\Services\AssessmentScan;

use Symfony\Component\Process\Process;

final class DmtxReadDecoder implements DataMatrixDecoder
{
    /** @return iterable<array{payload:string, y_px:float}> */
    public function decode(string $imagePath): iterable
    {
        $process = new Process(['dmtxread', '-R', '-q', '10', '-n', $imagePath]);
        $exitCode = $process->run();

        if ($exitCode > 1) {
            throw new \RuntimeException('DataMatrix-Decoder konnte nicht ausgeführt werden.');
        }

        yield from $this->parseOutput($process->getOutput());
    }

    /** @return list<array{payload:string, y_px:float}> */
    public function parseOutput(string $output): array
    {
        $markers = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (! preg_match('/^((?:-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?:){4})(.+)$/', trim($line), $matches)) {
                continue;
            }

            preg_match_all('/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?):/', $matches[1], $corners);
            $ys = array_map('floatval', $corners[2] ?? []);
            if (count($ys) !== 4) {
                continue;
            }

            $markers[] = ['payload' => trim($matches[2]), 'y_px' => min($ys)];
        }

        return $markers;
    }
}
