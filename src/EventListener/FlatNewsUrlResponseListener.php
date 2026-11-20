<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener;

use Contao\CoreBundle\ContaoCoreBundle;
use Futuraweb\ContaoBackendSuite\FlatUrl\FlatUrlPageAliasRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Strips every reader page alias flagged via "backendSuiteFlatUrl" (e.g.
 * "beitrag") from every rendered frontend HTML response, so
 * teasers/listings/navigation link to "/slug" instead of "/beitrag/slug"
 * sitewide. No-op if no page is flagged.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -100)]
class FlatNewsUrlResponseListener
{
    public function __construct(private readonly FlatUrlPageAliasRegistry $aliases)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (ContaoCoreBundle::SCOPE_FRONTEND !== $event->getRequest()->attributes->get('_scope')) {
            return;
        }

        $response = $event->getResponse();

        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $aliases = $this->aliases->getFlaggedAliases();

        if ([] === $aliases) {
            return;
        }

        $content = $response->getContent();

        if (false === $content) {
            return;
        }

        $changed = false;

        foreach ($aliases as $alias) {
            $needle = '/'.$alias.'/';

            if (str_contains($content, $needle)) {
                $content = str_replace($needle, '/', $content);
                $changed = true;
            }
        }

        if ($changed) {
            $response->setContent($content);
        }
    }
}
