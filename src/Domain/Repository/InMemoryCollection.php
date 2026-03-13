<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Repository;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @template TKey of array-key
 * @template TValue of object
 *
 * @extends ArrayCollection<TKey, TValue>
 *
 * @implements Collection<TKey, TValue>
 */
class InMemoryCollection extends ArrayCollection implements Collection
{
}
