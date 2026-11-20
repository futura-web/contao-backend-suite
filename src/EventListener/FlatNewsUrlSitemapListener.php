<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\SitemapEvent;
use Futuraweb\ContaoBackendSuite\FlatUrl\FlatUrlPageAliasRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Strips every reader page alias flagged via "backendSuiteFlatUrl" (e.g.
 * "beitrag") from the <loc> entries of the generated sitemap.xml, so
 * "/beitrag/slug" appears as "/slug". No-op if no page is flagged.
 *
 * Priority -100 so this runs AFTER the core News/FAQ/Calendar/Newsletter
 * subscribers have already added their URLs to the document - otherwise
 * there is nothing yet to rewrite.
 */
#[AsEventListener(event: ContaoCoreEvents::SITEMAP, priority: -100)]
class FlatNewsUrlSitemapListener
{
    public function __construct(private readonly FlatUrlPageAliasRegistry $aliases)
    {
    }

    public function __invoke(SitemapEvent $event): void
    {
        $aliases = $this->aliases->getFlaggedAliases();

        if ([] === $aliases) {
            return;
        }

        $document = $event->getDocument();
        $locNodes = $document->getElementsByTagNameNS('https://www.sitemaps.org/schemas/sitemap/0.9', 'loc');

        foreach (iterator_to_array($locNodes) as $loc) {
            $url = $loc->textContent;

            foreach ($aliases as $alias) {
                $needle = '/'.$alias.'/';

                if (str_contains($url, $needle)) {
                    $loc->textContent = preg_replace('~'.preg_quote($needle, '~').'~', '/', $url, 1);
                    break;
                }
            }
        }
    }
}
