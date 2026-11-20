<?php

declare(strict_types=1);

namespace Futuraweb\ContaoBackendSuite\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ContaoBackendSuiteExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('contao_backend_suite.ssh_migrate_hint', $config['ssh_migrate_hint']);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'contao_backend_suite';
    }
}
