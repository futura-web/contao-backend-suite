<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener;

use Contao\CoreBundle\ContaoCoreBundle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Strips the configured news-reader-page prefix (e.g. "beitrag") from every
 * rendered frontend HTML response, so teasers/listings/navigation link to
 * "/slug" instead of "/beitrag/slug" sitewide. No-op if
 * `contao_backend_suite.flat_news_url_prefix` is empty.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -100)]
class FlatNewsUrlResponseListener
{
    public function __construct(
        #[Autowire('%contao_backend_suite.flat_news_url_prefix%')]
        private readonly string $prefix,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if ('' === $this->prefix || !$event->isMainRequest()) {
            return;
        }

        if (ContaoCoreBundle::SCOPE_FRONTEND !== $event->getRequest()->attributes->get('_scope')) {
            return;
        }

        $response = $event->getResponse();

        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $content = $response->getContent();
        $needle = '/'.$this->prefix.'/';

        if (false === $content || !str_contains($content, $needle)) {
            return;
        }

        $response->setContent(str_replace($needle, '/', $content));
    }
}
