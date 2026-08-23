<?php

namespace App\Services\AssessmentEvaluation;

use RuntimeException;

final class AssessmentTemplateCropper
{
    private const DPI = 300;

    private const NAME_X_CM = 14.2;

    private const NAME_Y_CM = 1.0;

    private const NAME_WIDTH_CM = 5.6;

    private const NAME_HEIGHT_CM = 1.6;

    public function nameFragment(string $pageContents): string
    {
        return $this->crop(
            $pageContents,
            self::NAME_X_CM,
            self::NAME_Y_CM,
            self::NAME_WIDTH_CM,
            self::NAME_HEIGHT_CM,
        );
    }

    public function taskFragment(string $pageContents, float $startYCm, float $endYCm): string
    {
        if ($endYCm <= $startYCm) {
            throw new RuntimeException('Der Aufgabenausschnitt benötigt eine positive Höhe.');
        }

        $image = imagecreatefromstring($pageContents);
        if ($image === false) {
            throw new RuntimeException('Die Scan-Seite konnte nicht als Bild gelesen werden.');
        }

        try {
            $startY = $this->pixels($startYCm);
            $endY = min(imagesy($image), $this->pixels($endYCm));

            return $this->encode($this->cropImage($image, 0, $startY, imagesx($image), $endY - $startY));
        } finally {
            imagedestroy($image);
        }
    }

    private function crop(string $pageContents, float $xCm, float $yCm, float $widthCm, float $heightCm): string
    {
        $image = imagecreatefromstring($pageContents);
        if ($image === false) {
            throw new RuntimeException('Die Scan-Seite konnte nicht als Bild gelesen werden.');
        }

        try {
            return $this->encode($this->cropImage(
                $image,
                $this->pixels($xCm),
                $this->pixels($yCm),
                $this->pixels($widthCm),
                $this->pixels($heightCm),
            ));
        } finally {
            imagedestroy($image);
        }
    }

    private function cropImage(\GdImage $image, int $x, int $y, int $width, int $height): \GdImage
    {
        $crop = imagecrop($image, compact('x', 'y', 'width', 'height'));
        if ($crop === false) {
            throw new RuntimeException('Der Ausschnitt liegt außerhalb der Scan-Seite.');
        }

        return $crop;
    }

    private function encode(\GdImage $image): string
    {
        try {
            ob_start();
            imagepng($image);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }
    }

    private function pixels(float $centimetres): int
    {
        return (int) round(($centimetres / 2.54) * self::DPI);
    }
}
