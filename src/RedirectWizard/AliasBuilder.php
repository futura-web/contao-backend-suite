<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\RedirectWizard;

/**
 * Rechnet eine alte URL in den tl_page.alias um, den eine
 * Weiterleitungsseite braucht, damit sie genau unter der alten Adresse
 * antwortet.
 *
 * Contao baut die Route einer Seite als
 *
 *     /<urlPrefix>/<alias><urlSuffix>
 *
 * wobei urlPrefix (z.B. "de") und urlSuffix (z.B. ".html") am Startpunkt
 * haengen, nicht an der einzelnen Seite. Das Feld "routePath" in Contao 5.3
 * ist nur eine Anzeige (PageRoutingListener::generateRoutePath), kein
 * Eingabefeld - es gibt also keinen Weg, Prefix und Suffix fuer eine
 * einzelne Seite zu umgehen. Daraus folgen die harten Grenzen unten: passt
 * die alte URL nicht in dieses Schema, kann sie als Seite im Seitenbaum
 * nicht nachgebildet werden.
 *
 * Die Baumposition der Seite spielt fuer die URL dagegen keine Rolle, nur
 * der Alias-String. Alle Weiterleitungsseiten koennen daher in einem
 * einzigen, zugeklappten Ordner am Ende des Baums liegen.
 */
final class AliasBuilder
{
    public const PROBLEM_QUERY = 'query';
    public const PROBLEM_PREFIX = 'prefix';
    public const PROBLEM_SUFFIX = 'suffix';
    public const PROBLEM_INVALID = 'invalid';
    public const PROBLEM_ROOT = 'root';

    /**
     * Identisch zu Contao\Validator::isFolderAlias() (rgxp "folderalias",
     * mit dem tl_page.alias validiert wird): Buchstaben, Ziffern,
     * Unterstrich, Schraegstrich, Punkt und Bindestrich, kein fuehrender
     * oder abschliessender Schraegstrich.
     */
    private const FOLDER_ALIAS = '(^(?!/)[\w/.-]+(?<!/)$)u';

    /**
     * @param string $urlPrefix Startpunkt-Feld "urlPrefix", ohne Schraegstriche
     * @param string $urlSuffix Startpunkt-Feld "urlSuffix", inkl. Punkt
     *
     * @return array{alias: string, problem: string|null}
     */
    public function build(string $path, string $query, string $urlPrefix, string $urlSuffix): array
    {
        // Query-Strings sind in einem Alias nicht darstellbar. "/index.php?id=47"
        // laesst sich als Seite im Baum grundsaetzlich nicht abbilden.
        if ('' !== $query) {
            return ['alias' => '', 'problem' => self::PROBLEM_QUERY];
        }

        $alias = ltrim($path, '/');

        $prefix = trim($urlPrefix, '/');

        if ('' !== $prefix) {
            if ($alias === $prefix) {
                // Die alte Startseite - die ist im neuen Baum der Startpunkt
                // selbst und braucht keine Weiterleitungsseite.
                return ['alias' => '', 'problem' => self::PROBLEM_ROOT];
            }

            if (!str_starts_with($alias, $prefix.'/')) {
                return ['alias' => '', 'problem' => self::PROBLEM_PREFIX];
            }

            $alias = substr($alias, \strlen($prefix) + 1);
        }

        if ('' !== $urlSuffix) {
            if (!str_ends_with($alias, $urlSuffix)) {
                return ['alias' => '', 'problem' => self::PROBLEM_SUFFIX];
            }

            $alias = substr($alias, 0, -\strlen($urlSuffix));
        }

        $alias = trim($alias, '/');

        if ('' === $alias) {
            return ['alias' => '', 'problem' => self::PROBLEM_ROOT];
        }

        if (1 !== preg_match(self::FOLDER_ALIAS, $alias)) {
            return ['alias' => '', 'problem' => self::PROBLEM_INVALID];
        }

        if (\strlen($alias) > 255) {
            return ['alias' => '', 'problem' => self::PROBLEM_INVALID];
        }

        return ['alias' => $alias, 'problem' => null];
    }
}
