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

Every domain event automatically receives:

| Property       | Type                | Description                                        |
|----------------|---------------------|----------------------------------------------------|
| `$id`          | `string`            | Unique event ID (UUID v7, auto-generated)          |
| `$aggregateId` | `string`            | ID of the aggregate that produced the event        |
| `$occurredOn`  | `DateTimeImmutable` | Timestamp when the event occurred (auto-generated) |

## EventBus

The `EventBus` interface publishes domain events:

```php
use OpenSolid\Core\Domain\Event\Bus\EventBus;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
```

When using the `symfony` bus strategy, domain events are automatically published by Messenger middleware after the
command handler completes successfully — you don't need to call `publish()` manually.

## InMemoryEventStore

The `InMemoryEventStore` trait provides event accumulation for aggregate entities. Use it to collect events during a
domain operation and pull them out later for publishing:

```php
use OpenSolid\Core\Domain\Event\Store\InMemoryEventStore;

class User
{
    use InMemoryEventStore;

    private function __construct(
        public readonly string $id,
        public readonly string $email,
        public string $name,
    ) {
    }

    public static function register(string $email, string $name): self
    {
        $user = new self(Uuid::v7()::generate(), $email, $name);
        $user->pushDomainEvent(new UserRegistered($user->id, $email, $name));

        return $user;
    }

    public function rename(string $name): void
    {
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
readonly class SendWelcomeEmailOnUserRegistered
{
    public function __construct(
        private Mailer $mailer,
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

Domain events are always dispatched asynchronously through the `async` transport by default when using the `symfony` bus
strategy. This ensures that event subscribers do not block the main request.

### Routing to Additional Transports

Use the `#[TransportStamp]` attribute to route a domain event to additional transports beyond the default `async`:

```php
use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;
use OpenSolid\Core\Domain\Event\Message\DomainEvent;

#[TransportStamp('audit')]
final readonly class PaymentReceived extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public float $amount,
    ) {
        parent::__construct($aggregateId);
    }
}
```

You can specify multiple transports:

```php
#[TransportStamp(['audit', 'analytics'])]
```

## Full Example

```php
// 1. Define the event
readonly class ProductCreated extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public string $name,
    ) {
        parent::__construct($aggregateId);
    }
}

// 2. Raise it from your entity
class Product
{
    use InMemoryEventStore;

    public static function create(string $name): self
    {
        $product = new self(Uuid::v7()::generate(), $name);
        $product->pushDomainEvent(new ProductCreated($product->id, $name));

        return $product;
    }
}

// 3. Handle the command — events are published automatically by middleware
#[AsCommandHandler]
readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepository $products,
    ) {
    }

    public function __invoke(CreateProduct $command): void
    {
        $product = Product::create($command->name);

        $this->products->add($product);
    }
}

// 4. React to the event
#[AsDomainEventSubscriber]
readonly class NotifyOnProductCreated
{
    public function __invoke(ProductCreated $event): void
    {
        // send notification, update read model, etc.
    }
}
```
