<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Projektseitig in config/packages/contao_backend_suite.yaml überschreibbare
 * Bundle-Einstellungen - bewusst keine host-/pfadspezifischen Werte im
 * Bundle-Code selbst (siehe Memory feedback-no-hardcoded-env-values):
 * jedes Projekt (c5, netcup, ...) trägt hier seinen eigenen Wert ein.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('contao_backend_suite');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('ssh_migrate_hint')
                    ->info('Wird im Feld-Builder angezeigt, falls eine DB-Spalte nicht automatisch angelegt werden konnte - idealerweise ein fertiger, kopierbarer SSH-Befehl für dieses Projekt.')
                    ->defaultValue('Datenbank manuell aktualisieren: "contao:migrate" per SSH ausführen, oder im Contao Manager unter "Contao-Einstellungen" auf "Datenbank migrieren" klicken.')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
