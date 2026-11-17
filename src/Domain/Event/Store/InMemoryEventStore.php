<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Event\Store;

use OpenSolid\Core\Domain\Event\Message\DomainEvent;

trait InMemoryEventStore
{
    /**
     * @var list<DomainEvent>
     */
    private array $domainEvents = [];

    final protected function pushDomainEvent(DomainEvent $domainEvent): void
    {
        $this->domainEvents[] = $domainEvent;
    }

    /**
     * @return list<DomainEvent>
     */
    final public function pullDomainEvents(): array
    {
        $domainEvents = $this->domainEvents;
        $this->domainEvents = [];

        return $domainEvents;
    }
}
