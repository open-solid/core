<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests;

use OpenSolid\Core\CoreBundle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CoreBundleConfigurationTest extends TestCase
{
    #[Test]
    public function defaultConfigurationIsProperlySet(): void
    {
        $bundle = new CoreBundle();
        $extension = $bundle->getContainerExtension();
        $this->assertNotNull($extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        $this->assertNotNull($configuration);

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
            'twig' => [
                'templates' => [
                    'relative_path' => '/Presentation/Resources/templates',
                ],
            ],
            'translation' => [
                'translations' => [
                    'relative_path' => '/Presentation/Resources/translations',
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
        $bundle = new CoreBundle();
        $extension = $bundle->getContainerExtension();
        $this->assertNotNull($extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        $this->assertNotNull($configuration);

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
                'twig' => [
                    'templates' => [
                        'relative_path' => '/UI/Resources/templates',
                    ],
                ],
                'translation' => [
                    'translations' => [
                        'relative_path' => '/UI/Resources/translations',
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
            'twig' => [
                'templates' => [
                    'relative_path' => '/UI/Resources/templates',
                ],
            ],
            'translation' => [
                'translations' => [
                    'relative_path' => '/UI/Resources/translations',
                ],
            ],
            'bus' => [
                'strategy' => 'native',
            ],
        ], $config);
    }
}
