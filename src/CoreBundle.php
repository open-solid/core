<?php

declare(strict_types=1);

namespace OpenSolid\Core;

use Doctrine\ORM\Events;
use OpenSolid\Bus\Bridge\Symfony\DependencyInjection\CompilerPass\HandlingMiddlewarePass;
use OpenSolid\Bus\Bridge\Symfony\DependencyInjection\Configurator\MessageHandlerConfigurator;
use OpenSolid\Core\Application\Command\Handler\Attribute\AsCommandHandler;
use OpenSolid\Core\Application\Query\Handler\Attribute\AsQueryHandler;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\StampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Event\Subscriber\Attribute\AsDomainEventSubscriber;
use OpenSolid\Core\Infrastructure\Symfony\DependencyInjection\Compiler\RegisterGenericDbalTypesPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Messenger\MessageBusInterface;

class CoreBundle extends AbstractBundle
{
    protected string $extensionAlias = 'opensolid';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterGenericDbalTypesPass($container));
        $container->addCompilerPass(new HandlingMiddlewarePass('cqs.command.handler', 'cqs.command.middleware.handler', topic: 'command'));
        $container->addCompilerPass(new HandlingMiddlewarePass('cqs.query.handler', 'cqs.query.middleware.handler', topic: 'query'));
        $container->addCompilerPass(new HandlingMiddlewarePass('domain.event.subscriber', 'domain.event.subscriber.middleware', [], true, 'event'));
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (interface_exists(MessageBusInterface::class)) {
            $container->import('../config/packages/messenger.php');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (class_exists(Events::class)) {
            $container->import('../config/services.php');
        }

        if ('native' === $config['bus']['strategy']) {
            MessageHandlerConfigurator::configure($builder, AsCommandHandler::class, 'cqs.command.handler');
            MessageHandlerConfigurator::configure($builder, AsQueryHandler::class, 'cqs.query.handler');
            MessageHandlerConfigurator::configure($builder, AsDomainEventSubscriber::class, 'domain.event.subscriber');

            $container->import('../config/native.php');
        } elseif ('symfony' === $config['bus']['strategy']) {
            if (!interface_exists(MessageBusInterface::class)) {
                throw new \LogicException('The "symfony" strategy requires symfony/messenger package.');
            }

            $builder->registerForAutoconfiguration(StampTransformer::class)
                ->addTag('envelope.stamp_transformer');

            MessageHandlerConfigurator::configure($builder, AsCommandHandler::class, 'messenger.message_handler', ['bus' => 'command.bus']);
            MessageHandlerConfigurator::configure($builder, AsQueryHandler::class, 'messenger.message_handler', ['bus' => 'query.bus']);
            MessageHandlerConfigurator::configure($builder, AsDomainEventSubscriber::class, 'messenger.message_handler', ['bus' => 'event.bus']);

            $container->import('../config/messenger.php');
        }
    }
}
