<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Repository;

use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;

/**
 * @template TKey of array-key
 * @template TValue of object
 *
 * @implements Collection<TKey, TValue>
 */
final readonly class ReadonlyCollection implements Collection
{
    /**
     * @param DoctrineCollection<TKey, TValue> $collection
     */
    public function __construct(
        private DoctrineCollection $collection,
    ) {
    }

    public function getIterator(): \Traversable
    {
        return $this->collection->getIterator();
    }

    public function count(): int
    {
        return $this->collection->count();
    }

    /** @phpstan-assert-if-true TValue $element */
    public function contains(mixed $element): bool
    {
        return $this->collection->contains($element);
    }

    public function isEmpty(): bool
    {
        return $this->collection->isEmpty();
    }

    public function containsKey(int|string $key): bool
    {
        return $this->collection->containsKey($key);
    }

    public function get(int|string $key): mixed
    {
        return $this->collection->get($key);
    }

    public function getKeys(): array
    {
        return $this->collection->getKeys();
    }

    public function getValues(): array
    {
        return $this->collection->getValues();
    }

    public function toArray(): array
    {
        return $this->collection->toArray();
    }

    public function first(): mixed
    {
        return $this->collection->first();
    }

    public function last(): mixed
    {
        return $this->collection->last();
    }

    public function key(): int|string|null
    {
        return $this->collection->key();
    }

    public function current(): mixed
    {
        return $this->collection->current();
    }

    public function next(): mixed
    {
        return $this->collection->next();
    }

    public function slice(int $offset, ?int $length = null): array
    {
        return $this->collection->slice($offset, $length);
    }

    public function exists(\Closure $p): bool
    {
        return $this->collection->exists($p);
    }

    /**
     * @return self<TKey, TValue>
     */
    public function filter(\Closure $p): self
    {
        return new self($this->collection->filter($p));
    }

    /**
     * @template U of object
     *
     * @return self<TKey, U>
     */
    public function map(\Closure $func): self
    {
        /** @var DoctrineCollection<TKey, U> $collection */
        $collection = $this->collection->map($func);

        return new self($collection);
    }

    public function partition(\Closure $p): array
    {
        return $this->collection->partition($p);
    }

    public function forAll(\Closure $p): bool
    {
        return $this->collection->forAll($p);
    }

    /** @phpstan-assert-if-true TValue $element */
    public function indexOf(mixed $element): int|string|false
    {
        return $this->collection->indexOf($element);
    }

    public function findFirst(\Closure $p): mixed
    {
        return $this->collection->findFirst($p);
    }

    public function reduce(\Closure $func, mixed $initial = null): mixed
    {
        return $this->collection->reduce($func, $initial);
    }

    /**
     * @return self<TKey, TValue>
     */
    public function matching(Criteria $criteria): self
    {
        if (!$this->collection instanceof Selectable) {
            throw new \LogicException(sprintf('Expected a selectable collection, got "%s".', $this->collection::class));
        }

        /** @var DoctrineCollection<TKey, TValue>&Selectable<TKey, TValue> $collection */
        $collection = $this->collection->matching($criteria);

        return new self($collection);
    }
}
