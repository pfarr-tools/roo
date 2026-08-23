<?php

namespace App\Services\AssessmentScan;

use Symfony\Component\Process\Process;

final class DmtxReadDecoder implements DataMatrixDecoder
{
    private const SCAN_TIMEOUT_MILLISECONDS = 10000;

    /** @return iterable<array{payload:string, y_px:float}> */
    public function decode(string $imagePath): iterable
    {
        $process = new Process($this->commandArguments($imagePath));
        $exitCode = $process->run();

        if ($exitCode > 1) {
            throw new \RuntimeException('DataMatrix-Decoder konnte nicht ausgeführt werden.');
        }

        yield from $this->parseOutput($process->getOutput(), $process->getErrorOutput());
    }

    /** @return list<string> */
    public function commandArguments(string $imagePath): array
    {
        return [
            'dmtxread',
            '-R',
            '-q',
            '10',
            '-m',
            (string) self::SCAN_TIMEOUT_MILLISECONDS,
            '-n',
            $imagePath,
        ];
    }

    /** @return list<array{payload:string, y_px:float}> */
    public function parseOutput(string $output, string $positionOutput = ''): array
    {
        $markers = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (! preg_match('/^((?:-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?:){4})(.+)$/', trim($line), $matches)) {
                continue;
            }

            $markers[] = $this->markerFromPosition($matches[1], $matches[2]);
        }

        if ($positionOutput === '') {
            return array_values(array_filter($markers));
        }

        preg_match_all('/((?:-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?:){4})/', $positionOutput, $positionMatches);
        $positions = $positionMatches[1] ?? [];
        $payloads = array_values(array_filter(
            preg_split('/\R/', trim($output)) ?: [],
            static fn (string $line): bool => trim($line) !== '',
        ));

        foreach ($payloads as $index => $payload) {
            if (isset($positions[$index])) {
                $markers[] = $this->markerFromPosition($positions[$index], $payload);
            }
        }

        return array_values(array_filter($markers));
    }

    /** @return array{payload:string, y_px:float}|null */
    private function markerFromPosition(string $position, string $payload): ?array
    {
        preg_match_all('/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?):/', $position, $corners);
        $ys = array_map('floatval', $corners[2] ?? []);
        if (count($ys) !== 4 || trim($payload) === '') {
            return null;
        }

        $xs = array_map('floatval', $corners[1] ?? []);

        return [
            'payload' => trim($payload),
            'x_px' => min($xs),
            'y_px' => min($ys),
            'width_px' => max($xs) - min($xs),
            'height_px' => max($ys) - min($ys),
        ];
    }
}
