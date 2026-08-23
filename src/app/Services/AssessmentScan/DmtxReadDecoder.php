<?php

namespace App\Services\AssessmentScan;

use Closure;
use Symfony\Component\Process\Process;

final class DmtxReadDecoder implements DataMatrixDecoder
{
    private const SCAN_TIMEOUT_MILLISECONDS = 10000;

    private const LEFT_MARGIN_WIDTH_PX = 236;

    /** @param null|Closure(list<string>): array{exit_code:int, output:string, position_output:string} $processRunner */
    public function __construct(private readonly ?Closure $processRunner = null) {}

    /** @return iterable<array{payload:string, y_px:float}> */
    public function decode(string $imagePath): iterable
    {
        $cropPath = $this->cropLeftMargin($imagePath);

        try {
            $result = $this->run($this->commandArguments($cropPath));

            if ($result['exit_code'] > 1) {
                throw new \RuntimeException('DataMatrix-Decoder konnte nicht ausgeführt werden.');
            }

            yield from $this->parseOutput($result['output'], $result['position_output']);
        } finally {
            if (file_exists($cropPath)) {
                unlink($cropPath);
            }
        }
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

    /** @return array{exit_code:int, output:string, position_output:string} */
    private function run(array $arguments): array
    {
        if ($this->processRunner !== null) {
            return ($this->processRunner)($arguments);
        }

        $process = new Process($arguments);
        $exitCode = $process->run();

        return [
            'exit_code' => $exitCode,
            'output' => $process->getOutput(),
            'position_output' => $process->getErrorOutput(),
        ];
    }

    private function cropLeftMargin(string $imagePath): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($imagePath));
        if ($source === false) {
            throw new \RuntimeException('Gerenderte Scan-Seite konnte nicht gelesen werden.');
        }

        $width = min(imagesx($source), self::LEFT_MARGIN_WIDTH_PX);
        $height = imagesy($source);
        $crop = imagecreatetruecolor($width, $height);
        imagecopy($crop, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        $cropPath = tempnam(sys_get_temp_dir(), 'roo-dmtx-');

        try {
            if ($cropPath === false || ! imagepng($crop, $cropPath)) {
                throw new \RuntimeException('Scan-Rand konnte nicht für die DataMatrix-Erkennung vorbereitet werden.');
            }

            return $cropPath;
        } catch (\Throwable $exception) {
            if ($cropPath !== false && file_exists($cropPath)) {
                unlink($cropPath);
            }

            throw $exception;
        } finally {
            imagedestroy($crop);
        }
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
