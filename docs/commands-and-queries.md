# Commands & Queries

OpenSolid Core implements the **Command-Query Separation (CQS)** pattern, providing distinct message types and buses for
write operations (commands) and read operations (queries).

## Commands

A command represents an intent to change the system state. Commands extend the `Command` base class and are readonly
value objects. Prefer using **value objects** for their properties instead of primitive types — this enforces domain
invariants at the boundary:

```php
use OpenSolid\Core\Application\Command\Message\Command;

/** @extends Command<void> */
final readonly class RegisterUser extends Command
{
    public function __construct(
        public UserEmail $email,
        public UserName $name,
    ) {
    }
}
```

The `@extends Command<T>` annotation defines the return type. Use `void` for commands that return nothing, or a specific
type when a result is needed (e.g., `Command<UserId>` to return an ID).

### Command Handlers

Mark a class with `#[AsCommandHandler]` to register it as a command handler. The handler must implement `__invoke()`
with the command as its parameter:

```php
use OpenSolid\Core\Application\Command\Handler\Attribute\AsCommandHandler;

#[AsCommandHandler]
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(RegisterUser $command): void
    {
        $user = User::register($command->email, $command->name);

        $this->users->add($user);
    }
}
```

The handler is auto-discovered and configured — no manual service tagging required.

> **Note:** Command handlers are automatically wrapped in two middleware layers:
>
> - **Doctrine Transaction Middleware** — each handler executes within a database transaction. If the handler throws an
    exception, the transaction is rolled back automatically.
> - **Event Publisher Middleware** — after the handler completes successfully, domain events accumulated in entities (
    via the `InMemoryEventStore` trait) are collected from Doctrine's Unit of Work and published through the `EventBus`.
>
> This means you don't need to manually manage transactions or dispatch domain events in your handlers — both concerns
> are handled transparently by the bus middleware pipeline.

### CommandBus

The `CommandBus` interface dispatches commands to their handlers. Inject it wherever you need to dispatch commands:

```php
final readonly class CreateOrderAction
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->commandBus->execute(new RegisterUser(
            email: UserEmail::from($request->get('email')),
            name: UserName::from($request->get('name')),
        ));

        return new Response(status: 201);
    }
}
```

### Routing Commands to Transports

By default, commands are dispatched synchronously through the `sync` transport. Use the `#[TransportStamp]` attribute to
route a command to a different transport, e.g. for asynchronous processing:

```php
use OpenSolid\Core\Application\Command\Message\Command;
use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;

#[TransportStamp('async')]
/** @extends Command<void> */
final readonly class SendNewsletter extends Command
{
    public function __construct(
        public string $subject,
        public string $content,
    ) {
    }
}
```

> **Note:** Commands routed to async transports are serialized for message queue delivery. Use **primitive types** (
> `string`, `int`, `float`, `bool`) for their properties instead of value objects to ensure proper serialization.

You can also route to multiple transports at once:

```php
#[TransportStamp(['async', 'audit'])]
```

## Queries

A query represents a request for data. Queries extend the `Query` base class:

```php
use OpenSolid\Core\Application\Query\Message\Query;

/** @extends Query<User> */
final readonly class GetUserById extends Query
{
    public function __construct(
        public UserId $id,
    ) {
    }
}
```

The `@extends Query<T>` annotation defines the expected return type.

### Query Handlers

Mark a class with `#[AsQueryHandler]` to register it as a query handler:

```php
use App\User\Domain\Error\UserNotFound;
use OpenSolid\Core\Application\Query\Handler\Attribute\AsQueryHandler;

#[AsQueryHandler]
final readonly class GetUserByIdHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(GetUserById $query): User
    {
        return $this->users->ofId($query->id) ?? throw UserNotFound::withId($query->id);
    }
}
```

### QueryBus

The `QueryBus` interface dispatches queries to their handlers.

Usage:

```php
readonly class UserController
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function show(string $id): Response
    {
        $user = $this->queryBus->ask(new GetUserById(UserId::from($id)));

        return new JsonResponse($user);
    }
}
```

## Bus Strategy

The bundle supports three bus strategies configured via the `opensolid.bus.strategy` option:

| Strategy  | Description                                                                                                 |
|-----------|-------------------------------------------------------------------------------------------------------------|
| `symfony` | Uses Symfony Messenger (default when `symfony/messenger` is installed)                                      |
| `native`  | Uses the native `open-solid/bus` implementation                                                             |
| `custom`  | Disables auto-configuration — you provide your own `CommandBus`, `QueryBus`, and `EventBus` implementations |

See [Configuration](configuration.md) for details.
