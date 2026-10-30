<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\Upload;

use Imagine\Image\ImagineInterface;

/**
 * Downscales an image file in place if it exceeds a maximum edge length.
 * Does nothing (and returns false) if the image is already small enough.
 */
class ImageShrinker
{
    private const MAX_EDGE = 2000;
    private const JPEG_QUALITY = 82;
    private const PNG_COMPRESSION = 7;
    private const WEBP_QUALITY = 82;

    public function __construct(private readonly ImagineInterface $imagine)
    {
    }

    public function shrink(string $absolutePath): bool
    {
        $image = $this->imagine->open($absolutePath);
        $size = $image->getSize();
        $longestEdge = max($size->getWidth(), $size->getHeight());

        if ($longestEdge <= self::MAX_EDGE) {
            return false;
        }

        $newSize = $size->scale(self::MAX_EDGE / $longestEdge);

        $image->resize($newSize)->save($absolutePath, [
            'jpeg_quality' => self::JPEG_QUALITY,
            'png_compression_level' => self::PNG_COMPRESSION,
            'webp_quality' => self::WEBP_QUALITY,
        ]);

        return true;
    }
}
