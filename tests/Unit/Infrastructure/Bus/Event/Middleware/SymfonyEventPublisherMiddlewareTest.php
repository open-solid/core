<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Event\Middleware;

use OpenSolid\Core\Domain\Event\Bus\EventBus;
use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Domain\Event\Store\AggregateEventPuller;
use OpenSolid\Core\Infrastructure\Bus\Event\Middleware\SymfonyEventPublisherMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class SymfonyEventPublisherMiddlewareTest extends TestCase
{
    #[Test]
    public function publishesPulledEventsAfterNextMiddleware(): void
    {
        $event1 = new TestPublishedEvent('aggregate-1');
        $event2 = new TestPublishedEvent('aggregate-2');

        $eventPuller = $this->createMock(AggregateEventPuller::class);
        $eventPuller->expects($this->once())
            ->method('pullDomainEvents')
            ->willReturn([$event1, $event2]);

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects($this->once())
            ->method('publish')
            ->with($event1, $event2);

        $envelope = new Envelope(new \stdClass());
        $stack = $this->createMock(StackInterface::class);
        $next = $this->createMock(MiddlewareInterface::class);
        $next->expects($this->once())
            ->method('handle')
            ->with($envelope, $stack)
            ->willReturn($envelope);
        $stack->expects($this->once())
            ->method('next')
            ->willReturn($next);

        $middleware = new SymfonyEventPublisherMiddleware($eventPuller, $eventBus);

        $this->assertSame($envelope, $middleware->handle($envelope, $stack));
    }

    #[Test]
    public function publishesNothingWhenNoEventsPulled(): void
    {
        $eventPuller = $this->createMock(AggregateEventPuller::class);
        $eventPuller->expects($this->once())
            ->method('pullDomainEvents')
            ->willReturn([]);

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects($this->once())
            ->method('publish');

        $envelope = new Envelope(new \stdClass());
        $stack = $this->createStub(StackInterface::class);
        $next = $this->createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturn($envelope);
        $stack->method('next')->willReturn($next);

        $middleware = new SymfonyEventPublisherMiddleware($eventPuller, $eventBus);
        $middleware->handle($envelope, $stack);
    }
}

final readonly class TestPublishedEvent extends DomainEvent
{
}
