<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\Routing;

use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResult;
use Contao\NewsModel;
use Contao\PageModel;

/**
 * Gegenrichtung zu FlatNewsPage: liefert beim Erzeugen von News-Links den
 * Alias fuer die Route /{alias}.
 *
 * Welche Seite zustaendig ist, entscheidet weiterhin Contaos eigener
 * NewsResolver - deshalb gibt resolve() hier null zurueck. Contao sammelt die
 * Parameter aller Resolver ein und verwendet nur die, welche die gewaehlte
 * Route tatsaechlich kennt.
 *
 * Dadurch entstehen flache URLs schon bei der Erzeugung, nicht erst durch
 * nachtraegliches Ersetzen im fertigen HTML.
 */
class FlatNewsUrlResolver implements ContentUrlResolverInterface
{
    public function resolve(object $content): ContentUrlResult|null
    {
        return null;
    }

    public function getParametersForContent(object $content, PageModel $pageModel): array
    {
        if (!$content instanceof NewsModel || FlatNewsPage::TYPE !== $pageModel->type) {
            return [];
        }

        return ['alias' => $content->alias ?: (string) $content->id];
    }
}
