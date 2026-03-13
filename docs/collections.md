# Collections

OpenSolid Core provides collection interfaces and implementations that extend Doctrine Collections, giving you a rich API for working with sets of domain objects.

## Collection Interface

The `Collection` interface combines Doctrine's `ReadableCollection` and `Selectable` interfaces:

```php
use OpenSolid\Core\Domain\Repository\Collection;

/** @extends Collection<int, Product> */
interface ProductCollection extends Collection
{
}
```

This gives you access to methods like `filter()`, `map()`, `reduce()`, `first()`, `last()`, `count()`, `isEmpty()`, `exists()`, `forAll()`, `matching()`, and more.

## ReadonlyCollection

A readonly wrapper around any Doctrine Collection. Use it when you want to expose a collection without allowing modifications:

```php
use OpenSolid\Core\Domain\Repository\ReadonlyCollection;

class Order
{
    public function getItems(): ReadonlyCollection
    {
        return new ReadonlyCollection($this->items);
    }
}
```

`ReadonlyCollection` implements the full `Collection` interface (read operations only). Methods that return collections (like `filter()`, `map()`, `matching()`) return new `ReadonlyCollection` instances, preserving immutability.

### Available Operations

```php
$collection = new ReadonlyCollection($items);

// Querying
$collection->count();
$collection->isEmpty();
$collection->contains($item);
$collection->containsKey(0);
$collection->first();
$collection->last();
$collection->get(0);

// Iterating
$collection->exists(fn ($key, $item) => $item->isActive());
$collection->forAll(fn ($key, $item) => $item->isValid());
$collection->findFirst(fn ($key, $item) => $item->name === 'Widget');

// Transforming (returns new ReadonlyCollection instances)
$active = $collection->filter(fn ($item) => $item->isActive());
$names = $collection->map(fn ($item) => $item->name);
$total = $collection->reduce(fn ($carry, $item) => $carry + $item->price, 0);
$page = $collection->slice(0, 10);

// Criteria-based filtering
use Doctrine\Common\Collections\Criteria;

$criteria = Criteria::create()
    ->where(Criteria::expr()->gt('price', 100))
    ->orderBy(['name' => 'ASC'])
    ->setMaxResults(5);

$expensive = $collection->matching($criteria);
```

## InMemoryCollection

An in-memory implementation of `Collection` backed by Doctrine's `ArrayCollection`. Useful for testing and prototyping:

```php
use OpenSolid\Core\Domain\Repository\InMemoryCollection;

// Create from an array
$products = new InMemoryCollection([
    new Product('Widget', 9.99),
    new Product('Gadget', 19.99),
]);

// Use all Doctrine ArrayCollection methods
$products->add(new Product('Doohickey', 29.99));
$products->removeElement($someProduct);
$products->filter(fn (Product $p) => $p->price > 10);
```

Since `InMemoryCollection` extends `ArrayCollection`, it supports both read and write operations, making it ideal for in-memory repository implementations in tests:

```php
/** @extends InMemoryCollection<int, User> */
class InMemoryUserRepository extends InMemoryCollection implements UserRepository
{
    public function save(User $user): void
    {
        $this->add($user);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findFirst(
            fn ($key, User $user) => $user->email === $email
        );
    }
}
```

## GetOrCreateResource

A utility class for "get or create" patterns. It wraps a resource and indicates whether it was newly created or already existed:

```php
use OpenSolid\Core\Application\Command\Handler\Attribute\AsCommandHandler;
use OpenSolid\Core\Domain\Model\GetOrCreateResource;

#[AsCommandHandler]
final readonly class GetOrCreateTagHandler
{
    public function __construct(
        private TagRepository $tags,
    ) {
    }

    public function __invoke(GetOrCreateTag $command): GetOrCreateResource
    {
        if (null !== $tag = $this->tags->ofName($command->name)) {
            return GetOrCreateResource::existing($tag);
        }

        $tag = new Tag($command->name);
        $this->tags->add($tag);

        return GetOrCreateResource::created($tag);
    }
}
```

Usage:

```php
$result = $this->commandBus->execute(new GetOrCreateTag('php'));

$result->resource;  // The Tag instance
$result->created;   // true if newly created
$result->existing;  // true if already existed

if ($result->created) {
    // perform additional setup for new tags
}
```
