<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\DynamicField;

use Contao\Controller;
use Contao\CoreBundle\Config\ResourceFinderInterface;
use Contao\DC_Table;

/**
 * Findet die Tabellen, die der Feld-Builder erweitern darf: ein Scan
 * aller registrierten DCA-Dateien (jede DC_Table-Tabelle mit einer
 * "default"-Palette, nach demselben Muster wie
 * CssStyle\StyleSelectorTableRegistry), eingeschränkt auf eine bewusst
 * kuratierte Liste echter Content-Tabellen (CONTENT_TABLES) - technische
 * Tabellen (Benutzer, Layouts, Themes, Module, interne Bundle-Tabellen
 * usw.) tauchen dadurch gar nicht erst in der Auswahl auf.
 *
 * Nutzerentscheidung (2026-11-01): bewusst eng gehalten statt "alles was
 * technisch geht" - Inhaltselemente (tl_content) sind ausdrücklich NICHT
 * dabei, weil sie pro Typ (Text, HTML, Bild, ...) eine eigene Palette
 * haben und der aktuelle Injektions-Mechanismus (ein Feld in EINE
 * "default"-Palette) sie nicht sinnvoll unterstützen kann - das wäre ein
 * eigener, größerer Folge-Schritt mit Typ-Auswahl.
 */
class DynamicFieldTableRegistry
{
    public const NEW_LEGEND_OPTION = '__new__';

    private const OWN_TABLES = ['tl_dynamic_field'];

    /** @var array<string, string> Tabellenname => verständliches Label */
    private const CONTENT_TABLES = [
        'tl_calendar_events' => 'Termine',
        'tl_news' => 'Nachrichten',
        'tl_faq' => 'FAQ',
    ];

    /** @var list<string>|null */
    private array|null $tables = null;

    private bool $scanning = false;

    public function __construct(private readonly ResourceFinderInterface $resourceFinder)
    {
    }

    /** @return array<string, string> Tabellenname => Anzeige-Label */
    public function getTableOptions(): array
    {
        $options = [];

        foreach ($this->getTables() as $table) {
            $options[$table] = self::CONTENT_TABLES[$table] ?? $table;
        }

        return $options;
    }

    /**
     * Alle "{xxx_legend}"-Abschnitte der "default"-Palette der gegebenen
     * Tabelle, plus die Option "neuer eigener Abschnitt".
     *
     * @return array<string, string>
     */
    public function getLegendOptions(string|null $table): array
    {
        $options = [self::NEW_LEGEND_OPTION => 'Neuer eigener Abschnitt am Ende'];

        if (null === $table || '' === $table || !\in_array($table, $this->getTables(), true)) {
            return $options;
        }

        Controller::loadDataContainer($table);

        $palette = $GLOBALS['TL_DCA'][$table]['palettes']['default'] ?? '';

        if (!\is_string($palette)) {
            return $options;
        }

        preg_match_all('/\{([a-zA-Z0-9_]+_legend)[^}]*\}/', $palette, $matches);

        foreach (array_unique($matches[1] ?? []) as $legendKey) {
            $options[$legendKey] = $GLOBALS['TL_LANG'][$table][$legendKey] ?? $legendKey;
        }

        return $options;
    }

    /** @return list<string> */
    public function getTables(): array
    {
        if (null !== $this->tables) {
            return $this->tables;
        }

        // Ein Kandidat-Tabellen-Scan laedt per loadDataContainer() jede
        // Tabelle einmal durch, was den Hook, der diese Methode aufruft,
        // erneut triggert (Re-Entrancy) - siehe StyleSelectorTableRegistry
        // fuer das gleiche, dort schon geloeste Problem.
        if ($this->scanning) {
            return [];
        }

        $this->scanning = true;
        $tables = [];

        foreach ($this->findCandidateTableNames() as $table) {
            if (\in_array($table, self::OWN_TABLES, true) || !isset(self::CONTENT_TABLES[$table])) {
                continue;
            }

            Controller::loadDataContainer($table);

            $dca = $GLOBALS['TL_DCA'][$table] ?? [];

            if (DC_Table::class === ($dca['config']['dataContainer'] ?? null) && \is_string($dca['palettes']['default'] ?? null)) {
                $tables[] = $table;
            }
        }

        sort($tables);

        $this->scanning = false;
        $this->tables = $tables;

        return $this->tables;
    }

    /** @return list<string> */
    private function findCandidateTableNames(): array
    {
        $tables = [];

        foreach ($this->resourceFinder->findIn('dca')->files()->name('*.php') as $file) {
            $tables[] = $file->getBasename('.php');
        }

        return array_values(array_unique($tables));
    }
}
