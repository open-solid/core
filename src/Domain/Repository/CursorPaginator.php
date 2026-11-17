<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Repository;

/**
 * Contract for cursor-based pagination.
 *
 * Unlike offset-based pagination, cursor pagination uses opaque pointers (cursors)
 * derived from the last seen row to fetch the next or previous page. This makes it
 * stable and performant on large datasets, at the cost of random access to a page
 * by its number.
 *
 * Implementations expose the navigation API below once the results have been fetched.
 *
 * @template T of object
 *
 * @extends \Traversable<T>
 */
interface CursorPaginator extends \Traversable
{
    /**
     * Returns the number of items on the current page.
     */
    public function countPageItems(): int;

    /**
     * Returns the total number of matching items, ignoring the cursor and limit.
     */
    public function getTotalCount(): int;

    /**
     * Returns whether there is a next page.
     */
    public function hasNextPage(): bool;

    /**
     * Returns whether there is a previous page.
     */
    public function hasPreviousPage(): bool;

    /**
     * Returns whether the result set spans more than one page.
     */
    public function hasToPaginate(): bool;

    /**
     * Returns the cursor object for the next page.
     *
     * @throws \LogicException if there is no next page; check {@see hasNextPage()} first
     */
    public function getNextCursor(): Cursor;

    /**
     * Returns the cursor object for the previous page.
     *
     * @throws \LogicException if there is no previous page; check {@see hasPreviousPage()} first
     */
    public function getPreviousCursor(): Cursor;

    /**
     * Returns the encoded cursor string for the next page.
     *
     * @throws \LogicException if there is no next page; check {@see hasNextPage()} first
     */
    public function getNextCursorAsString(): string;

    /**
     * Returns the encoded cursor string for the previous page.
     *
     * @throws \LogicException if there is no previous page; check {@see hasPreviousPage()} first
     */
    public function getPreviousCursorAsString(): string;

    /**
     * Returns the cursor for a given item.
     *
     * @param T $item
     */
    public function getCursorForItem(mixed $item, bool $isNext = true): Cursor;

    /**
     * Returns items wrapped with their associated cursors.
     *
     * @return array<int, CursorItem<T>>
     */
    public function getItems(): array;

    /**
     * Returns the raw items for the current page.
     *
     * @return list<T>
     */
    public function getValues(): array;
}
