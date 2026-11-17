<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Event\Store;

use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Domain\Event\Store\AggregateEventPuller;
use OpenSolid\Core\Infrastructure\Bus\Event\Store\AggregateEventPullerChain;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AggregateEventPullerChainTest extends TestCase
{
    #[Test]
    public function returnsEmptyArrayWhenNoPullers(): void
    {
        $chain = new AggregateEventPullerChain([]);

        $this->assertSame([], $chain->pullDomainEvents());
    }

    #[Test]
    public function mergesEventsFromAllPullersInOrder(): void
    {
        $event1 = new TestChainEvent('aggregate-1');
        $event2 = new TestChainEvent('aggregate-2');
        $event3 = new TestChainEvent('aggregate-3');

        $pullerA = $this->createStub(AggregateEventPuller::class);
        $pullerA->method('pullDomainEvents')->willReturn([$event1, $event2]);

        $pullerB = $this->createStub(AggregateEventPuller::class);
        $pullerB->method('pullDomainEvents')->willReturn([$event3]);

        $chain = new AggregateEventPullerChain([$pullerA, $pullerB]);

        $this->assertSame([$event1, $event2, $event3], $chain->pullDomainEvents());
    }

    #[Test]
    public function skipsEmptyResultsTransparently(): void
    {
        $event = new TestChainEvent('aggregate-1');

        $pullerA = $this->createStub(AggregateEventPuller::class);
        $pullerA->method('pullDomainEvents')->willReturn([]);

        $pullerB = $this->createStub(AggregateEventPuller::class);
        $pullerB->method('pullDomainEvents')->willReturn([$event]);

        $chain = new AggregateEventPullerChain([$pullerA, $pullerB]);

        $this->assertSame([$event], $chain->pullDomainEvents());
    }
}

final readonly class TestChainEvent extends DomainEvent
{
}
