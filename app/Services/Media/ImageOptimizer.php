<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;

/**
 * Shrinks an uploaded photo before it is stored: corrects the EXIF rotation,
 * caps the longest side, re-encodes (WebP by default) and drops metadata.
 *
 * It never makes an image worse: anything it cannot read, animated or vector
 * formats, and results that would not be smaller all keep the original file.
 */
class ImageOptimizer
{
    /** Formats GD can decode as a single still frame. GIF may be animated, so it is left alone. */
    private const OPTIMIZABLE = ['jpg', 'jpeg', 'png', 'webp'];

    /** Bytes GD needs per pixel, with headroom for the resized copy. */
    private const BYTES_PER_PIXEL = 10;

    /**
     * @return array{path: string, extension: string}|null null keeps the original file
     */
    public function optimize(string $path, string $extension, bool $keepFormat = false): ?array
    {
        $extension = strtolower($extension);
        if (! config('media.optimize.enabled', true)
            || ! in_array($extension, self::OPTIMIZABLE, true)
            || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $info = @getimagesize($path);
        if (! is_array($info) || $info[0] < 1 || $info[1] < 1) {
            return null;
        }
        [$width, $height] = $info;
        if ($width * $height > (int) config('media.optimize.max_pixels', 50_000_000)) {
            return null;
        }

        $memoryLimit = ini_get('memory_limit');
        $this->ensureMemory($width * $height * self::BYTES_PER_PIXEL);

        try {
            return $this->process($path, $extension, $keepFormat);
        } catch (\Throwable $e) {
            Log::warning('Image optimisation skipped.', ['exception' => $e::class]);

            return null;
        } finally {
            ini_set('memory_limit', (string) $memoryLimit);
        }
    }

    /**
     * @return array{path: string, extension: string}|null
     */
    private function process(string $path, string $extension, bool $keepFormat): ?array
    {
        $image = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (! $image instanceof \GdImage) {
            return null;
        }

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            $image = $this->applyExifOrientation($image, $path);
        }

        $resized = $this->downscale($image, $extension === 'png');
        $image = $resized ?? $image;

        $target = $keepFormat || ! function_exists('imagewebp') ? $extension : 'webp';
        $output = tempnam(sys_get_temp_dir(), 'img');
        if ($output === false) {
            return null;
        }

        if (! $this->encode($image, $target, $extension, $output)) {
            @unlink($output);

            return null;
        }

        clearstatcache(true, $output);
        $optimizedSize = (int) filesize($output);
        if ($optimizedSize === 0 || $optimizedSize >= (int) filesize($path)) {
            @unlink($output);

            return null;
        }

        return ['path' => $output, 'extension' => $target === 'jpeg' ? 'jpg' : $target];
    }

    /**
     * Phones store photos sideways and flag the rotation in EXIF; re-encoding
     * drops that flag, so the pixels are rotated first.
     */
    private function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof \GdImage ? $rotated : $image;
    }

    /**
     * Fits the longest side within max_dimension without ever upscaling.
     * A strong reduction is followed by a light sharpen to keep edges crisp.
     */
    private function downscale(\GdImage $image, bool $mayHaveAlpha): ?\GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $max = max(1, (int) config('media.optimize.max_dimension', 2560));
        if (max($width, $height) <= $max) {
            return null;
        }

        $ratio = $max / max($width, $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Convolution also filters the alpha channel, which frays transparent edges.
        if ($ratio < 0.67 && ! $mayHaveAlpha) {
            imageconvolution($canvas, [[-1, -1, -1], [-1, 24, -1], [-1, -1, -1]], 16, 0);
        }

        return $canvas;
    }

    private function encode(\GdImage $image, string $target, string $source, string $output): bool
    {
        $quality = (int) config('media.optimize.quality', 82);

        return match ($target) {
            // PNG is usually a logo, screenshot or text graphic: lossless WebP keeps every edge.
            'webp' => $this->prepareAlpha($image)
                && imagewebp($image, $output, $source === 'png' && defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : $quality),
            'png' => $this->prepareAlpha($image) && imagepng($image, $output, 9),
            default => $this->encodeJpeg($image, $output, max($quality, 85)),
        };
    }

    private function encodeJpeg(\GdImage $image, string $output, int $quality): bool
    {
        imageinterlace($image, true);

        return imagejpeg($image, $output, $quality);
    }

    private function prepareAlpha(\GdImage $image): bool
    {
        imagealphablending($image, false);

        return imagesavealpha($image, true);
    }

    private function ensureMemory(int $needed): void
    {
        $limit = $this->bytes((string) ini_get('memory_limit'));
        $required = memory_get_usage(true) + $needed;
        if ($limit > 0 && $limit < $required) {
            ini_set('memory_limit', (string) $required);
        }
    }

    private function bytes(string $value): int
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
}
