# Error Handling

OpenSolid Core provides a structured approach to domain errors with support for error aggregation and state transition
validation.

## DomainError

The base class for all domain errors. It extends `\DomainException` and provides static factory methods:

```php
use OpenSolid\Core\Domain\Error\DomainError;

class InsufficientFunds extends DomainError
{
}

// Throw a single error
throw InsufficientFunds::create('Account balance is insufficient.');

// Throw multiple errors aggregated into one
throw InsufficientFunds::createMany([
    InsufficientFunds::create('Minimum balance not met.'),
    InsufficientFunds::create('Pending transactions exceed available funds.'),
]);
```

### Factory Methods

| Method         | Description                                                                                                                                         |
|----------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| `create()`     | Creates a single domain error with a message, code, and optional previous exception                                                                 |
| `createMany()` | Aggregates multiple `DomainError` instances into one, joining messages with spaces. The individual errors are accessible via the `$errors` property |

### Accessing Aggregated Errors

When using `createMany()`, the individual errors are available on the `$errors` property:

```php
try {
    $this->validateOrder($order);
} catch (DomainError $e) {
    foreach ($e->errors as $error) {
        // handle each individual error
    }
}
```

## Built-in Error Types

### EntityNotFound

For cases where a requested entity does not exist:

```php
use OpenSolid\Core\Domain\Error\EntityNotFound;

throw EntityNotFound::create('Product with ID "123" not found.');
```

### InvariantViolation

For domain rule violations:

```php
use OpenSolid\Core\Domain\Error\InvariantViolation;

throw InvariantViolation::create('Order total must be greater than zero.');
```

### InvalidStateTransition

For invalid state machine transitions. This is a specialized `InvariantViolation` that works with `BackedEnum` values:

```php
use OpenSolid\Core\Domain\Error\InvalidStateTransition;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}

// Define allowed transitions
$allowed = [OrderStatus::Shipped];

// Throws if the transition is not allowed
throw InvalidStateTransition::transition(
    from: OrderStatus::Confirmed,
    to: OrderStatus::Cancelled,
    allowed: $allowed,
);
// Message: The "OrderStatus" cannot transition from "confirmed" to "cancelled".
//          Allowed transition states from "confirmed": shipped.
```

For terminal states (no transitions allowed), pass an empty array:

```php
throw InvalidStateTransition::transition(
    from: OrderStatus::Delivered,
    to: OrderStatus::Cancelled,
    allowed: [],
);
// Message: The "OrderStatus" cannot transition from "delivered" to "cancelled".
//          State "delivered" is terminal and cannot transition to any other state.
```

## InMemoryErrorStore

The `InMemoryErrorStore` trait provides error accumulation for domain entities or services. Instead of throwing on the
first error, you can collect multiple validation errors and throw them all at once:

```php
use OpenSolid\Core\Domain\Error\Store\InMemoryErrorStore;

class OrderValidator
{
    use InMemoryErrorStore;

    public function validate(Order $order): void
    {
        if ($order->total <= 0) {
            $this->pushDomainError('Order total must be greater than zero.');
        }

        if (empty($order->items)) {
            $this->pushDomainError('Order must contain at least one item.');
        }

        if (null === $order->shippingAddress) {
            $this->pushDomainError(
                InvariantViolation::create('Shipping address is required.')
            );
        }

        // Throws all accumulated errors (or nothing if there are none)
        $this->throwDomainErrors();
    }
}
```

The trait provides two methods:

| Method                | Visibility  | Description                                  |
|-----------------------|-------------|----------------------------------------------|
| `pushDomainError()`   | `protected` | Accepts a `string` or `DomainError` instance |
| `throwDomainErrors()` | `protected` | Throws accumulated errors if any exist       |

The `throwDomainErrors()` behavior:

- **No errors:** Does nothing
- **One error:** Throws that single error directly
- **Multiple errors of the same type:** Throws via that type's `createMany()`
- **Multiple errors of different types:** Throws via `DomainError::createMany()`

## Custom Error Types

Create custom error classes by extending `DomainError` or any of the built-in types:

```php
class ProductOutOfStock extends InvariantViolation
{
}

class UserAlreadyExists extends DomainError
{
}
```

The `__construct` is `final protected`, so always use the `create()` or `createMany()` factory methods.

## Full Example

```php
class Order
{
    use InMemoryErrorStore;

    public function confirm(): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw InvalidStateTransition::transition(
                from: $this->status,
                to: OrderStatus::Confirmed,
                allowed: [OrderStatus::Draft],
            );
        }

        if ($this->total <= 0) {
            $this->pushDomainError('Order total must be positive.');
        }

        if ($this->items->isEmpty()) {
            $this->pushDomainError('Order must have at least one item.');
        }

        $this->throwDomainErrors();

        $this->status = OrderStatus::Confirmed;
    }
}
```
