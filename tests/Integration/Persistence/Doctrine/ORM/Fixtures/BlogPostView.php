<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\Fixtures;

/**
 * A plain DTO (not a mapped entity) hydrated through the DQL `NEW` operator.
 *
 * Its public properties must be named after the ORDER BY fields so the cursor
 * paginator can read the pivot values straight off the DTO.
 */
final readonly class BlogPostView
{
    public function __construct(
        public int $id,
        public string $title,
    ) {
    }
}
