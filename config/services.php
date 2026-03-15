<?php

use Doctrine\ORM\Events;
use OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM\Mapping\AutoMapGenericTypes;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container
        ->services()
            ->set(AutoMapGenericTypes::class)
                ->tag('doctrine.event_listener', ['event' => Events::loadClassMetadata])
    ;
};
