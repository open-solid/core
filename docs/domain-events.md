# Domain Events

Domain events represent meaningful occurrences in your domain. They are immutable records of past actions that other
parts of the system can react to.

## DomainEvent

All domain events extend the abstract `DomainEvent` base class:

```php
use OpenSolid\Core\Domain\Event\Message\DomainEvent;

final readonly class UserRegistered extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public string $email,
        public string $name,
    ) {
        parent::__construct($aggregateId);
    }
}
```

Domain events are dispatched asynchronously by default through the `async` transport when using the `symfony` bus
strategy. This ensures that event subscribers do not block the main request.

> **Note:** Events routed to async transports are serialized for message queue delivery. Use **primitive types** (`string`,
> `int`, `float`, `bool`) for their properties instead of value objects to ensure proper serialization.


Every domain event automatically receives:

| Property       | Type                | Description                                        |
|----------------|---------------------|----------------------------------------------------|
| `$id`          | `string`            | Unique event ID (UUID v7, auto-generated)          |
| `$aggregateId` | `string`            | ID of the aggregate that produced the event        |
| `$occurredOn`  | `DateTimeImmutable` | Timestamp when the event occurred (auto-generated) |

## EventBus

The `EventBus` interface publishes domain events. When using the `symfony` bus strategy, domain events are automatically
published by Messenger middleware after the command handler completes successfully — you don't need to call `publish()`
manually.

## InMemoryEventStore

The `InMemoryEventStore` trait provides event accumulation for aggregate entities. Use it to collect events during a
domain operation and pull them out later for publishing:

```php
use OpenSolid\Core\Domain\Event\Store\InMemoryEventStore;

class User
{
    use InMemoryEventStore;

    private function __construct(
        private(set) UserId $id,
        private(set) UserEmail $email,
        private(set) UserName $name,
    ) {
    }

    public static function register(UserEmail $email, UserName $name): self
    {
        $user = new self(UserId::create(), $email, $name);
        $user->pushDomainEvent(new UserRegistered($user->id->value, $email->value, $name->value));

        return $user;
    }

    public function rename(UserName $name): void
    {
        if ($this->name->equals($name)) {
            return; // no change, so no event
        }
    
        $this->name = $name;
        $this->pushDomainEvent(new UserRenamed($this->id, $name));
    }
}
```

The trait provides two methods:

| Method               | Visibility  | Description                                  |
|----------------------|-------------|----------------------------------------------|
| `pushDomainEvent()`  | `protected` | Records a domain event (one per event class) |
| `pullDomainEvents()` | `public`    | Returns and clears all recorded events       |

> **Note:** `pushDomainEvent()` stores one event per event class. If the same event class is pushed multiple times, only
> the first instance is kept.

## Event Subscribers

Mark a class with `#[AsDomainEventSubscriber]` to register it as an event subscriber:

```php
use OpenSolid\Core\Infrastructure\Bus\Event\Subscriber\Attribute\AsDomainEventSubscriber;

#[AsDomainEventSubscriber]
final readonly class SendWelcomeEmailOnUserRegistered
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(UserRegistered $event): void
    {
        $this->mailer->send(new WelcomeEmail($event->email, $event->name));
    }
}
```

Subscribers are auto-discovered and configured. The handler method parameter type determines which event it subscribes
to.

### Routing to Additional Transports

Use the `#[TransportStamp]` attribute to route a domain event to additional transports beyond the default `async`:

```php
use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;
use OpenSolid\Core\Domain\Event\Message\DomainEvent;

#[TransportStamp(['async', 'audit'])]
final readonly class PaymentReceived extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public float $amount,
        public string $currency,
    ) {
        parent::__construct($aggregateId);
    }
}
```

See also [Commands & Queries — Routing Commands to Transports](commands-and-queries.md#routing-commands-to-transports) for routing commands to async transports.
