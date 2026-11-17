<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Bus\Event\Store;

use OpenSolid\Core\Domain\Event\Store\AggregateEventPuller;

final readonly class AggregateEventPullerChain implements AggregateEventPuller
{
    /**
     * @param iterable<AggregateEventPuller> $eventPullers
     */
    public function __construct(
        private iterable $eventPullers,
    ) {
    }

    public function pullDomainEvents(): array
    {
        $events = [[]];
        foreach ($this->eventPullers as $eventPuller) {
            $events[] = $eventPuller->pullDomainEvents();
        }

        return array_merge(...$events);
    }
}
