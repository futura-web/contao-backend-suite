<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Futuraweb\ContaoBackendSuite\Routing\FlatNewsPage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Leitet die alte, verschachtelte Form dauerhaft auf die flache um:
 * "/<leseseite>/<artikel-alias>" -> "/<artikel-alias>".
 *
 * Eine Seite vom Typ FlatNewsPage belegt ihren eigenen Alias nicht mehr
 * (ihre Route ist "/{alias}"), weshalb die alte Form ins Leere laeuft.
 * Genau diese 404 wird hier abgefangen - der Listener greift also nur,
 * wenn ohnehin nichts anderes mehr zustaendig war, und kostet im
 * Normalbetrieb nichts.
 *
 * Damit entfaellt die projektseitige .htaccess-Regel, die diese
 * Umleitung bisher uebernehmen musste.
 *
 * Bewusst NICHT behandelt: der blosse Aufruf der Leseseite ohne Artikel
 * ("/<leseseite>"). Wohin der sinnvoll zeigt - Uebersichtsseite,
 * Elternseite, Startseite - haengt vom Projekt ab und laesst sich nicht
 * allgemein entscheiden; er bleibt daher ein regulaerer 404.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 64)]
class FlatNewsLegacyUrlListener
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();
        $path = ltrim($request->getPathInfo(), '/');
        $slash = strpos($path, '/');

        // Ohne zweites Segment gibt es nichts umzuleiten.
        if (false === $slash || $slash === \strlen($path) - 1) {
            return;
        }

        $first = substr($path, 0, $slash);
        $rest = substr($path, $slash + 1);

        if ('' === $first || '' === $rest || !$this->isFlatNewsAlias($first)) {
            return;
        }

        $target = '/'.$rest;
        $query = $request->server->get('QUERY_STRING');

        if ($query) {
            $target .= '?'.$query;
        }

        $event->setResponse(new RedirectResponse($target, RedirectResponse::HTTP_MOVED_PERMANENTLY));
    }

    private function isFlatNewsAlias(string $alias): bool
    {
        try {
            return (bool) $this->connection->fetchOne(
                'SELECT TRUE FROM tl_page WHERE type = ? AND alias = ? LIMIT 1',
                [FlatNewsPage::TYPE, $alias],
            );
        } catch (DbalException) {
            return false;
        }
    }
}
