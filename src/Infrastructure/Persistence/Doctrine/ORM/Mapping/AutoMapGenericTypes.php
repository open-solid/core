<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM\Mapping;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

final readonly class AutoMapGenericTypes
{
    /**
     * @param array<class-string> $types
     */
    public function __construct(
        private array $types = [],
    ) {
    }

    public function __invoke(LoadClassMetadataEventArgs $args): void
    {
        $classMetadata = $args->getClassMetadata();
        $reflectionClass = $classMetadata->getReflectionClass();

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            if (!$reflectionClass->hasProperty($fieldName)) {
                continue;
            }

            $mapping = $classMetadata->getFieldMapping($fieldName);

            if ($mapping->declaredField && isset($classMetadata->embeddedClasses[$mapping->declaredField])) {
                continue;
            }

            $type = $reflectionClass->getProperty($fieldName)->getType();
            $propertyType = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if (!$propertyType || !\class_exists($propertyType)) {
                continue;
            }

            foreach ($this->types as $type) {
                if (\is_subclass_of($propertyType, $type)) {
                    $classMetadata->fieldMappings[$fieldName]->type = $propertyType;

                    continue 2;
                }
            }
        }
    }
}
