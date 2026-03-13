<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\LazyCriteriaCollection as BaseLazyCriteriaCollection;
use OpenSolid\Core\Domain\Repository\Collection;

/**
 * @template TKey of array-key
 * @template TValue of object
 *
 * @extends BaseLazyCriteriaCollection<TKey, TValue>
 *
 * @implements Collection<TKey, TValue>
 */
class LazyCriteriaCollection extends BaseLazyCriteriaCollection implements Collection
{
    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return self<TKey, T>
     */
    public static function for(EntityManagerInterface $em, string $className, Criteria $criteria): self
    {
        /** @var self<TKey, T> */
        return new self($em->getUnitOfWork()->getEntityPersister($className), $criteria);
    }
}
