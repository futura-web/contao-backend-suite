<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\Upload;

use Imagine\Image\ImagineInterface;

/**
 * Recompresses an image file in place to reduce its file size, without
 * changing its pixel dimensions. Does nothing (and returns false) if the
 * file is already small enough.
 */
class ImageShrinker
{
    private const MAX_FILE_SIZE = 1_000_000;
    private const JPEG_QUALITY = 75;
    private const PNG_COMPRESSION = 9;
    private const WEBP_QUALITY = 75;

    public function __construct(private readonly ImagineInterface $imagine)
    {
    }

    public function shrink(string $absolutePath): bool
    {
        if (filesize($absolutePath) <= self::MAX_FILE_SIZE) {
            return false;
        }

        $image = $this->imagine->open($absolutePath);

        $image->save($absolutePath, [
            'jpeg_quality' => self::JPEG_QUALITY,
            'png_compression_level' => self::PNG_COMPRESSION,
            'webp_quality' => self::WEBP_QUALITY,
        ]);

        return true;
    }
}
