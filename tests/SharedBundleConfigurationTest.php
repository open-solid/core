<?php

declare(strict_types=1);

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

        $this->assertSame([
            'doctrine' => [
                'orm' => [
                    'mapping' => [
                        'type' => 'xml',
                        'relative_path' => '/Infrastructure/Resources/config/doctrine/mapping/',
                    ],
                ],
            ],
            'api_platform' => [
                'resources' => [
                    'mapping' => [
                        'relative_path' => '/Infrastructure/Resources/config/api_platform/mapping/',
                    ],
                ],
            ],
            'bus' => [
                'strategy' => 'symfony',
            ],
        ], $config);
    }

    #[Test]
    public function customConfigurationOverridesDefaults(): void
    {
        $bundle = new SharedBundle();
        $configuration = $bundle->getContainerExtension()->getConfiguration([], new ContainerBuilder());

        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, [
            'opensolid' => [
                'doctrine' => [
                    'orm' => [
                        'mapping' => [
                            'type' => 'attribute',
                            'relative_path' => '/Domain/Model/',
                        ],
                    ],
                ],
                'api_platform' => [
                    'resources' => [
                        'mapping' => [
                            'relative_path' => '/path/to/api_platform',
                        ],
                    ],
                ],
                'bus' => [
                    'strategy' => 'native',
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
            'api_platform' => [
                'resources' => [
                    'mapping' => [
                        'relative_path' => '/path/to/api_platform',
                    ],
                ],
            ],
            'bus' => [
                'strategy' => 'native',
            ],
        ], $config);
    }
}
