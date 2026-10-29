<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Futuraweb\ContaoBackendSuite\Toolbar\CurrentThemeTracker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: -100)]
class CurrentThemeRequestListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly CurrentThemeTracker $tracker,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->scopeMatcher->isBackendRequest($event->getRequest())) {
            return;
        }

        $this->tracker->detectFromRequest();
    }
}
