<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Event;

use OpenSolid\Bus\LazyMessageBus;
use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Infrastructure\Bus\Event\NativeEventBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NativeEventBusTest extends TestCase
{
    #[Test]
    public function publishesSingleEvent(): void
    {
        $event = new TestEvent('aggregate-1');

        $messageBus = $this->createMock(LazyMessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $bus = new NativeEventBus($messageBus);
        $bus->publish($event);
    }

    #[Test]
    public function publishesMultipleEvents(): void
    {
        $event1 = new TestEvent('aggregate-1');
        $event2 = new TestEvent('aggregate-2');
        $event3 = new TestEvent('aggregate-3');

        $messageBus = $this->createMock(LazyMessageBus::class);
        $messageBus->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (DomainEvent $event) use ($event1, $event2, $event3) {
                static $callIndex = 0;
                $expectedEvents = [$event1, $event2, $event3];
                $this->assertSame($expectedEvents[$callIndex], $event);
                $callIndex++;
            });

        $bus = new NativeEventBus($messageBus);
        $bus->publish($event1, $event2, $event3);
    }

    #[Test]
    public function publishesNoEventsWhenEmpty(): void
    {
        $messageBus = $this->createMock(LazyMessageBus::class);
        $messageBus->expects($this->never())
            ->method('dispatch');

        $bus = new NativeEventBus($messageBus);
        $bus->publish();
    }

    #[Test]
    public function flushDelegatesToMessageBus(): void
    {
        $messageBus = $this->createMock(LazyMessageBus::class);
        $messageBus->expects($this->once())
            ->method('flush');

        $bus = new NativeEventBus($messageBus);
        $bus->flush();
    }

    #[Test]
    public function publishAndFlush(): void
    {
        $event = new TestEvent('aggregate-1');

        $messageBus = $this->createMock(LazyMessageBus::class);

        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $messageBus->expects($this->once())
            ->method('flush');

        $bus = new NativeEventBus($messageBus);
        $bus->publish($event);
        $bus->flush();
    }
}

final readonly class TestEvent extends DomainEvent
{
}
