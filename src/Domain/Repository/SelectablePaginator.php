<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\Selectable;

/**
 * @template T of object
 *
 * @implements Paginator<T>
 * @implements \IteratorAggregate<T>
 */
final readonly class SelectablePaginator implements \IteratorAggregate, Paginator
{
    /**
     * @var ReadableCollection<array-key,T>
     */
    private ReadableCollection $slicedCollection;
    private int $totalItems;

    /**
     * @template TInput of object
     *
     * @param Selectable<array-key, TInput> $selectable
     * @param null|\Closure(TInput): T      $mapper
     */
    public function __construct(
        public Selectable $selectable,
        private int $currentPage,
        private int $itemsPerPage,
        private ?\Closure $mapper = null,
    ) {
        $this->totalItems = $this->selectable instanceof \Countable ? $this->selectable->count() : $this->selectable->matching(Criteria::create(true))->count();

        $criteria = Criteria::create(true)
            ->setFirstResult(($currentPage - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage > 0 ? $itemsPerPage : null);

        /** @var ReadableCollection<array-key, T> $slicedCollection */
        $slicedCollection = $selectable->matching($criteria);
        $this->slicedCollection = $slicedCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastPage(): int
    {
        if (0 >= $this->itemsPerPage) {
            return 1;
        }

        return (int) max(ceil($this->totalItems / $this->itemsPerPage), 1);
    }

    /**
     * {@inheritdoc}
     */
    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->slicedCollection->count();
    }

    /**
     * {@inheritdoc}
     *
     * @return \Traversable<T>
     */
    public function getIterator(): \Traversable
    {
        if (null === $this->mapper) {
            yield from $this->slicedCollection;

            return;
        }

        foreach ($this->slicedCollection as $item) {
            yield ($this->mapper)($item);
        }
    }
}
