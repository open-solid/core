<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Bus\Event;

use OpenSolid\Core\Domain\Event\Bus\EventBus;
use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\EnvelopeStampTrait;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\StampTransformer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final class SymfonyEventBus implements EventBus
{
    use EnvelopeStampTrait;

    public function __construct(
        private readonly MessageBusInterface $eventBus,
        StampTransformer $stampTransformer,
    ) {
        $this->stampTransformer = $stampTransformer;
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            // Domain events are published from inside the command's Doctrine
            // transaction (SymfonyEventPublisherMiddleware runs after the handler
            // but before commit). The DispatchAfterCurrentBusStamp defers the
            // actual send until the outermost command dispatch finishes — i.e.
            // after the transaction commits — and the shared
            // DispatchAfterCurrentBusMiddleware drops the queue if that dispatch
            // throws. This is what enforces the "dispatch after commit, never on
            // rollback" contract for async-routed events.
            $this->eventBus->dispatch(
                $this->createEnvelope($event)->with(new DispatchAfterCurrentBusStamp()),
            );
        }
    }
}
