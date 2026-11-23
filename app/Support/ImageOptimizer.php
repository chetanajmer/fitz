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

    /** Never raise PHP's memory limit above this while processing one picture. */
    protected const MAX_MEMORY_BYTES = 1024 * 1024 * 1024;

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

        // A decoded picture needs about 4 bytes per pixel (plus the smaller copy we make):
        // raise the memory limit just enough for it, or refuse it with a clear message.
        $this->ensureMemoryFor($info[0], $info[1], $info[2], $field);

        $original = $this->load($path, $info[2]);

        if (! $original instanceof GdImage) {
            throw ValidationException::withMessages([$field => 'This picture could not be processed. Try saving it as JPG or PNG.']);
        }

        if (! imageistruecolor($original)) {
            imagepalettetotruecolor($original); // GIF / 8-bit PNG
        }

        // Phone photos are often stored sideways: a 90° turn swaps the box we fit into.
        $angle = $this->rotationAngle($path, $info[2]);
        [$boxWidth, $boxHeight] = abs($angle) === 90 ? [$maxHeight, $maxWidth] : [$maxWidth, $maxHeight];

        // Shrink once into the box and free the big original straight away (saves memory).
        $hasAlpha = $this->hasTransparency($original);
        $fit = min(1, $boxWidth / imagesx($original), $boxHeight / imagesy($original));
        $image = $this->resize($original, max(1, (int) round(imagesx($original) * $fit)), max(1, (int) round(imagesy($original) * $fit)), $hasAlpha);
        imagedestroy($original);
        unset($original);

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, $hasAlpha ? imagecolorallocatealpha($image, 0, 0, 0, 127) : 0);
            imagedestroy($image);
            $image = $rotated;
            if ($hasAlpha) {
                imagesavealpha($image, true);
            }
        }

        $format = function_exists('imagewebp') ? 'webp' : ($hasAlpha ? 'png' : 'jpg');

        // Then shrink further only if the byte budget is not met.
        $scale = 1.0;
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
    protected function rotationAngle(string $path, int $type): int
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return 0;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        return [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
    }

    /** Decode straight from the file (no extra copy of the file in memory). */
    protected function load(string $path, int $type): GdImage|false
    {
        return match (true) {
            $type === IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            $type === IMAGETYPE_PNG => @imagecreatefrompng($path),
            $type === IMAGETYPE_GIF => @imagecreatefromgif($path),
            $type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($path),
            default => @imagecreatefromstring((string) file_get_contents($path)),
        };
    }

    /**
     * Make sure PHP may use enough memory to decode the picture. GD keeps 4 bytes per pixel; PNG,
     * WebP and GIF decoders also hold their own full copy of the raw rows while decoding, so they
     * need about twice as much as JPEG. Raises memory_limit for this request when allowed;
     * otherwise a friendly error.
     */
    protected function ensureMemoryFor(int $width, int $height, int $type, string $field): void
    {
        $bytesPerPixel = $type === IMAGETYPE_JPEG ? 6 : 11;
        $needed = memory_get_usage(true) + $width * $height * $bytesPerPixel + 64 * 1024 * 1024;
        $limit = $this->bytes((string) ini_get('memory_limit'));

        if ($limit < 0 || $needed <= $limit) {
            return;
        }

        if ($needed <= self::MAX_MEMORY_BYTES && @ini_set('memory_limit', (string) $needed) !== false
            && $this->bytes((string) ini_get('memory_limit')) >= $needed) {
            return;
        }

        throw ValidationException::withMessages([$field => 'This picture is too big to process ('.$width.'×'.$height
            .'). Please resize it (e.g. to 3000 pixels wide) or use a smaller photo.']);
    }

    /** "128M" / "1G" / "-1" to bytes (-1 = unlimited). */
    protected function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
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
