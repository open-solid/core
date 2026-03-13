# OpenSolid Core

Building blocks for modular architecture with Symfony.

OpenSolid Core provides a set of DDD (Domain-Driven Design) building blocks designed for creating modular Symfony applications. It offers clean abstractions for commands, queries, domain events, error handling, and collections — all integrated seamlessly with the Symfony ecosystem.

## Installation

```console
composer require open-solid/core
```

> **Requirements:** PHP 8.4+ and Symfony 7.0+/8.0+

The bundle is automatically registered via Symfony Flex. If needed, register it manually:

```php
// config/bundles.php
return [
    // ...
    OpenSolid\Core\CoreBundle::class => ['all' => true],
];
```

## Features

- **[Commands & Queries](commands-and-queries.md)** — CQS pattern with dedicated buses and auto-configured handlers
- **[Domain Events](domain-events.md)** — Event-driven architecture with in-memory event stores and async transport support
- **[Error Handling](error-handling.md)** — Structured domain errors with aggregation and state transition validation
- **[Collections](collections.md)** — Rich collection interfaces compatible with Doctrine, including readonly and in-memory implementations
- **[Modular Architecture](modular-architecture.md)** — Convention-based module system with auto-configured Doctrine mappings and API Platform resources
- **[Configuration](configuration.md)** — Bundle configuration reference

## Quick Start

### 1. Define a command and its handler

```php
use OpenSolid\Core\Application\Command\Message\Command;

/** @extends Command<void> */
readonly class CreateProduct extends Command
{
    public function __construct(
        public string $name,
        public float $price,
    ) {
    }
}
```

```php
use OpenSolid\Core\Application\Command\Handler\Attribute\AsCommandHandler;

#[AsCommandHandler]
readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepository $products,
    ) {
    }

    public function __invoke(CreateProduct $command): void
    {
        $product = new Product($command->name, $command->price);

        $this->products->add($product);
    }
}
```

### 2. Execute the command via the bus

```php
use OpenSolid\Core\Application\Command\Bus\CommandBus;

readonly class ProductController
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function create(): Response
    {
        $this->commandBus->execute(new CreateProduct('Widget', 9.99));

        return new Response(status: 201);
    }
}
```

That's it — the handler is automatically discovered and wired by the bundle.
