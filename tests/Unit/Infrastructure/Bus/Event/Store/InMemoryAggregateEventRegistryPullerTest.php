<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Event\Store;

use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Domain\Event\Store\InMemoryEventStore;
use OpenSolid\Core\Infrastructure\Bus\Event\Store\InMemoryAggregateEventRegistryPuller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InMemoryAggregateEventRegistryPullerTest extends TestCase
{
    #[Test]
    public function pullsNoEventsWhenNothingRegistered(): void
    {
        $puller = new InMemoryAggregateEventRegistryPuller();

        $this->assertSame([], $puller->pullDomainEvents());
    }

    #[Test]
    public function pullsEventsFromRegisteredAggregates(): void
    {
        $aggregateA = new TestRegistryAggregate();
        $aggregateA->doSomething('a');
        $aggregateB = new TestRegistryAggregate();
        $aggregateB->doSomething('b');

        $puller = new InMemoryAggregateEventRegistryPuller();
        $puller->register($aggregateA);
        $puller->register($aggregateB);

        $events = $puller->pullDomainEvents();

        $this->assertCount(2, $events);
        $this->assertInstanceOf(TestRegistryEvent::class, $events[0]);
        $this->assertInstanceOf(TestRegistryEvent::class, $events[1]);
        $this->assertSame('a', $events[0]->value);
        $this->assertSame('b', $events[1]->value);
    }

    #[Test]
    public function throwsWhenRegisteringAggregateWithoutPullDomainEvents(): void
    {
        $puller = new InMemoryAggregateEventRegistryPuller();

        $this->expectException(\LogicException::class);

        $puller->register(new \stdClass());
    }
}

class TestRegistryAggregate
{
    use InMemoryEventStore;

    public function doSomething(string $value): void
    {
        $this->pushDomainEvent(new TestRegistryEvent('aggregate-1', $value));
    }
}

final readonly class TestRegistryEvent extends DomainEvent
{
    public function __construct(string $aggregateId, public string $value)
    {
        parent::__construct($aggregateId);
    }
}
