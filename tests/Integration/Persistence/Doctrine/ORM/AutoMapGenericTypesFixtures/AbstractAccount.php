<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\AutoMapGenericTypesFixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractAccount
{
    #[ORM\Column(type: 'string')]
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }
}
