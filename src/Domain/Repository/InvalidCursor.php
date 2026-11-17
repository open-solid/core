<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Repository;

final class InvalidCursor extends \InvalidArgumentException
{
    public function __construct(string $cursor, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('The cursor "%s" could not be decoded.', $cursor), previous: $previous);
    }
}
