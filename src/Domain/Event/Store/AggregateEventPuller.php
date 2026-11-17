<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Event\Store;

use OpenSolid\Core\Domain\Event\Message\DomainEvent;

interface AggregateEventPuller
{
    /**
     * @return array<int, DomainEvent>
     */
    public function pullDomainEvents(): array;
}
