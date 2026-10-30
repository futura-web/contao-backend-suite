<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeFontColor;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Automatischer Font-Import über die google-webfonts-helper API
 * (https://gwfh.mranftl.com/fonts): im Theme wird eine Liste von
 * Schriftarten gepflegt (eine Zeile pro Font), beim Speichern lädt diese
 * Klasse die passenden Dateien herunter und schreibt "_fontface.scss"
 * komplett neu.
 *
 * Zeilen-Syntax im Feld "backendSuiteFonts":
 *   Familie | Subsets (kommagetrennt) | Schriftschnitte (kommagetrennt)
 *   z.B.:  Open Sans | latin | 400,700
 *
 * Erwartet die SCSS-Projektstruktur-Konvention, die über mehrere Projekte
 * hinweg verwendet wird: "files/public/<project>/theme/css/...". Der
 * Projekt-Ordnername wird über das Theme selbst aufgelöst (Spalte
 * "templates" in tl_theme, Standard "project").
 */
class FontImporter
{
    private const API_BASE = 'https://gwfh.mranftl.com/api/fonts/';

    /**
     * Formate je Browser-Support-Stufe, entspricht den drei Stufen auf
     * gwfh.mranftl.com/fonts ("Modern Browsers", "Legacy Support",
     * "Historic Support").
     */
    private const SUPPORT_FORMATS = [
        'modern' => ['woff2'],
        'legacy' => ['woff2', 'ttf'],
        'historic' => ['eot', 'woff2', 'woff', 'ttf', 'svg'],
    ];

    private const FORMAT_COMMENTS = [
        'woff2' => 'Chrome 36+, Opera 23+, Firefox 39+, Safari 12+, iOS 10+',
        'ttf' => 'Chrome 4+, Firefox 3.5+, IE 9+, Safari 3.1+, iOS 4.2+, Android Browser 2.2+',
        'woff' => 'Chrome 5+, Firefox 3.6+, IE 9+, Safari 5.1+, iOS 5+',
        'eot_iefix' => 'IE6-IE8',
        'eot' => 'IE9 Compat Modes',
        'svg' => 'Legacy iOS',
    ];

    private const FORMAT_LABELS = [
        'woff2' => 'woff2',
        'woff' => 'woff',
        'ttf' => 'truetype',
        'eot' => 'embedded-opentype',
        'svg' => 'svg',
    ];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT backendSuiteFonts, backendSuiteFontSupport, backendSuiteFontsDeleteOld, templates FROM tl_theme WHERE id = ?',
            [(int) $dc->id],
        );

        if (!$row) {
            return;
        }

        $fonts = self::parseFontList((string) $row['backendSuiteFonts']);

        if ([] === $fonts) {
            return;
        }

        $deleteOld = (bool) $row['backendSuiteFontsDeleteOld'];
        $support = (string) ($row['backendSuiteFontSupport'] ?: 'modern');
        $formats = self::SUPPORT_FORMATS[$support] ?? self::SUPPORT_FORMATS['modern'];
        // "templates" speichert den Pfad relativ zum Projekt-Root (z.B.
        // "templates/project"), nicht nur den Ordnernamen - daher basename().
        $projectSlug = basename((string) ($row['templates'] ?: 'project'));

        $fontsDir = $this->projectDir.'/files/public/'.$projectSlug.'/theme/css/fonts';
        $fontfacePath = $this->projectDir.'/files/public/'.$projectSlug.'/theme/css/include/_fontface.scss';

        if (!is_dir($fontsDir) && !mkdir($fontsDir, 0775, true) && !is_dir($fontsDir)) {
            Message::addError('Font-Import: Font-Verzeichnis konnte nicht angelegt werden.');

            return;
        }

        $allDownloaded = [];
        $allSkipped = [];

        foreach ($fonts as [$family, $subsets, $variants]) {
            $slug = self::slugify($family);

            if ('' === $slug) {
                $allSkipped[] = sprintf('"%s" (ungültiger Name)', $family);

                continue;
            }

            foreach ($subsets as $subset) {
                $data = self::fetchFontData($slug, $subset);

                if (null === $data) {
                    $allSkipped[] = sprintf('%s (Subset "%s" nicht gefunden)', $family, $subset);

                    continue;
                }

                $version = $data['version'] ?? '0';

                foreach ($data['variants'] ?? [] as $variant) {
                    $variantId = (string) ($variant['id'] ?? '');

                    if (!\in_array($variantId, $variants, true)) {
                        continue;
                    }

                    $versionTag = str_starts_with($version, 'v') ? $version : 'v'.$version;
                    $baseName = sprintf('%s-%s-%s-%s', $slug, $versionTag, $subset, $variantId);

                    $files = [];
                    $failedFormats = [];

                    foreach ($formats as $format) {
                        $url = $variant[$format] ?? null;

                        if (!$url) {
                            $failedFormats[] = $format;

                            continue;
                        }

                        $fileName = $baseName.'.'.$format;

                        if (self::download($url, $fontsDir.'/'.$fileName)) {
                            $files[$format] = $fileName;
                        } else {
                            $failedFormats[] = $format;
                        }
                    }

                    if ($failedFormats) {
                        $allSkipped[] = sprintf('%s %s/%s (Format(e) fehlten: %s)', $family, $subset, $variantId, implode(', ', $failedFormats));
                    }

                    if ([] === $files) {
                        continue;
                    }

                    $allDownloaded[] = [
                        'files' => $files,
                        'family' => $family,
                        'subset' => $subset,
                        'variant' => $variantId,
                        'fontStyle' => $variant['fontStyle'] ?? 'normal',
                        'fontWeight' => $variant['fontWeight'] ?? '400',
                    ];
                }
            }
        }

        if ([] === $allDownloaded) {
            Message::addError(sprintf(
                'Font-Import fehlgeschlagen - keine Datei konnte geladen werden.%s',
                $allSkipped ? ' Details: '.implode('; ', $allSkipped) : '',
            ));

            return;
        }

        self::writeFontFace($fontfacePath, $allDownloaded, $support);

        $removedCount = 0;

        if ($deleteOld) {
            $removedCount = self::deleteObsoleteFontFiles($fontsDir, $allDownloaded);
        }

        Message::addConfirmation(sprintf(
            'Font-Import: %d Datei(en) geladen, _fontface.scss aktualisiert.%s%s',
            \count($allDownloaded),
            $deleteOld ? sprintf(' %d alte Font-Datei(en) gelöscht.', $removedCount) : ' Hinweis: Font-Dateien einer vorherigen Auswahl werden nicht automatisch gelöscht, ggf. manuell aufräumen (oder Checkbox "Alte Schriften löschen" aktivieren).',
            $allSkipped ? ' Übersprungen: '.implode('; ', $allSkipped) : '',
        ));

        // Eingabe-Widget und Lösch-Checkbox nach erfolgreicher Verarbeitung
        // zuruecksetzen - die "aktuell eingebundenen Schriftarten" werden
        // client-seitig direkt aus _fontface.scss angezeigt, nicht aus
        // diesem Feld. Das sorgt nebenbei dafuer, dass ein unveraendertes
        // Speichern des Themes keinen erneuten Font-Download ausloest.
        $this->connection->update(
            'tl_theme',
            ['backendSuiteFonts' => '', 'backendSuiteFontsDeleteOld' => ''],
            ['id' => (int) $dc->id],
        );
    }

    /**
     * @param array<int, array{files: array<string, string>, family: string, subset: string, variant: string, fontStyle: string, fontWeight: string}> $downloaded
     */
    private static function deleteObsoleteFontFiles(string $fontsDir, array $downloaded): int
    {
        $keep = [];

        foreach ($downloaded as $entry) {
            foreach ($entry['files'] as $fileName) {
                $keep[$fileName] = true;
            }
        }

        $removed = 0;

        foreach (glob($fontsDir.'/*') ?: [] as $path) {
            if (!is_file($path) || isset($keep[basename($path)])) {
                continue;
            }

            if (unlink($path)) {
                ++$removed;
            }
        }

        return $removed;
    }

    /**
     * @return array<int, array{0: string, 1: array<int, string>, 2: array<int, string>}>
     */
    private static function parseFontList(string $raw): array
    {
        $fonts = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            if (\count($parts) < 3 || '' === $parts[0]) {
                continue;
            }

            [$family, $subsetsRaw, $variantsRaw] = $parts;

            $subsets = array_values(array_filter(array_map('trim', explode(',', $subsetsRaw))));
            $variants = array_values(array_filter(array_map('trim', explode(',', $variantsRaw))));

            if ([] === $subsets || [] === $variants) {
                continue;
            }

            $fonts[] = [$family, $subsets, $variants];
        }

        return $fonts;
    }

    /**
     * @return array{version?: string, variants?: array}|null
     */
    private static function fetchFontData(string $slug, string $subset): array|null
    {
        $url = self::API_BASE.rawurlencode($slug).'?subsets='.rawurlencode($subset);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'ContaoBackendSuite/1.0 (+font-import)',
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (false === $body || '' !== $error || 200 !== $httpCode) {
            return null;
        }

        $data = json_decode($body, true);

        return \is_array($data) ? $data : null;
    }

    private static function download(string $url, string $destination): bool
    {
        $ch = curl_init($url);
        $fp = fopen($destination, 'wb');

        if (false === $fp) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'ContaoBackendSuite/1.0 (+font-import)',
        ]);

        $ok = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (false === $ok || '' !== $error || 200 !== $httpCode) {
            @unlink($destination);

            return false;
        }

        return true;
    }

    /**
     * @param array<int, array{files: array<string, string>, family: string, subset: string, variant: string, fontStyle: string, fontWeight: string}> $downloaded
     */
    private static function writeFontFace(string $path, array $downloaded, string $support): void
    {
        $lines = [
            '/*',
            '* Automatisch generiert über den Font-Import (google-webfonts-helper, '.$support.' browser support).',
            '* Manuelle Änderungen hier gehen beim nächsten Speichern des Themes verloren.',
            '*/',
            '',
            sprintf('$fontFamily: "%s", sans-serif;', $downloaded[0]['family']),
        ];

        foreach ($downloaded as $entry) {
            $lines[] = '';
            $lines[] = sprintf('/* %s - %s/%s */', strtolower($entry['family']), $entry['subset'], $entry['variant']);
            $lines[] = sprintf(
                '%s: "%s";',
                self::toVariableName(self::slugify($entry['family']), $entry['fontStyle'], $entry['fontWeight']),
                $entry['family'],
            );
            $lines[] = '@font-face {';
            $lines[] = '  font-display: swap;';
            $lines[] = sprintf('  font-family: "%s";', $entry['family']);
            $lines[] = sprintf('  font-style: %s;', $entry['fontStyle']);
            $lines[] = sprintf('  font-weight: %s;', $entry['fontWeight']);

            foreach (self::buildSrcLines($entry['files'], $entry['family']) as $srcLine) {
                $lines[] = $srcLine;
            }

            $lines[] = '}';
        }

        $lines[] = '';

        // CRLF, damit die generierte Datei zu den Zeilenenden der übrigen
        // SCSS-Partials in diesem Theme passt.
        file_put_contents($path, implode("\r\n", $lines));
    }

    /**
     * @param array<string, string> $files Format (woff2/woff/ttf/eot/svg) -> Dateiname
     *
     * @return array<int, string>
     */
    private static function buildSrcLines(array $files, string $family): array
    {
        $lines = [];

        if (isset($files['eot'])) {
            $lines[] = sprintf(
                '  src: url("fonts/%s"); /* %s */',
                $files['eot'],
                self::FORMAT_COMMENTS['eot'],
            );
        }

        $chain = [];

        if (isset($files['eot'])) {
            $chain[] = [
                sprintf('url("fonts/%s?#iefix") format("embedded-opentype")', $files['eot']),
                self::FORMAT_COMMENTS['eot_iefix'],
            ];
        }

        foreach (['woff2', 'woff', 'ttf'] as $format) {
            if (!isset($files[$format])) {
                continue;
            }

            $chain[] = [
                sprintf('url("fonts/%s") format("%s")', $files[$format], self::FORMAT_LABELS[$format]),
                self::FORMAT_COMMENTS[$format],
            ];
        }

        if (isset($files['svg'])) {
            $chain[] = [
                sprintf('url("fonts/%s#%s") format("svg")', $files['svg'], $family),
                self::FORMAT_COMMENTS['svg'],
            ];
        }

        $prefix = '  src: ';

        foreach ($chain as $i => [$part, $comment]) {
            $isLast = $i === \count($chain) - 1;
            $lines[] = sprintf('%s%s%s /* %s */', $prefix, $part, $isLast ? ';' : ',', $comment);
            $prefix = '       ';
        }

        return $lines;
    }

    private static function slugify(string $family): string
    {
        $family = trim($family);
        $family = preg_replace('/\s+/u', '-', $family) ?? $family;
        $family = strtolower($family);
        $family = preg_replace('/[^a-z0-9-]/', '', $family) ?? '';

        return trim($family, '-');
    }

    private static function toVariableName(string $slug, string $fontStyle, string $fontWeight): string
    {
        $camel = '';

        foreach (array_filter(explode('-', $slug), static fn (string $w): bool => '' !== $w) as $i => $word) {
            $camel .= 0 === $i ? $word : ucfirst($word);
        }

        return '$'.$camel.ucfirst($fontStyle).$fontWeight;
    }
}
