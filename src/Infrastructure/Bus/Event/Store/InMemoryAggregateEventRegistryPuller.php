<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Bus\Event\Store;

use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Domain\Event\Store\AggregateEventPuller;
use OpenSolid\Core\Domain\Event\Store\AggregateEventRegistry;

/**
 * Registers aggregates that implement the pullDomainEvents method,
 * so they can be published later.
 */
final class InMemoryAggregateEventRegistryPuller implements AggregateEventRegistry, AggregateEventPuller
{
    /** @var list<\Closure(): array<int, DomainEvent>> */
    private(set) array $pullers = [];

    public function register(object $aggregate): void
    {
        if (!method_exists($aggregate, 'pullDomainEvents')) {
            throw new \LogicException(sprintf('The aggregate "%s" does not implement the pullDomainEvents method. Did you miss the InMemoryEventStore trait?', $aggregate::class));
        }

        $this->pullers[] = $aggregate->pullDomainEvents(...);
    }

    public function pullDomainEvents(): array
    {
        if ([] === $this->pullers) {
            return [];
        }

        $events = [[]];
        foreach ($this->pullers as $puller) {
            $events[] = $puller();
        }

        return array_merge(...$events);
    }
}
