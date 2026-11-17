<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\AutoMapGenericTypesFixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'auto_map_account')]
class Account extends AbstractAccount
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string')]
    public Reference $reference;

    public function __construct(int $id)
    {
        $this->id = $id;
    }
}
