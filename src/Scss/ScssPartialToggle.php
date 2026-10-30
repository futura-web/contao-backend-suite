<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\Scss;

/**
 * Liest/schreibt den Aktiv-Zustand einer einzelnen "@import"-Zeile
 * (auskommentiert = inaktiv) in einer SCSS-Datei wie base.scss. Wird
 * sowohl von der manuellen SCSS-Features-Checkbox-Liste als auch von der
 * JS-Funktionen-Auswahl genutzt (einige Partials wie Slider/Modal/Galerie
 * werden automatisch anhand der aktiven JS-Funktionen geschaltet statt
 * manuell ausgewählt).
 */
final class ScssPartialToggle
{
    public static function getState(string $content, string $partial): bool|null
    {
        $regex = '/^\h*(\/\/\h*)?@import\h+"include\/_'.preg_quote($partial, '/').'"\h*;\h*$/m';

        if (!preg_match($regex, $content, $m)) {
            return null;
        }

        return '' === ($m[1] ?? '');
    }

    public static function setActive(string $content, string $partial, bool $active): string
    {
        $regex = '/^(\h*)(\/\/\h*)?(@import\h+"include\/_'.preg_quote($partial, '/').'"\h*;)\h*$/m';

        $newContent = preg_replace_callback($regex, static function (array $m) use ($active): string {
            return $m[1].($active ? '' : '// ').$m[3];
        }, $content, 1);

        return $newContent ?? $content;
    }
}
