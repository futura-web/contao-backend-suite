<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Futuraweb\ContaoBackendSuite\RedirectWizard\CandidateImporter;

/**
 * Zeile der Vorschlagsliste: alte Adresse, Pfeil, Zielseite, Score und
 * Zustand auf einen Blick, damit falsche Zuordnungen beim Durchscrollen
 * auffallen und nicht erst nach dem Anlegen der Seiten.
 */
#[AsCallback(table: 'tl_bs_redirect', target: 'list.sorting.child_record')]
class RedirectCandidateListListener
{
    /**
     * Seitentitel werden ueber die ganze Liste hinweg gesammelt, damit
     * nicht jede Zeile eine eigene Abfrage ausloest.
     *
     * @var array<int, string|null>
     */
    private array $pageTitles = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function __invoke(array $row): string
    {
        $state = (string) ($row['state'] ?? '');
        $source = (string) ($row['source'] ?? '');
        $targetId = (int) ($row['targetPage'] ?? 0);
        $score = (int) ($row['score'] ?? 0);

        $label = $GLOBALS['TL_LANG']['tl_bs_redirect']['states'][$state] ?? $state;

        $parts = ['<span style="font-family:monospace">'.StringUtil::specialchars($source).'</span>'];

        if (0 !== $targetId) {
            $title = $this->getPageTitle($targetId);

            $parts[] = '<span style="opacity:.5">→</span> '.(
                null === $title
                    ? '<span style="color:#c33">Zielseite gelöscht (ID '.$targetId.')</span>'
                    : '<strong>'.StringUtil::specialchars($title).'</strong>'
            );
        }

        $meta = ['<span style="color:'.$this->getStateColor($state).'">'.StringUtil::specialchars((string) $label).'</span>'];

        if ($score > 0) {
            $meta[] = $score.'%';
        }

        if (0 !== (int) ($row['createdPage'] ?? 0)) {
            $meta[] = 'Seite '.(int) $row['createdPage'];
        }

        return '<div class="tl_content_left">'.implode(' ', $parts)
            .' <span class="tl_gray">['.implode(' · ', $meta).']</span></div>';
    }

    private function getPageTitle(int $id): string|null
    {
        if (!\array_key_exists($id, $this->pageTitles)) {
            $title = $this->connection->fetchOne('SELECT title FROM tl_page WHERE id = ?', [$id]);
            $this->pageTitles[$id] = false === $title ? null : (string) $title;
        }

        return $this->pageTitles[$id];
    }

    private function getStateColor(string $state): string
    {
        return match ($state) {
            CandidateImporter::STATE_READY => '#2a6',
            CandidateImporter::STATE_CREATED => '#390',
            CandidateImporter::STATE_REVIEW => '#c80',
            CandidateImporter::STATE_NOTARGET => '#c33',
            CandidateImporter::STATE_EXISTS => '#888',
            default => '#888',
        };
    }
}
