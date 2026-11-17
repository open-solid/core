<?php

use OpenSolid\Core\Infrastructure\Bus\Event\Serializer\DomainEventNormalizer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container
        ->services()
            ->set('domain.event.serializer.normalizer', DomainEventNormalizer::class)
                ->tag('serializer.normalizer')
    ;
};
