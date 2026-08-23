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

    /** @param list<string> $pageContents */
    public function taskFragment(array $pageContents, float $startYCm, float $endYCm): string
    {
        if ($pageContents === [] || (count($pageContents) === 1 && $endYCm <= $startYCm)) {
            throw new RuntimeException('Der Aufgabenausschnitt benötigt eine positive Höhe.');
        }

        $images = array_map($this->imageFromContents(...), $pageContents);
        $crops = [];

        try {
            foreach ($images as $index => $image) {
                $startY = $index === 0 ? $this->pixels($startYCm) : 0;
                $endY = $index === array_key_last($images) ? min(imagesy($image), $this->pixels($endYCm)) : imagesy($image);
                $height = $endY - $startY;
                if ($height <= 0) {
                    throw new RuntimeException('Der Aufgabenausschnitt benötigt eine positive Höhe.');
                }
                $crops[] = $this->cropImage($image, 0, $startY, imagesx($image), $height);
            }

            $width = max(array_map(imagesx(...), $crops));
            $height = array_sum(array_map(imagesy(...), $crops));
            $assembled = imagecreatetruecolor($width, $height);
            imagefill($assembled, 0, 0, imagecolorallocate($assembled, 255, 255, 255));
            $offset = 0;
            foreach ($crops as $crop) {
                imagecopy($assembled, $crop, 0, $offset, 0, 0, imagesx($crop), imagesy($crop));
                $offset += imagesy($crop);
            }

            return $this->encode($assembled);
        } finally {
            foreach ($crops as $crop) {
                imagedestroy($crop);
            }
            foreach ($images as $image) {
                imagedestroy($image);
            }
        }
    }

    private function crop(string $pageContents, float $xCm, float $yCm, float $widthCm, float $heightCm): string
    {
        $image = $this->imageFromContents($pageContents);

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

    private function imageFromContents(string $contents): \GdImage
    {
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new RuntimeException('Die Scan-Seite konnte nicht als Bild gelesen werden.');
        }

        return $image;
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
