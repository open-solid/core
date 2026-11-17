<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Domain\Event\Store;

use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Domain\Event\Store\InMemoryEventStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InMemoryEventStoreTest extends TestCase
{
    #[Test]
    public function pullDomainEventsReturnsEmptyArrayInitially(): void
    {
        $entity = new TestEntity();

        $events = $entity->pullDomainEvents();

        $this->assertSame([], $events);
    }

    #[Test]
    public function pushAndPullSingleEvent(): void
    {
        $entity = new TestEntity();
        $entity->doSomething();

        $events = $entity->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(TestSomethingHappened::class, $events[0]);
    }

    #[Test]
    public function pullDomainEventsClearsEvents(): void
    {
        $entity = new TestEntity();
        $entity->doSomething();

        $entity->pullDomainEvents();
        $events = $entity->pullDomainEvents();

        $this->assertSame([], $events);
    }

    #[Test]
    public function pushMultipleDifferentEvents(): void
    {
        $entity = new TestEntity();
        $entity->doSomething();
        $entity->doAnotherThing();

        $events = $entity->pullDomainEvents();

        $this->assertCount(2, $events);
        $this->assertInstanceOf(TestSomethingHappened::class, $events[0]);
        $this->assertInstanceOf(TestAnotherThingHappened::class, $events[1]);
    }

    #[Test]
    public function everyEventOfTheSameClassIsKept(): void
    {
        $entity = new TestEntity();
        $entity->doSomething();
        $entity->doSomething();
        $entity->doSomething();

        $events = $entity->pullDomainEvents();

        $this->assertCount(3, $events);
    }

    #[Test]
    public function eventsAreKeptInTheOrderTheyWerePushed(): void
    {
        $entity = new TestEntity();
        $entity->doSomethingWithValue('first');
        $entity->doAnotherThing();
        $entity->doSomethingWithValue('second');

        $events = $entity->pullDomainEvents();

        $this->assertCount(3, $events);
        $this->assertInstanceOf(TestSomethingWithValue::class, $events[0]);
        $this->assertSame('first', $events[0]->value);
        $this->assertInstanceOf(TestAnotherThingHappened::class, $events[1]);
        $this->assertInstanceOf(TestSomethingWithValue::class, $events[2]);
        $this->assertSame('second', $events[2]->value);
    }

    #[Test]
    public function eventsAreReturnedAsIndexedArray(): void
    {
        $entity = new TestEntity();
        $entity->doSomething();
        $entity->doAnotherThing();

        $events = $entity->pullDomainEvents();

        $this->assertArrayHasKey(0, $events);
        $this->assertArrayHasKey(1, $events);
    }
}

class TestEntity
{
    use InMemoryEventStore;

    public function doSomething(): void
    {
        $this->pushDomainEvent(new TestSomethingHappened('test-id'));
    }

    public function doSomethingWithValue(string $value): void
    {
        $this->pushDomainEvent(new TestSomethingWithValue('test-id', $value));
    }

    public function doAnotherThing(): void
    {
        $this->pushDomainEvent(new TestAnotherThingHappened('test-id'));
    }
}

final readonly class TestSomethingHappened extends DomainEvent
{
}

final readonly class TestSomethingWithValue extends DomainEvent
{
    public function __construct(string $aggregateId, public string $value)
    {
        parent::__construct($aggregateId);
    }
}

final readonly class TestAnotherThingHappened extends DomainEvent
{
}
