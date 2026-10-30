<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Futuraweb\ContaoBackendSuite\Upload\ThemeUploadSettings;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles clicks on the "shrink + rename on upload" toggle button in the
 * file manager: flips the setting for the current theme, then redirects
 * back to the clean URL so reloading the page doesn't toggle it again.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -101)]
class UploadToggleRequestListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly ThemeUploadSettings $settings,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$this->scopeMatcher->isBackendRequest($request)) {
            return;
        }

        if (!$request->query->has('backendSuiteToggleShrink')) {
            return;
        }

        $this->settings->toggleForCurrentTheme();

        $query = $request->query->all();
        unset($query['backendSuiteToggleShrink']);

        $event->setResponse(new RedirectResponse($request->getBaseUrl().$request->getPathInfo().([] !== $query ? '?'.http_build_query($query) : '')));
    }
}
