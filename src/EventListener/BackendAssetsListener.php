<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Loads assets that should apply across the whole backend, not just a
 * single DCA table.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -50)]
class BackendAssetsListener
{
    public function __construct(private readonly ScopeMatcher $scopeMatcher)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->scopeMatcher->isBackendRequest($event->getRequest())) {
            return;
        }

        $GLOBALS['TL_CSS'][] = 'bundles/contaobackendsuite/save-buttons.css';
    }
}
