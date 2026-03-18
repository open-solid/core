# OpenSolid Core

Building blocks for **modular architecture** with [Symfony](https://symfony.com/).

Provides DDD-oriented abstractions ─ commands, queries, domain events, error handling, collections, and a convention-based module system ─ so you can focus on your domain logic instead of wiring infrastructure.

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://www.php.net/)
[![Symfony 7/8](https://img.shields.io/badge/Symfony-7%20%7C%208-black)](https://symfony.com/)

## Installation

```bash
composer require open-solid/core
```

The bundle is auto-registered via [Symfony Flex](https://symfony.com/doc/current/setup/flex.html).

### Modular Application Structure

Each bounded context lives in its own module with the same layered structure:

```bash
src/
├── Product/                                   # Module root
│   ├── Application/
│   │   └── Product/                           # Aggregate
│   │       ├── Create/                        # Use case (verb)
│   │       │   ├── CreateProduct.php          # Command
│   │       │   └── CreateProductHandler.php   # Handler
│   │       └── Find/
│   │           ├── FindProduct.php            # Query
│   │           └── FindProductHandler.php     # Handler
│   ├── Domain/
│   │   ├── Error/                             # Domain-specific errors
│   │   ├── Event/                             # Domain events
│   │   └── Model/                             # Aggregate, entities and value objects
│   │       └── Product.php
│   └── Infrastructure/
│       ├── Resources/
│       │   └── config/
│       │       ├── doctrine/
│       │       │   └── mapping/               # Doctrine ORM mappings
│       │       ├── packages/                  # Package-specific config overrides
│       │       └── services.yaml              # Service definitions
│       └── ProductExtension.php               # Module extension
│
├── Order/                                     # Another module
│   ├── Application/
│   ├── Domain/
│   └── Infrastructure/
│       └── OrderExtension.php
│
└── Kernel.php
```

Each `ModuleExtension` automatically registers services, and Doctrine mappings for its module ─ zero manual wiring.

## Documentation

- **[Configuration](docs/configuration.md)** ─ Bus strategies, Doctrine ORM mapping, and API Platform resource settings.
- **[Commands & Queries (CQS)](docs/commands-and-queries.md)** ─ Type-safe command-query separation with auto-discovered handlers.
- **[Domain Events](docs/domain-events.md)** ─ Raise and react to events with automatic publishing after command execution.
- **[Error Handling](docs/error-handling.md)** ─ Structured domain errors with factory methods and batch error accumulation.
- **[Collections](docs/collections.md)** ─ Domain repository abstractions built on top of Doctrine Collections.
- **[Modular Architecture](docs/modular-architecture.md)** ─ Convention-based module system with automatic service registration.
