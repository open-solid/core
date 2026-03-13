<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Repository;

use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\Selectable;

/**
 * @template TKey of array-key
 * @template TValue of object
 *
 * @extends ReadableCollection<TKey, TValue>
 * @extends Selectable<TKey, TValue>
 */
interface Collection extends ReadableCollection, Selectable
{
}
