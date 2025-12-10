<?php

namespace OpenSolid\Shared\Tests;

use OpenSolid\Shared\SharedBundle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SharedBundleConfigurationTest extends TestCase
{
    #[Test]
    public function defaultConfigurationIsProperlySet(): void
    {
        $bundle = new SharedBundle();
        $configuration = $bundle->getContainerExtension()->getConfiguration([], new ContainerBuilder());

        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        $this->assertArrayHasKey('doctrine', $config);
        $this->assertArrayHasKey('orm', $config['doctrine']);
        $this->assertArrayHasKey('mapping', $config['doctrine']['orm']);
        $this->assertSame('xml', $config['doctrine']['orm']['mapping']['type']);
        $this->assertSame('/Infrastructure/Resources/config/doctrine/mapping/', $config['doctrine']['orm']['mapping']['relative_path']);
    }

    #[Test]
    public function customConfigurationOverridesDefaults(): void
    {
        $bundle = new SharedBundle();
        $configuration = $bundle->getContainerExtension()->getConfiguration([], new ContainerBuilder());

        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            'opensolid_shared' => [
                'doctrine' => [
                    'orm' => [
                        'mapping' => [
                            'type' => 'attribute',
                            'relative_path' => '/Domain/Model/',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            'doctrine' => [
                'orm' => [
                    'mapping' => [
                        'type' => 'attribute',
                        'relative_path' => '/Domain/Model/',
                    ],
                ],
            ],
        ], $config);
    }
}