<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Repository;

/**
 * Represents a paginated item with its associated cursor.
 *
 * @template T
 */
final readonly class CursorItem
{
    /**
     * @param T $value
     */
    public function __construct(
        private mixed $value,
        private Cursor $cursor,
    ) {
    }

    /**
     * @return T
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getCursor(): Cursor
    {
        return $this->cursor;
    }
}
