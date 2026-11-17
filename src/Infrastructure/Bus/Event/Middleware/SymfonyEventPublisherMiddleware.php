<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Bus\Event\Middleware;

use OpenSolid\Core\Domain\Event\Bus\EventBus;
use OpenSolid\Core\Domain\Event\Store\AggregateEventPuller;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class SymfonyEventPublisherMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AggregateEventPuller $eventPuller,
        private EventBus $eventBus,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);

        $this->eventBus->publish(...$this->eventPuller->pullDomainEvents());

        return $envelope;
    }
}
