<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener\Menu;

use Contao\CoreBundle\Event\MenuEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Öffnet den "Contao Manager"-Link in der Hauptnavigation (System-Kategorie)
 * in einem neuen Tab statt im aktuellen - ein Klick darauf verlässt sonst
 * die gerade bearbeitete Backend-Seite ohne Rückfrage.
 *
 * Niedrige Priorität, damit der Core-Listener
 * (Contao\ManagerBundle\EventListener\BackendMenuListener::addManagerLink,
 * Standard-Priorität 0), der dieses Menüitem überhaupt erst anlegt, in
 * jedem Fall vorher gelaufen ist.
 */
#[AsEventListener(priority: -100)]
class ContaoManagerLinkListener
{
    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();

        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        $item = $tree->getChild('system')?->getChild('contao_manager');

        if (!$item) {
            return;
        }

        $item->setLinkAttribute('target', '_blank');
        $item->setLinkAttribute('rel', 'noopener');
        $item->setLinkAttribute('class', trim($item->getLinkAttribute('class').' backend-suite-external-link'));
    }
}
