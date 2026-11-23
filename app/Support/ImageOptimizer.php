<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Shrinks uploaded pictures (PHP GD, no extra package) until they fit a byte budget:
 * resize to a maximum width/height, then lower the quality, then make it smaller again if needed.
 * Saves WebP when the server supports it (small, keeps transparency); otherwise JPEG, or PNG
 * for pictures with transparency.
 */
class ImageOptimizer
{
    /** Pictures larger than this (in megapixels) are refused before decoding, to protect memory. */
    public const MAX_MEGAPIXELS = 40;

    protected const QUALITIES = [85, 78, 70, 62, 55, 48, 40];

    /**
     * @return array{contents: string, extension: string, width: int, height: int, bytes: int}
     */
    public function optimize(UploadedFile|string $source, int $maxWidth, int $maxHeight, int $maxBytes, string $field = 'image'): array
    {
        $path = $source instanceof UploadedFile ? $source->getRealPath() : $source;
        $info = @getimagesize($path);

        if (! $info || $info[0] < 1 || $info[1] < 1) {
            throw ValidationException::withMessages([$field => 'This file is not a picture we can read. Use JPG, PNG or WebP.']);
        }

        if (self::MAX_MEGAPIXELS * 1_000_000 < $info[0] * $info[1]) {
            throw ValidationException::withMessages([$field => 'This picture is too large ('.$info[0].'×'.$info[1].'). Use one under '.self::MAX_MEGAPIXELS.' megapixels.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([$field => 'This picture could not be processed. Try saving it as JPG or PNG.']);
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image); // GIF / 8-bit PNG
        }

        $image = $this->orient($image, $path, $info[2]);
        $hasAlpha = $this->hasTransparency($image);
        $format = function_exists('imagewebp') ? 'webp' : ($hasAlpha ? 'png' : 'jpg');

        // Fit inside the box (never enlarge), then shrink further until the budget is met.
        $scale = min(1, $maxWidth / imagesx($image), $maxHeight / imagesy($image));
        $best = null;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $width = max(1, (int) round(imagesx($image) * $scale));
            $height = max(1, (int) round(imagesy($image) * $scale));
            $resized = $this->resize($image, $width, $height, $hasAlpha);

            foreach ($format === 'png' ? [9] : self::QUALITIES as $quality) {
                $contents = $this->encode($resized, $format, $quality, $hasAlpha);
                $best = ['contents' => $contents, 'extension' => $format, 'width' => $width, 'height' => $height, 'bytes' => strlen($contents)];

                if ($best['bytes'] <= $maxBytes) {
                    imagedestroy($resized);
                    imagedestroy($image);

                    return $best;
                }
            }

            imagedestroy($resized);
            $scale *= 0.8;
        }

        imagedestroy($image);

        return $best;
    }

    protected function resize(GdImage $image, int $width, int $height, bool $keepAlpha): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if ($keepAlpha) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        } else {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $canvas;
    }

    protected function encode(GdImage $image, string $format, int $quality, bool $keepAlpha): string
    {
        ob_start();
        match ($format) {
            'webp' => imagewebp($image, null, $quality),
            'png' => imagepng($image, null, 9),
            default => imagejpeg($image, null, $quality),
        };

        return (string) ob_get_clean();
    }

    /** Phone photos are often stored sideways with an EXIF rotation flag. */
    protected function orient(GdImage $image, string $path, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        imagedestroy($image);

        return $rotated;
    }

    /** Samples a grid of pixels: enough to tell a transparent logo from a photo. */
    protected function hasTransparency(GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $stepX = max(1, intdiv($width, 40));
        $stepY = max(1, intdiv($height, 40));

        for ($x = 0; $x < $width; $x += $stepX) {
            for ($y = 0; $y < $height; $y += $stepY) {
                if ((imagecolorat($image, $x, $y) >> 24) & 0x7F) {
                    return true;
                }
            }
        }

        return false;
    }
}
