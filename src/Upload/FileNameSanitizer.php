<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\Upload;

/**
 * Rewrites an uploaded image's filename into a clean, predictable form:
 * lowercase, spaces become underscores, umlauts/diacritics are
 * transliterated, and a ".min" marker is inserted before the extension
 * (once — re-running this on an already-sanitized name is a no-op).
 */
class FileNameSanitizer
{
    /**
     * German umlauts expand (ä → ae) rather than just losing the dot,
     * which is the conventional German transliteration and reads better
     * than iconv's own TRANSLIT output (which turns ä into a plain "a").
     */
    private const GERMAN_MAP = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        'ß' => 'ss',
    ];

    public function sanitize(string $fileName): string
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);

        $baseName = $this->transliterate($baseName);
        $baseName = strtolower($baseName);
        $baseName = str_replace(' ', '_', $baseName);
        $baseName = preg_replace('/[^a-z0-9_.-]/', '', $baseName) ?? $baseName;
        $baseName = preg_replace('/_+/', '_', $baseName) ?? $baseName;
        $baseName = trim($baseName, '_.-');

        if ('' === $baseName) {
            $baseName = 'file';
        }

        if (!str_ends_with($baseName, '.min')) {
            $baseName .= '.min';
        }

        $extension = strtolower($extension);

        return '' !== $extension ? $baseName.'.'.$extension : $baseName;
    }

    private function transliterate(string $value): string
    {
        // macOS sends filenames in NFD (decomposed) form, e.g. "ü" as "u" +
        // a combining diaeresis. Normalizing to NFC first ensures the
        // German umlaut map below actually matches, instead of iconv
        // silently dropping the combining mark and leaving a bare "u".
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }

        $value = strtr($value, self::GERMAN_MAP);

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return false !== $transliterated ? $transliterated : $value;
    }
}
