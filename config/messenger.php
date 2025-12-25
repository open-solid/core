<?php

use Doctrine\ORM\EntityManagerInterface;
use OpenSolid\Core\Application\Command\Bus\CommandBus;
use OpenSolid\Core\Application\Query\Bus\QueryBus;
use OpenSolid\Core\Domain\Event\Bus\EventBus;
use OpenSolid\Core\Infrastructure\Bus\Command\SymfonyCommandBus;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\ChainStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\DefaultStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\StampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Event\Middleware\SymfonyEventPublisherMiddleware;
use OpenSolid\Core\Infrastructure\Bus\Event\SymfonyEventBus;
use OpenSolid\Core\Infrastructure\Bus\Query\SymfonyQueryBus;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('envelope.stamp_transformer.default', DefaultStampTransformer::class)
            ->tag('envelope.stamp_transformer')

        ->set('envelope.stamp_transformer.chain', ChainStampTransformer::class)
            ->args([
                tagged_iterator('envelope.stamp_transformer'),
            ])

        ->alias(StampTransformer::class, 'envelope.stamp_transformer.chain')

        ->set('cqs.command.bus', SymfonyCommandBus::class)
            ->args([
                service('command.bus'),
                service('envelope.stamp_transformer.chain'),
            ])

        ->alias(CommandBus::class, 'cqs.command.bus')

        ->set('cqs.query.bus', SymfonyQueryBus::class)
            ->args([
                service('query.bus'),
                service('envelope.stamp_transformer.chain'),
            ])

        ->alias(QueryBus::class, 'cqs.query.bus')

        ->set('domain.event.bus', SymfonyEventBus::class)
            ->args([
                service('event.bus'),
                service('envelope.stamp_transformer.chain'),
            ])

        ->alias(EventBus::class, 'domain.event.bus')

        ->set('domain.event.bus.publisher.middleware', SymfonyEventPublisherMiddleware::class)
            ->args([
                service(EntityManagerInterface::class),
                service('domain.event.bus'),
            ])

        ->alias('publish_domain_events', 'domain.event.bus.publisher.middleware')
    ;
};
