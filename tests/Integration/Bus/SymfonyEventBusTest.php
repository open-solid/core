<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Bus;

use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;
use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\ChainStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\DefaultStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Event\SymfonyEventBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class SymfonyEventBusTest extends TestCase
{
    private ChainStampTransformer $stampTransformer;

    protected function setUp(): void
    {
        $this->stampTransformer = new ChainStampTransformer([
            new DefaultStampTransformer(),
        ]);
    }

    #[Test]
    public function publishesSingleEvent(): void
    {
        $event = new TestIntegrationEvent('aggregate-1');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(Envelope::class))
            ->willReturnCallback(fn (Envelope $e) => $e);

        $bus = new SymfonyEventBus($messageBus, $this->stampTransformer);
        $bus->publish($event);
    }

    #[Test]
    public function publishesMultipleEvents(): void
    {
        $event1 = new TestIntegrationEvent('aggregate-1');
        $event2 = new TestIntegrationEvent('aggregate-2');
        $event3 = new TestIntegrationEvent('aggregate-3');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnCallback(fn (Envelope $e) => $e);

        $bus = new SymfonyEventBus($messageBus, $this->stampTransformer);
        $bus->publish($event1, $event2, $event3);
    }

    #[Test]
    public function publishesNoEventsWhenEmpty(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())
            ->method('dispatch');

        $bus = new SymfonyEventBus($messageBus, $this->stampTransformer);
        $bus->publish();
    }

    #[Test]
    public function publishesEventWithStampsFromEnvelopeAttribute(): void
    {
        $event = new TestAsyncEvent('aggregate-1');

        $capturedEnvelope = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use (&$capturedEnvelope) {
                $capturedEnvelope = $envelope;
                return $envelope;
            });

        $bus = new SymfonyEventBus($messageBus, $this->stampTransformer);
        $bus->publish($event);

        $this->assertNotNull($capturedEnvelope);
        $stamps = $capturedEnvelope->all(TransportNamesStamp::class);
        $this->assertCount(1, $stamps);
        $this->assertSame(['async'], $stamps[0]->getTransportNames());
    }

    #[Test]
    public function dispatchesEnvelopeWrappedMessage(): void
    {
        $event = new TestIntegrationEvent('aggregate-1');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use ($event) {
                $this->assertSame($event, $envelope->getMessage());

                return $envelope;
            });

        $bus = new SymfonyEventBus($messageBus, $this->stampTransformer);
        $bus->publish($event);
    }
}

final readonly class TestIntegrationEvent extends DomainEvent
{
}

#[TransportStamp('async')]
final readonly class TestAsyncEvent extends DomainEvent
{
}
