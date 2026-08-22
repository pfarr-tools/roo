<?php

namespace App\Services\AssessmentScan;

use Symfony\Component\Process\Process;

final class PopplerPdfPageRenderer implements PdfPageRenderer
{
    /** @return iterable<array{page:int, image_path:string}> */
    public function render(string $pdfPath): iterable
    {
        $directory = sys_get_temp_dir().'/roo-assessment-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);

        try {
            $info = Process::fromShellCommandline('pdfinfo '.escapeshellarg($pdfPath));
            $info->mustRun();
            preg_match('/^Pages:\s+(\d+)/mi', $info->getOutput(), $matches);
            $pageCount = (int) ($matches[1] ?? 0);

            for ($page = 1; $page <= $pageCount; $page++) {
                $imagePath = $directory.'/page-'.$page;
                (new Process(['pdftoppm', '-f', (string) $page, '-l', (string) $page, '-r', '300', '-png', '-singlefile', $pdfPath, $imagePath]))->mustRun();

                yield ['page' => $page, 'image_path' => $imagePath.'.png'];
            }
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
