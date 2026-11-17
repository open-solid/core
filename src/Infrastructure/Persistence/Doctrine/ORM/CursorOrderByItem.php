<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\AST\PathExpression;

/**
 * @internal
 */
final readonly class CursorOrderByItem
{
    /** @param ClassMetadata<object>|null $metadata */
    public function __construct(
        public PathExpression|string $expression,
        public OrderDirection $direction,
        public string $paramKey,
        public ?ClassMetadata $metadata = null,
    ) {
    }
}
