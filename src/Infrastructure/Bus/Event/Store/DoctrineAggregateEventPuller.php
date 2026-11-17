<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Bus\Event\Store;

use Doctrine\ORM\EntityManagerInterface;
use OpenSolid\Core\Domain\Event\Store\AggregateEventPuller;

final readonly class DoctrineAggregateEventPuller implements AggregateEventPuller
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function pullDomainEvents(): array
    {
        $events = [[]];
        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (method_exists($entity, 'pullDomainEvents')) {
                    $events[] = $entity->pullDomainEvents();
                }
            }
        }

        return array_merge(...$events);
    }
}
