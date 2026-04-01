<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Symfony\Module;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\AbstractExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

abstract class ModuleExtension extends AbstractExtension
{
    protected private(set) string $path {
        get {
            return $this->path ??= \dirname(new \ReflectionObject($this)->getFileName(), 2);
        }
    }

    protected private(set) string $namespace {
        get {
            return $this->namespace ??= preg_replace('/\\\\Infrastructure\\\\[^\\\\]+$/', '', $this::class);
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = $this->getConfig($builder);

        $this->configureTwigTemplates($container, $builder, $config);
        $this->configureTranslations($container, $builder, $config);
        $this->configureDoctrineMapping($container, $builder, $config);

        if (\is_dir($this->path.'/Infrastructure/Resources/config/packages')) {
            $container->import($this->path.'/Infrastructure/Resources/config/packages/*.yaml');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (\is_dir($this->path.'/Infrastructure/Resources/config')) {
            $container->import($this->path.'/Infrastructure/Resources/config/{services.yaml}');
        }
    }

    public function getAlias(): string
    {
        return 'app_'.parent::getAlias();
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function configureTwigTemplates(ContainerConfigurator $container, ContainerBuilder $builder, array $config): void
    {
        if (!$builder->hasExtension('twig') || !\is_dir($dir = $this->path.$config['twig']['templates']['relative_path'])) {
            return;
        }

        $moduleName = preg_replace('/Extension$/', '', new \ReflectionClass($this)->getShortName());

        $container->extension('twig', [
            'paths' => [
                $dir => $moduleName,
            ],
        ], true);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function configureTranslations(ContainerConfigurator $container, ContainerBuilder $builder, array $config): void
    {
        if (!$builder->hasExtension('framework') || !\is_dir($dir = $this->path.$config['translation']['translations']['relative_path'])) {
            return;
        }

        $container->extension('framework', [
            'translator' => [
                'paths' => [$dir],
            ],
        ], true);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function configureDoctrineMapping(ContainerConfigurator $container, ContainerBuilder $builder, array $config): void
    {
        if (!$builder->hasExtension('doctrine') || !\is_dir($this->path.'/Domain/Model')) {
            return;
        }

        if (!\is_dir($dir = $this->path.$config['doctrine']['orm']['mapping']['relative_path'])) {
            mkdir($dir, 0750, true);
        }

        $container->extension('doctrine', [
            'orm' => [
                'mappings' => [
                    $this->namespace => [
                        'type' => $config['doctrine']['orm']['mapping']['type'],
                        'is_bundle' => false,
                        'dir' => $dir,
                        'prefix' => $this->namespace.'\\Domain\\Model',
                        'alias' => $this->namespace.'\\Domain\\Model',
                    ],
                ],
            ],
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(ContainerBuilder $builder): array
    {
        /** @var AbstractExtension $extension */
        $extension = $builder->getExtension('opensolid');
        $configuration = $extension->getConfiguration([], $builder);

        if (null === $configuration) {
            return [];
        }

        return new Processor()->processConfiguration($configuration, $builder->getExtensionConfig('opensolid'));
    }
}
