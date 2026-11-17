<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Event\Store;

interface AggregateEventRegistry
{
    /**
     * @param object $aggregate Aggregate implementing the pullDomainEvents method
     */
    public function register(object $aggregate): void;
}
