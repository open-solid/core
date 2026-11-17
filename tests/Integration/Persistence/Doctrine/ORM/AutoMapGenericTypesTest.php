<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\ORMSetup;
use OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM\Mapping\AutoMapGenericTypes;
use OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\AutoMapGenericTypesFixtures\AbstractReference;
use OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\AutoMapGenericTypesFixtures\Account;
use OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\AutoMapGenericTypesFixtures\Reference;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AutoMapGenericTypesTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__.'/AutoMapGenericTypesFixtures'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);
    }

    #[Test]
    public function inheritedPrivateFieldIsSkippedInsteadOfThrowing(): void
    {
        $metadata = $this->em->getClassMetadata(Account::class);

        // Sanity-check the reproduction: 'name' is a mapped field whose backing
        // property is private on the mapped superclass, so it is invisible to
        // the concrete class' reflection - the case that made getProperty() throw.
        self::assertContains('name', $metadata->getFieldNames());
        self::assertFalse($metadata->getReflectionClass()->hasProperty('name'));

        $listener = new AutoMapGenericTypes([AbstractReference::class]);

        // Before the hasProperty() guard this threw:
        // "Property ...Account::$name does not exist".
        $listener(new LoadClassMetadataEventArgs($metadata, $this->em));

        // The inherited-private field is left untouched.
        self::assertSame('string', $metadata->getFieldMapping('name')->type);
    }

    #[Test]
    public function ownGenericTypedFieldIsStillRemapped(): void
    {
        $metadata = $this->em->getClassMetadata(Account::class);

        $listener = new AutoMapGenericTypes([AbstractReference::class]);
        $listener(new LoadClassMetadataEventArgs($metadata, $this->em));

        // The guard does not over-skip: a generic-typed property the class
        // actually declares is still remapped to its concrete PHP type.
        self::assertSame(Reference::class, $metadata->getFieldMapping('reference')->type);
    }
}
