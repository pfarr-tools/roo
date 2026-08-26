<?php

namespace App\Services\AssessmentEvaluation;

use RuntimeException;

final class AssessmentTemplateCropper
{
    private const DPI = 300;

    /** Fixed geometry of the task body in the A4/300-DPI assessment template. */
    private const TASK_FRAME_LEFT_CM = 2.2;

    private const TASK_FRAME_TOP_CM = 3.2;

    private const TASK_FRAME_RIGHT_CM = 1.2;

    private const TASK_FRAME_BOTTOM_CM = 2.0;

    private const PAGE_WIDTH_CM = 21.0;

    private const PAGE_HEIGHT_CM = 29.7;

    private const WHITESPACE_THRESHOLD = 245;

    private const WHITESPACE_PADDING_PX = 2;

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
        $frames = [];
        $crops = [];

        try {
            foreach ($images as $index => $image) {
                $frames[] = $this->cropImage(
                    $image,
                    $this->pixels(self::TASK_FRAME_LEFT_CM),
                    $this->pixels(self::TASK_FRAME_TOP_CM),
                    $this->pixels(self::PAGE_WIDTH_CM - self::TASK_FRAME_LEFT_CM - self::TASK_FRAME_RIGHT_CM),
                    $this->pixels(self::PAGE_HEIGHT_CM - self::TASK_FRAME_TOP_CM - self::TASK_FRAME_BOTTOM_CM),
                );
                $frame = $frames[array_key_last($frames)];
                $startY = $index === 0 ? $this->pixels($startYCm - self::TASK_FRAME_TOP_CM) : 0;
                $endY = $index === array_key_last($images) ? min(imagesy($frame), $this->pixels($endYCm - self::TASK_FRAME_TOP_CM)) : imagesy($frame);
                $height = $endY - $startY;
                if ($height <= 0) {
                    throw new RuntimeException('Der Aufgabenausschnitt benötigt eine positive Höhe.');
                }
                $crops[] = $this->trimWhitespace($this->cropImage($frame, 0, $startY, imagesx($frame), $height));
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
            foreach ($frames as $frame) {
                imagedestroy($frame);
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

    private function trimWhitespace(\GdImage $image): \GdImage
    {
        $top = null;
        $bottom = null;
        for ($y = 0; $y < imagesy($image); $y++) {
            if (! $this->isWhitespaceRow($image, $y)) {
                $top ??= $y;
                $bottom = $y;
            }
        }

        if ($top === null || $bottom === null) {
            return $image;
        }

        $top = max(0, $top - self::WHITESPACE_PADDING_PX);
        $bottom = min(imagesy($image) - 1, $bottom + self::WHITESPACE_PADDING_PX);
        $trimmed = $this->cropImage($image, 0, $top, imagesx($image), $bottom - $top + 1);
        imagedestroy($image);

        return $trimmed;
    }

    private function isWhitespaceRow(\GdImage $image, int $y): bool
    {
        for ($x = 0; $x < imagesx($image); $x++) {
            $colour = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            if ($colour['red'] < self::WHITESPACE_THRESHOLD
                || $colour['green'] < self::WHITESPACE_THRESHOLD
                || $colour['blue'] < self::WHITESPACE_THRESHOLD) {
                return false;
            }
        }

        return true;
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
