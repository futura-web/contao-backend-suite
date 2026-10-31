<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\ThemeProjectConfig;

use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Benennt beim ersten echten Umbenennen des Themes (Feld "name") die
 * Boilerplate-Ordner "project" automatisch nach dem neuen Theme-Namen um.
 *
 * Regel: Wörter des Theme-Namens zusammenschreiben, kleingeschrieben.
 * Ist das Ergebnis länger als 10 Zeichen:
 *   - mehrere Wörter  -> Akronym aus den Anfangsbuchstaben (z.B. "odl")
 *   - ein Wort        -> auf 6 Zeichen kürzen
 *
 * Der Automatismus läuft nur einmal: sobald tl_theme.templates nicht mehr
 * "project" ist, ist er für diesen Datensatz "verbraucht".
 */
class BoilerplateRenamer
{
    private const DEFAULT_SLUG = 'project';
    private const LENGTH_THRESHOLD = 10;
    private const FALLBACK_LENGTH = 6;

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
            'SELECT name, templates FROM tl_theme WHERE id = ?',
            [(int) $dc->id],
        );

        if (!$row || self::DEFAULT_SLUG !== $row['templates']) {
            // Noch kein Datensatz, oder Automatik für dieses Theme schon verbraucht.
            return;
        }

        $oldName = (string) ($dc->activeRecord->name ?? '');
        $newName = (string) $row['name'];

        if ('' === $newName || $oldName === $newName) {
            // Name wurde in diesem Speichervorgang nicht geändert -> nicht auslösen.
            return;
        }

        $newSlug = self::slugify($newName);

        if ('' === $newSlug || self::DEFAULT_SLUG === $newSlug) {
            return;
        }

        try {
            $templatesRenamed = $this->renameFolder($this->projectDir.'/templates/'.self::DEFAULT_SLUG, $this->projectDir.'/templates/'.$newSlug);
            $filesRenamed = $this->renameFolder($this->projectDir.'/files/public/'.self::DEFAULT_SLUG, $this->projectDir.'/files/public/'.$newSlug);

            $this->replaceInFiles($this->projectDir.'/templates/'.$newSlug, self::DEFAULT_SLUG, $newSlug);
            $this->replaceInFiles($this->projectDir.'/contao/config', self::DEFAULT_SLUG, $newSlug);

            $this->connection->update('tl_theme', ['templates' => $newSlug], ['id' => (int) $dc->id]);

            Message::addConfirmation(sprintf(
                'Theme-Ordner wurden von "%s" zu "%s" umbenannt (Templates: %s, Dateien: %s). '
                .'Bitte jetzt "contao:files:sync" ausführen und den Cache leeren.',
                self::DEFAULT_SLUG,
                $newSlug,
                $templatesRenamed ? 'ok' : 'übersprungen',
                $filesRenamed ? 'ok' : 'übersprungen',
            ));
        } catch (\Throwable $e) {
            Message::addError(sprintf(
                'Automatische Theme-Umbenennung nach "%s" fehlgeschlagen: %s. Bitte manuell prüfen.',
                $newSlug,
                $e->getMessage(),
            ));
        }
    }

    private static function slugify(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = array_values(array_filter(array_map(
            static fn (string $w): string => self::asciify($w),
            $words,
        )));

        if ([] === $words) {
            return '';
        }

        $joined = strtolower(implode('', $words));

        if (\strlen($joined) <= self::LENGTH_THRESHOLD) {
            return $joined;
        }

        if (\count($words) > 1) {
            $initials = array_map(
                static fn (string $w): string => strtolower(substr($w, 0, 1)),
                $words,
            );

            return implode('', $initials);
        }

        return substr($joined, 0, self::FALLBACK_LENGTH);
    }

    private static function asciify(string $word): string
    {
        $map = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        ];

        $word = strtr($word, $map);

        return preg_replace('/[^a-zA-Z0-9]/', '', $word) ?? '';
    }

    private function renameFolder(string $oldPath, string $newPath): bool
    {
        if (!is_dir($oldPath)) {
            return false;
        }

        if (is_dir($newPath)) {
            throw new \RuntimeException(sprintf('Zielordner "%s" existiert bereits.', $newPath));
        }

        if (!rename($oldPath, $newPath)) {
            throw new \RuntimeException(sprintf('Konnte "%s" nicht nach "%s" umbenennen.', $oldPath, $newPath));
        }

        return true;
    }

    private function replaceInFiles(string $dir, string $old, string $new): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $oldFragment = 'public/'.$old.'/';
        $newFragment = 'public/'.$new.'/';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (false === $content || !str_contains($content, $oldFragment)) {
                continue;
            }

            file_put_contents($file->getPathname(), str_replace($oldFragment, $newFragment, $content));
        }
    }
}
