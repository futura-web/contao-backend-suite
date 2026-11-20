<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\RedirectWizard;

/**
 * Fehler beim Abruf der CDX-API. Wird im Backend als Fehlermeldung
 * angezeigt, bricht den Import aber nicht hart ab - der Redakteur kann
 * stattdessen eine URL-Liste einfuegen.
 */
class WaybackException extends \RuntimeException
{
}
