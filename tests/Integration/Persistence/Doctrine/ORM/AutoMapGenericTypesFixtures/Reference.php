<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\AutoMapGenericTypesFixtures;

/**
 * Concrete generic type used as an entity property; must be auto-mapped.
 */
final class Reference extends AbstractReference
{
    public function __construct(
        public string $value = '',
    ) {
    }
}
