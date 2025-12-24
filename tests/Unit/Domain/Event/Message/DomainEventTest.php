<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Domain\Event\Message;

use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

class DomainEventTest extends TestCase
{
    #[Test]
    public function hasUuidV7Id(): void
    {
        $event = new TestDomainEvent('aggregate-123');

        $this->assertTrue(Uuid::isValid($event->id));
        $uuid = Uuid::fromString($event->id);
        $this->assertInstanceOf(UuidV7::class, $uuid);
    }

    #[Test]
    public function storesAggregateId(): void
    {
        $aggregateId = 'my-aggregate-id-456';
        $event = new TestDomainEvent($aggregateId);

        $this->assertSame($aggregateId, $event->aggregateId);
    }

    #[Test]
    public function hasOccurredOnTimestamp(): void
    {
        $before = new \DateTimeImmutable();
        $event = new TestDomainEvent('aggregate-123');
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->occurredOn);
        $this->assertLessThanOrEqual($after, $event->occurredOn);
    }

    #[Test]
    public function eachEventHasUniqueId(): void
    {
        $event1 = new TestDomainEvent('aggregate-123');
        $event2 = new TestDomainEvent('aggregate-123');

        $this->assertNotSame($event1->id, $event2->id);
    }

    #[Test]
    public function canExtendWithAdditionalProperties(): void
    {
        $event = new TestDomainEventWithData('aggregate-123', 'test-data', 42);

        $this->assertSame('test-data', $event->data);
        $this->assertSame(42, $event->count);
        $this->assertSame('aggregate-123', $event->aggregateId);
    }
}

final readonly class TestDomainEvent extends DomainEvent
{
}

final readonly class TestDomainEventWithData extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public string $data,
        public int $count,
    ) {
        parent::__construct($aggregateId);
    }
}
