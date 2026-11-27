<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\Routing;

use Contao\PageModel;
use Symfony\Cmf\Component\Routing\Enhancer\RouteEnhancerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Uebersetzt den Alias aus der Route /{alias} in "parameters", genau wie
 * Contao es bei normalen Leseseiten tut.
 *
 * Priority 10, damit der Enhancer vor Contaos InputEnhancer (Priority 0)
 * laeuft - erst dadurch wird aus "parameters" das auto_item, das der
 * Nachrichtenleser auswertet.
 */
#[AutoconfigureTag('contao.page_router_enhancer', ['priority' => 10])]
class FlatNewsRouteEnhancer implements RouteEnhancerInterface
{
    public function enhance(array $defaults, Request $request): array
    {
        $page = $defaults['pageModel'] ?? null;

        if (
            $page instanceof PageModel
            && FlatNewsPage::TYPE === $page->type
            && !empty($defaults['alias'])
        ) {
            $defaults['parameters'] = '/'.$defaults['alias'];
        }

        return $defaults;
    }
}
