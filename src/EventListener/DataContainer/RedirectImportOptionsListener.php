<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Doctrine\DBAL\Connection;

/**
 * Auswahlliste der Startpunkte fuer einen Weiterleitungs-Import.
 *
 * Bewusst eine Auswahlliste und kein Seitenbaum-Picker: der Startpunkt
 * bestimmt urlPrefix und urlSuffix und damit, welche alten URLs ueberhaupt
 * als Alias darstellbar sind. Eine beliebige Unterseite waere hier eine
 * falsche Eingabe, die der Picker nicht verhindern koennte.
 */
#[AsCallback(table: 'tl_bs_redirect_import', target: 'fields.rootPage.options')]
class RedirectImportOptionsListener
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<int, string>
     */
    public function __invoke(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, title, dns, language, urlPrefix, urlSuffix FROM tl_page WHERE type = 'root' ORDER BY sorting",
        );

        $options = [];

        foreach ($rows as $row) {
            $label = (string) $row['title'];
            $details = [];

            if ('' !== (string) $row['dns']) {
                $details[] = (string) $row['dns'];
            }

            $details[] = (string) $row['language'];

            // Prefix und Suffix als Schema anzeigen: daran erkennt der
            // Redakteur sofort, in welche Form seine alten URLs passen
            // muessen - "/…" ohne beides, "/de/….html" mit beidem.
            $prefix = trim((string) $row['urlPrefix'], '/');
            $details[] = '/'.('' !== $prefix ? $prefix.'/' : '').'…'.(string) $row['urlSuffix'];

            $options[(int) $row['id']] = $label.' ['.implode(', ', $details).']';
        }

        return $options;
    }
}
