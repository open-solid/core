# Commands & Queries

OpenSolid Core implements the **Command-Query Separation (CQS)** pattern, providing distinct message types and buses for write operations (commands) and read operations (queries).

## Commands

A command represents an intent to change the system state. Commands extend the `Command` base class and are readonly value objects.

```php
use OpenSolid\Core\Application\Command\Message\Command;

/** @extends Command<void> */
final readonly class RegisterUser extends Command
{
    public function __construct(
        public string $email,
        public string $name,
    ) {
    }
}
```

The `@extends Command<T>` annotation defines the return type. Use `void` for commands that return nothing, or a specific type when a result is needed (e.g., `Command<string>` to return an ID).

### Command Handlers

Mark a class with `#[AsCommandHandler]` to register it as a command handler. The handler must implement `__invoke()` with the command as its parameter:

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

### CommandBus

The `CommandBus` interface dispatches commands to their handlers:

```php
use OpenSolid\Core\Application\Command\Bus\CommandBus;

interface CommandBus
{
    /** @return T */
    public function execute(Command $command): mixed;
}
```

Inject it wherever you need to dispatch commands:

```php
readonly class CreateOrderAction
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->commandBus->execute(new CreateOrder(
            customerId: $request->get('customer_id'),
            items: $request->get('items'),
        ));

        return new Response(status: 201);
    }
}
```

## Queries

A query represents a request for data. Queries extend the `Query` base class:

```php
use OpenSolid\Core\Application\Query\Message\Query;

/** @extends Query<UserView> */
readonly class GetUserById extends Query
{
    public function __construct(
        public string $id,
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
readonly class GetUserByIdHandler
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

The `QueryBus` interface dispatches queries to their handlers:

```php
use OpenSolid\Core\Application\Query\Bus\QueryBus;

interface QueryBus
{
    /** @return T */
    public function ask(Query $query): mixed;
}
```

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
        $user = $this->queryBus->ask(new GetUserById($id));

        return new JsonResponse($user);
    }
}
```

## Bus Strategy

The bundle supports three bus strategies configured via the `opensolid.bus.strategy` option:

| Strategy   | Description |
|------------|-------------|
| `symfony`  | Uses Symfony Messenger (default when `symfony/messenger` is installed) |
| `native`   | Uses the native `open-solid/bus` implementation |
| `custom`   | Disables auto-configuration — you provide your own `CommandBus`, `QueryBus`, and `EventBus` implementations |

See [Configuration](configuration.md) for details.
