<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Repository;

/**
 * @template T of object
 *
 * @extends \Traversable<T>
 */
interface Paginator extends \Traversable, \Countable
{
    /**
     * Gets the current page number.
     */
    public function getCurrentPage(): int;

    /**
     * Gets the number of items by page.
     */
    public function getItemsPerPage(): int;

    /**
     * Gets last page.
     */
    public function getLastPage(): int;

    /**
     * Gets the number of items in the whole collection.
     */
    public function getTotalItems(): int;
}
