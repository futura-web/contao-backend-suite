<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\SitemapEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Strips the configured news-reader-page prefix (e.g. "beitrag") from all
 * <loc> entries in the generated sitemap.xml, so "/beitrag/slug" appears as
 * "/slug". No-op if `contao_backend_suite.flat_news_url_prefix` is empty.
 *
 * Priority -100 so this runs AFTER the core News/FAQ/Calendar/Newsletter
 * subscribers have already added their URLs to the document - otherwise
 * there is nothing yet to rewrite.
 */
#[AsEventListener(event: ContaoCoreEvents::SITEMAP, priority: -100)]
class FlatNewsUrlSitemapListener
{
    public function __construct(
        #[Autowire('%contao_backend_suite.flat_news_url_prefix%')]
        private readonly string $prefix,
    ) {
    }

    public function __invoke(SitemapEvent $event): void
    {
        if ('' === $this->prefix) {
            return;
        }

        $document = $event->getDocument();
        $locNodes = $document->getElementsByTagNameNS('https://www.sitemaps.org/schemas/sitemap/0.9', 'loc');
        $needle = '/'.$this->prefix.'/';

        foreach (iterator_to_array($locNodes) as $loc) {
            $url = $loc->textContent;
            $flatUrl = preg_replace('~'.preg_quote($needle, '~').'~', '/', $url, 1);

            if (null !== $flatUrl && $flatUrl !== $url) {
                $loc->textContent = $flatUrl;
            }
        }
    }
}
